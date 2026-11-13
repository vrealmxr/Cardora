const encoder = new TextEncoder()
const decodeHex = text => Uint8Array.from(text.match(/../g), pair => parseInt(pair, 16))
const digestHex = bytes => [...new Uint8Array(bytes)].map(byte => byte.toString(16).padStart(2, '0')).join('')
let previousSecret, importedKey

// Dedicated signed public-HTML transport. Does not proxy any general API,
// authenticated request, image upload, scanner route or model request.
export async function handleOriginBridge(request, env, fetchOrigin = fetch) {
  const denied = () => new Response('Forbidden', {status:403, headers:{'Cache-Control':'no-store','X-Robots-Tag':'noindex'}})
  const url = new URL(request.url)
  const match = url.pathname.match(/^\/__cardora_public_html\/([a-f0-9]{64})$/)
  const locale = url.searchParams.get('locale')
  const client = request.headers.get('X-Cardora-Visitor-IP') || ''
  const visitor = request.headers.get('X-Cardora-Public-Visitor') || ''
  const signature = request.headers.get('X-Cardora-Public-Signature') || ''
  const timestamp = request.headers.get('X-Cardora-Public-Time') || ''
  const payload = request.headers.get('X-Cardora-Render-Request') || ''
  const secret = env.PUBLIC_RENDER_SIGNING_KEY || ''
  if (request.method !== 'GET' || !match || request.headers.has('Cookie') || request.headers.has('Authorization')
    || !['el','en'].includes(locale) || [...url.searchParams.keys()].length !== 1
    || !/^[0-9a-fA-F:.]{3,45}$/.test(client) || !/^[a-f0-9]{64}$/.test(visitor)
    || !/^[a-f0-9]{64}$/.test(signature) || !/^[a-f0-9]{64}$/.test(secret)
    || !/^\d{10}$/.test(timestamp) || Math.abs(Date.now()/1000 - Number(timestamp)) > 60
    || !payload || payload.length > 7000) return denied()
  try {
    if (secret !== previousSecret) {
      previousSecret = secret
      importedKey = crypto.subtle.importKey('raw',encoder.encode(secret),{name:'HMAC',hash:'SHA-256'},false,['verify'])
    }
    const key = await importedKey
    const visitorValid = await crypto.subtle.verify('HMAC',key,decodeHex(visitor),encoder.encode(`cardora-public-visitor-v1\n${client}`))
    const signatureValid = await crypto.subtle.verify('HMAC',key,decodeHex(signature),encoder.encode(['v1',timestamp,'GET',`public-html/${match[1]}`,locale,visitor].join('\n')))
    const raw = Uint8Array.from(atob(payload),char=>char.charCodeAt(0))
    if (!visitorValid || !signatureValid || digestHex(await crypto.subtle.digest('SHA-256',raw)) !== match[1]) return denied()
    const headers = new Headers({Accept:'application/octet-stream','X-Real-IP':client,'CF-Connecting-IP':client,
      'User-Agent':'Cardora-PublicRenderer/2.0 (+https://cardora.gr)'})
    for (const name of ['X-Cardora-Public-Visitor','X-Cardora-Public-Time','X-Cardora-Public-Signature','X-Cardora-Render-Request']) headers.set(name,request.headers.get(name))
    // A separate TLS origin vhost avoids re-entering Pages on cardora.gr.
    // Both visitor headers come only from the verified HMAC, never forwarded input.
    const result = await fetchOrigin(`https://public-origin.cardora.gr/index.php/api/public-html/${match[1]}?locale=${locale}`,{
      method:'GET',headers,redirect:'manual',signal:AbortSignal.timeout(11000),
    })
    if (![200,404].includes(result.status)) {
      const location = result.headers.get('location')
      console.error('Public origin transport status', {status:result.status,
        location:location?.replace(/[a-f0-9]{64}/g,'[digest]').slice(0,200) ?? null})
    }
    return result
  } catch {
    return new Response('Public origin unavailable',{status:503,headers:{'Cache-Control':'no-store','Retry-After':'30','X-Robots-Tag':'noindex'}})
  }
}

export default {fetch:(request,env)=>handleOriginBridge(request,env)}
