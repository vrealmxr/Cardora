// Preview deployments have no production proxy routes. Support anonymous public
// QA only; never forward credentials, enable writes, account APIs or scanner calls.
export async function readPreviewResource(request, fetchResource = fetch, readData) {
  const url = new URL(request.url)
  const path = url.pathname.replace(/^\/index\.php\/api\//, '')
  const locale = url.searchParams.get('locale') === 'en' || request.headers.get('X-Locale') === 'en' ? 'en' : 'el'
  let target
  if (readData && (['bootstrap', 'public-page-data', 'seo'].includes(path) || /^(profiles|public-listings)\/[^/?#]+$/.test(path))) {
    const data = await readData(path === 'bootstrap' ? 'public-page-data' : path, locale, true)
    return Response.json({ data }, {
      status: data === null ? 404 : 200,
      headers: { 'Cache-Control': 'no-store', 'X-Robots-Tag': 'noindex, nofollow' },
    })
  }
  if (['bootstrap', 'public-page-data', 'seo'].includes(path) || /^listings\/\d+$/.test(path) || /^(profiles|public-listings)\/[^/?#]+$/.test(path)) {
    target = `https://api.cardora.gr/index.php/api/${path === 'bootstrap' ? 'public-page-data' : path}?locale=${locale}`
  } else if (url.pathname === '/asset.php' || url.pathname.startsWith('/storage/')) {
    target = `https://api.cardora.gr${url.pathname}${url.search}`
  } else {
    return Response.json({ message: 'This preview supports anonymous browsing only.' }, { status: 403,
      headers: { 'Cache-Control': 'no-store', 'X-Robots-Tag': 'noindex, nofollow' } })
  }
  const upstream = await fetchResource(target, { method: request.method,
    headers: { Accept: 'application/json', 'X-Locale': locale, 'User-Agent': 'Cardora-PublicRenderer/1.0 (+https://cardora.gr)' },
    redirect: 'manual', signal: AbortSignal.timeout(8000) })
  if (upstream.status >= 300 && upstream.status < 400) throw new Error('Unexpected public origin redirect')
  return new Response(upstream.body, { status: upstream.status, headers: {
    'Content-Type': upstream.headers.get('Content-Type') || 'application/octet-stream',
    'Cache-Control': 'no-store', 'X-Content-Type-Options': 'nosniff', 'X-Robots-Tag': 'noindex, nofollow',
  } })
}
