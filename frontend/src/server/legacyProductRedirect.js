// Only attached to api.cardora.gr/{el,en}/proion/*, never API endpoints.
export default {
  fetch(request) {
    const url = new URL(request.url)
    if (url.hostname !== 'api.cardora.gr' || !/^\/(?:el|en)\/proion\/[A-Za-z0-9_-]+\/?$/.test(url.pathname)) {
      return new Response('Not found', { status: 404 })
    }
    if (!['GET', 'HEAD'].includes(request.method)) {
      return new Response('Method not allowed', { status: 405, headers: { Allow: 'GET, HEAD' } })
    }
    // Fixed destination; discard query data from these obsolete public links.
    return new Response(null, { status: 301, headers: {
      Location: `https://cardora.gr${url.pathname.replace(/\/$/, '')}`,
      'Cache-Control': 'public, max-age=300',
    } })
  },
}
