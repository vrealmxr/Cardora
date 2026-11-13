let cachedSecret, cachedKey
const encoder = new TextEncoder()
const hex = buffer => [...new Uint8Array(buffer)].map(byte => byte.toString(16).padStart(2, '0')).join('')
// Reject control characters as well as path/query separators before signing.
// eslint-disable-next-line no-control-regex
const publicPath = /^(?:public-page-data|seo|public-listing-paths|public-html\/[a-f0-9]{64}|(?:public-listings|profiles)\/[^/?#\x00-\x20\x7f]+)$/

// Attribution for anonymous reads only. These headers never authenticate an account.
export function createPublicRenderSigner(request, secret, now = () => Date.now()) {
  if (!/^[a-f0-9]{64}$/.test(secret || '')) throw new Error('Public renderer signing is not configured')
  if (cachedSecret !== secret) {
    cachedSecret = secret
    cachedKey = crypto.subtle.importKey('raw', encoder.encode(secret), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign'])
  }
  const key = cachedKey
  const sign = async text => hex(await crypto.subtle.sign('HMAC', await key, encoder.encode(text)))
  // The edge supplies CF-Connecting-IP. Never accept client-chosen forwarded-IP headers.
  const visitor = sign(`cardora-public-visitor-v1\n${request.headers.get('CF-Connecting-IP') || 'unattributed'}`)
  return async (path, locale) => {
    const resource = decodeURIComponent(path)
    if (!publicPath.test(resource) || !['el', 'en'].includes(locale)) throw new Error('Cannot sign non-public resource')
    const timestamp = String(Math.floor(now() / 1000))
    const identity = await visitor
    const signature = await sign(['v1', timestamp, 'GET', resource, locale, identity].join('\n'))
    return { 'X-Cardora-Public-Visitor': identity, 'X-Cardora-Public-Time': timestamp, 'X-Cardora-Public-Signature': signature }
  }
}
