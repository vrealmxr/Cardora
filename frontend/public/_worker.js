const ORIGIN_BASE_URL = 'https://api.cardora.gr'

const PROXY_PREFIXES = [
  '/api/',
  '/index.php/',
  '/storage/',
  '/icons/',
]

const PROXY_EXACT_PATHS = new Set([
  '/asset.php',
])

const shouldProxyToOrigin = (url) => {
  if (PROXY_EXACT_PATHS.has(url.pathname)) {
    return true
  }

  return PROXY_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))
}

const buildOriginRequest = (request) => {
  const requestUrl = new URL(request.url)
  const originUrl = new URL(requestUrl.pathname + requestUrl.search, ORIGIN_BASE_URL)

  const headers = new Headers(request.headers)
  headers.set('host', 'cardora.gr')
  headers.set('x-forwarded-host', requestUrl.host)
  headers.set('x-forwarded-proto', 'https')
  headers.set('x-forwarded-port', '443')

  return new Request(originUrl.toString(), {
    method: request.method,
    headers,
    body: ['GET', 'HEAD'].includes(request.method) ? undefined : request.body,
    redirect: 'manual',
  })
}

const proxyToOrigin = (request) => {
  return fetch(buildOriginRequest(request))
}

export default {
  async fetch(request, env) {
    const url = new URL(request.url)

    if (shouldProxyToOrigin(url)) {
      return proxyToOrigin(request)
    }

    return env.ASSETS.fetch(request)
  },
}
