import { createPublicRenderSigner } from './publicRenderSigner.js'

export async function requestOriginPage(request, env, fetchResource) {
  const url = new URL(request.url)
  const mode = ['cardora.gr', 'www.cardora.gr'].includes(url.hostname) ? 'production' : 'preview'
  const payload = JSON.stringify({ version: 1, path: url.pathname + url.search, mode, release: env.PUBLIC_RENDER_RELEASE })
  if (!/^[a-f0-9]{16}$/.test(env.PUBLIC_RENDER_RELEASE || '')) throw new Error('Public renderer release missing')
  const digest = [...new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(payload)))].map(byte => byte.toString(16).padStart(2, '0')).join('')
  const locale = /^\/en(?:\/|$)/.test(url.pathname) ? 'en' : 'el'
  const resource = `public-html/${digest}`
  const sign = createPublicRenderSigner(request, env.PUBLIC_RENDER_SIGNING_KEY)
  const clientIp = request.headers.get('CF-Connecting-IP')
  if (!clientIp || !/^[0-9a-fA-F:.]{3,45}$/.test(clientIp)) throw new Error('Trusted edge visitor address missing')
  // A Worker Custom Domain supports both same-zone Pages and cross-zone
  // preview fetches. A Worker Route does not have equivalent routing semantics.
  const response = await (fetchResource || fetch)(`https://public-render.cardora.gr/__cardora_public_html/${digest}?locale=${locale}`, {
    headers: { Accept: 'text/html', 'X-Cardora-Render-Request': btoa(String.fromCharCode(...new TextEncoder().encode(payload))),
      // The dedicated bridge checks that this platform address matches the
      // signed visitor partition before forwarding it to the same-zone origin.
      'X-Cardora-Visitor-IP': clientIp,
      'User-Agent': 'Cardora-PublicRenderer/2.0 (+https://cardora.gr)', ...(await sign(resource, locale)) },
    redirect: 'manual', credentials: 'omit', signal: AbortSignal.timeout(12000),
  })
  // Never forward backend cookies, CORS, internal headers or exception bodies.
  const headers = new Headers({ 'Cache-Control': 'no-store', 'X-Content-Type-Options': 'nosniff',
    'X-Frame-Options': 'SAMEORIGIN', 'Referrer-Policy': 'strict-origin-when-cross-origin' })
  if (mode === 'preview') headers.set('X-Robots-Tag', 'noindex, nofollow')
  if (![200, 404].includes(response.status)) {
    await response.body?.cancel()
    console.error('Public origin unavailable', { status: response.status, ray: response.headers.get('cf-ray') })
    headers.set('Retry-After', '30'); headers.set('X-Robots-Tag', 'noindex'); headers.set('Content-Type', 'text/html; charset=utf-8')
    return new Response('<!doctype html><html lang="el"><meta charset="utf-8"><title>Cardora</title><h1>Η σελίδα δεν είναι προσωρινά διαθέσιμη</h1><p>Δοκίμασε ξανά σε λίγο.</p></html>', {status:503, headers})
  }
  const type = response.headers.get('x-cardora-document-type')
  if (!['text/html; charset=utf-8', 'application/xml; charset=utf-8'].includes(type)) {
    await response.body?.cancel()
    throw new Error('Invalid origin document type')
  }
  headers.set('Content-Type', type)
  if (response.headers.has('x-robots-tag')) headers.set('X-Robots-Tag', response.headers.get('x-robots-tag'))
  if (mode === 'preview') headers.set('X-Robots-Tag', 'noindex, nofollow')
  return new Response(request.method === 'HEAD' ? null : response.body, {status: response.status, headers})
}
