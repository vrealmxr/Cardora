const UPSTREAM_ORIGIN = 'https://cardora.gr'
const RESOLVE_OVERRIDE = 'origin-proxy.cardora.gr'

const copyHeaders = (headers) => {
  const next = new Headers(headers)
  next.set('x-forwarded-proto', 'https')
  next.set('x-forwarded-port', '443')
  return next
}

export default {
  async fetch(request) {
    const incomingUrl = new URL(request.url)
    const upstreamUrl = new URL(
      `${incomingUrl.pathname}${incomingUrl.search}`,
      UPSTREAM_ORIGIN,
    )

    const upstreamHeaders = copyHeaders(request.headers)
    upstreamHeaders.set('host', 'cardora.gr')
    upstreamHeaders.set('x-forwarded-host', incomingUrl.host)

    const response = await fetch(upstreamUrl.toString(), {
      method: request.method,
      headers: upstreamHeaders,
      body: ['GET', 'HEAD'].includes(request.method) ? undefined : request.body,
      redirect: 'manual',
      cf: {
        resolveOverride: RESOLVE_OVERRIDE,
      },
    })

    const nextHeaders = new Headers(response.headers)
    nextHeaders.set('access-control-allow-origin', '*')
    nextHeaders.set('access-control-allow-methods', 'GET,HEAD,POST,PUT,PATCH,DELETE,OPTIONS')
    nextHeaders.set('access-control-allow-headers', request.headers.get('access-control-request-headers') ?? '*')

    if (request.method === 'OPTIONS') {
      return new Response(null, {
        status: 204,
        headers: nextHeaders,
      })
    }

    return new Response(response.body, {
      status: response.status,
      statusText: response.statusText,
      headers: nextHeaders,
    })
  },
}
