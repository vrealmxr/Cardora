const UPSTREAM_ORIGIN = 'http://origin-proxy.cardora.gr'

const withCors = (headers, request) => {
  const next = new Headers(headers)
  next.set('access-control-allow-origin', '*')
  next.set('access-control-allow-methods', 'GET,HEAD,POST,PUT,PATCH,DELETE,OPTIONS')
  next.set(
    'access-control-allow-headers',
    request.headers.get('access-control-request-headers') ?? '*',
  )

  return next
}

export default {
  async fetch(request) {
    if (request.method === 'OPTIONS') {
      return new Response(null, {
        status: 204,
        headers: withCors(new Headers(), request),
      })
    }

    const url = new URL(request.url)
    const upstreamUrl = new URL(`${url.pathname}${url.search}`, UPSTREAM_ORIGIN)

    const upstreamHeaders = new Headers(request.headers)
    upstreamHeaders.set('host', 'cardora.gr')
    upstreamHeaders.set('x-forwarded-host', url.host)
    upstreamHeaders.set('x-forwarded-proto', 'https')
    upstreamHeaders.set('x-forwarded-port', '443')

    try {
      const upstreamResponse = await fetch(upstreamUrl.toString(), {
        method: request.method,
        headers: upstreamHeaders,
        body: ['GET', 'HEAD'].includes(request.method) ? undefined : request.body,
        redirect: 'manual',
      })

      return new Response(upstreamResponse.body, {
        status: upstreamResponse.status,
        statusText: upstreamResponse.statusText,
        headers: withCors(upstreamResponse.headers, request),
      })
    } catch (error) {
      return new Response(`backend-proxy error: ${error instanceof Error ? error.message : String(error)}`, {
        status: 520,
        headers: withCors(new Headers({ 'content-type': 'text/plain; charset=UTF-8' }), request),
      })
    }
  },
}
