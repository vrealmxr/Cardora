import { resolvePublicRoute } from './routePolicy.js'
import { buildDocument } from './document.js'
import { createSitemap } from './sitemap.js'
import { DEFAULT_SHARE_IMAGE, getSeoUrls } from '../utils/seoUrls.js'

export function createPublicHandler({ template, render, loadData, loadSeo, tutorialSlugs, previewResource, loadProfile, loadListing, loadListingPaths }) {
  return async function handle(request, env) {
    const url = new URL(request.url)
    const context = { request, env }
    const preview = !['cardora.gr', 'www.cardora.gr'].includes(url.hostname)
    // QA visits must not count as production analytics or start a support chat.
    const pageTemplate = preview ? template.replace(/<script>[\s\S]*?<\/script>/g, '')
      .replace(/<noscript>[\s\S]*?<\/noscript>/g, '') : template
    const response = (body, status, type = 'text/html; charset=utf-8', extra = {}) => new Response(
      request.method === 'HEAD' ? null : body,
      { status, headers: { 'Content-Type': type, 'Cache-Control': 'no-store',
        'X-Content-Type-Options': 'nosniff', 'X-Frame-Options': 'SAMEORIGIN',
        'Referrer-Policy': 'strict-origin-when-cross-origin',
        ...(preview ? { 'X-Robots-Tag': 'noindex, nofollow' } : {}), ...extra } },
    )
    if (!['GET', 'HEAD'].includes(request.method)) return response('Method not allowed', 405, 'text/plain', { Allow: 'GET, HEAD' })
    if (url.href.length > 4096) return response('URI too long', 414, 'text/plain')
    if (preview && previewResource && (/^\/index\.php\/api\//.test(url.pathname) || url.pathname === '/asset.php' || url.pathname.startsWith('/storage/'))) {
      try { return await previewResource(request, env) }
      catch { return response('Preview data unavailable', 503, 'text/plain', { 'Retry-After': '30' }) }
    }
    if (url.hostname === 'www.cardora.gr') return response('', 301, 'text/plain', { Location: `https://cardora.gr${url.pathname}${url.search}` })
    if (/^\/(static|icons|vendor|tutorials)\//.test(url.pathname) || /^\/[^/]+\.[a-z0-9]+$/i.test(url.pathname) && !/^\/sitemap(?:-\d+)?\.xml$/.test(url.pathname) && url.pathname !== '/robots.txt') {
      const asset = await env.ASSETS.fetch(request)
      if ((asset.headers.get('content-type') || '').includes('text/html')) return response('Not found', 404, 'text/plain')
      return asset
    }
    if (url.pathname === '/robots.txt') return response('User-agent: *\nAllow: /\n\nSitemap: https://cardora.gr/sitemap.xml\n', 200, 'text/plain; charset=utf-8')
    try {
      const sitemap = url.pathname.match(/^\/sitemap(?:-(\d+))?\.xml$/)
      if (sitemap) {
        const data = { ...await loadData('el', context), ...(loadListingPaths ? { publicListingPaths: await loadListingPaths(context) } : {}) }
        const result = createSitemap(data, tutorialSlugs, sitemap[1] ? Number(sitemap[1]) : null)
        return response(result.xml, result.status, 'application/xml; charset=utf-8')
      }
      const route = resolvePublicRoute(url.pathname)
      if (route.kind === 'invalid') return response('Invalid URL', route.status, 'text/plain')
      if (route.kind === 'redirect') return response('', 301, 'text/plain', { Location: route.target.split('/').map(encodeURIComponent).join('/') + url.search })
      const head = { title: 'Cardora', description: '', image: DEFAULT_SHARE_IMAGE, locale: route.locale,
        ...getSeoUrls(url.pathname, url.search, route.locale) }
      if (route.kind === 'client') {
        head.robots = 'noindex, follow'
        head.alternates = {}
        return response(buildDocument(pageTemplate, { head }), 200)
      }
      let [data, seo] = route.kind === 'notfound' ? [{}, {}] : await Promise.all([loadData(route.locale, context), loadSeo(route.locale, context)])
      if (route.slug.startsWith('sylloges/')) {
        const handle = route.slug.slice('sylloges/'.length)
        const profile = await loadProfile(handle, route.locale, context)
        data = { ...data, publicProfile: { handle, profile, status: profile ? 200 : 404 } }
      }
      if (route.slug.startsWith('proion/') && loadListing) {
        const slug = route.slug.slice('proion/'.length)
        const product = await loadListing(slug, route.locale, context)
        data = { ...data, publicListing: { slug, locale: route.locale, product, status: product ? 200 : 404 } }
      }
      const rendered = await render(url.pathname + url.search, data, seo)
      const status = route.status === 404 ? 404 : rendered.head.status || 200
      if (status === 404) { rendered.head.robots = 'noindex, follow'; rendered.head.alternates = {} }
      if (preview) rendered.head.robots = 'noindex, nofollow'
      const document = buildDocument(pageTemplate, rendered, {
        version: 1, path: url.pathname + url.search, publicData: data, seo,
      })
      return response(document, status)
    } catch (error) {
      console.error('Public rendering failed:', error?.message || 'unknown error')
      return response('<!doctype html><html lang="el"><meta charset="utf-8"><title>Cardora</title><h1>Η σελίδα δεν είναι προσωρινά διαθέσιμη</h1><p>Δοκίμασε ξανά σε λίγο.</p></html>',
        503, 'text/html; charset=utf-8', { 'Retry-After': '30', 'X-Robots-Tag': 'noindex' })
    }
  }
}
