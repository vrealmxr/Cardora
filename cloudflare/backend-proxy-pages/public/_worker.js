// Fetching plain http://origin-proxy.cardora.gr used to trigger the
// origin's force-HTTPS redirect (LiteSpeed 301 http -> https://cardora.gr/...).
// Since this worker forwards the response with redirect: 'manual' instead of
// following it, the browser followed that 301 back to https://cardora.gr,
// re-entering this same Pages project and repeating — Cloudflare eventually
// cuts the chain with "Error 1019: Worker hit loop limit" once it hits 16
// hops. Fetching https://cardora.gr with cf.resolveOverride pointed at the
// grey-clouded origin-proxy host avoids the redirect entirely (matches the
// working pattern already used in cloudflare/backend-proxy/src/worker.js).
const UPSTREAM_ORIGIN = 'https://cardora.gr'
const RESOLVE_OVERRIDE = 'origin-proxy.cardora.gr'

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
        cf: {
          resolveOverride: RESOLVE_OVERRIDE,
        },
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
