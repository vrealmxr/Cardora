import { normalizeTextTree } from '../utils/textEncoding.js'

const API = 'https://api.cardora.gr/index.php/api'
const TTL = { 'public-page-data': 30, seo: 300 }
const wait = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds))

// Retry only a transient, unclassified edge 429. An explicit server cooldown or
// application quota must reach the caller, not be multiplied by retries.
async function fetchPublicResource(fetchResource, url, options, pause) {
  for (let attempt = 0; ; attempt++) {
    const response = await fetchResource(url, options)
    if (response.status !== 429 || attempt === 2 || options.signal.aborted
      || response.headers.has('retry-after') || response.headers.has('x-ratelimit-limit')
      || response.headers.has('cf-mitigated')) return response
    console.warn('Transient public upstream 429; bounded retry', { attempt: attempt + 1, ray: response.headers.get('cf-ray')?.slice(0, 80) ?? null })
    await response.body?.cancel()
    await pause(250 * 2 ** attempt + Math.floor(Math.random() * 100))
    options.signal.throwIfAborted()
  }
}

// Cache only the dedicated anonymous allowlist and public SEO configuration.
// Profile visibility is checked on every request; accounts and HTML are never cached.
export function createPublicDataReader({ fetchResource = fetch, cache = () => null, pause = wait } = {}) {
  const pending = new Map()
  return async function read(path, locale, allowMissing = false, authorize) {
    if (!Object.hasOwn(TTL, path) && path !== 'public-listing-paths' && !/^(profiles|public-listings)\/[^/?#]+$/.test(path)) throw new Error('Not a public resource')
    const language = locale === 'en' ? 'en' : 'el'
    const ttl = TTL[path]
    const key = new Request(`https://cardora.gr/__public-render-data/v1/${path}?locale=${language}`)
    const storage = ttl ? cache() : null
    if (storage) {
      const cached = await storage.match(key)
      if (cached) return cached.json()
    }
    const load = async () => {
      const response = await fetchPublicResource(fetchResource, `${API}/${path}?locale=${language}`, {
        headers: { Accept: 'application/json', 'X-Locale': language, 'User-Agent': 'Cardora-PublicRenderer/1.0 (+https://cardora.gr)', ...(authorize ? await authorize(path, language) : {}) },
        signal: AbortSignal.timeout(8000), redirect: 'manual', credentials: 'omit',
      }, pause)
      if (allowMissing && response.status === 404) return null
      if (!response.ok) {
        // Server logs only: distinguish application throttling from edge/hosting limits without logging visitor data.
        const diagnostic = Object.fromEntries(['x-ratelimit-limit', 'x-ratelimit-remaining', 'retry-after', 'server', 'content-type', 'cf-mitigated', 'cf-ray', 'cf-cache-status']
          .map(name => [name, response.headers.get(name)?.slice(0, 80) ?? null]))
        throw new Error(`Public ${path.split('/')[0]} unavailable (${response.status}); upstream=${JSON.stringify(diagnostic)}`)
      }
      const payload = await response.json()
      if (!payload?.data || typeof payload.data !== 'object') throw new Error('Invalid public data response')
      const data = normalizeTextTree(payload.data)
      if (path === 'public-page-data' && (!Array.isArray(data.categories) || !Array.isArray(data.products))) throw new Error('Invalid public catalog')
      if (storage) await storage.put(key, Response.json(data, { headers: { 'Cache-Control': `public, max-age=${ttl}` } }))
      return data
    }
    if (!ttl) return load()
    // Collapse simultaneous misses inside this worker; Cache API itself does not.
    if (!pending.has(key.url)) pending.set(key.url, load().finally(() => pending.delete(key.url)))
    // Callers cannot mutate another request's snapshot.
    return structuredClone(await pending.get(key.url))
  }
}
