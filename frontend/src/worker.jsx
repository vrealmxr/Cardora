/* global __PUBLIC_RENDER_RELEASE__ */
import template from '../dist/index.html?raw'
import giveawayTerms from './data/giveaway-december-2026.html?raw'
import giveawayTermsEn from './data/giveaway-december-2026.en.html?raw'
import { createPublicHandler } from './server/publicHandler'
import { createPublicDataReader } from './server/publicDataReader'
import { readPreviewResource } from './server/previewResource'

import { createPublicRenderSigner } from './server/publicRenderSigner'
import { resolvePublicRoute } from './server/routePolicy'
import { requestOriginPage } from './server/originTransport'

const readPublicData = createPublicDataReader({ cache: () => caches.default })
const signerFor = context => context.signer ??= createPublicRenderSigner(context.request, context.env.PUBLIC_RENDER_SIGNING_KEY)
const read = (path, locale, missing, context) => readPublicData(path, locale, missing, signerFor(context))
const handle = createPublicHandler({
  template, render: () => { throw new Error('Public HTML belongs to the origin renderer') }, tutorialSlugs: [],
  previewResource: (request, env) => {
    const context = { request, env }
    return readPreviewResource(request, fetch, (path, locale, missing) => read(path, locale, missing, context))
  },
})
export default { async fetch(request, env) {
  const url = new URL(request.url)
  if (/^\/(el|en)\/oroi-giveaway-dekemvrios-2026$/.test(url.pathname) && url.hostname !== 'www.cardora.gr') {
    if (!['GET', 'HEAD'].includes(request.method)) return new Response(null, { status: 405, headers: { Allow: 'GET, HEAD' } })
    return new Response(request.method === 'HEAD' ? null : (url.pathname.startsWith('/en/') ? giveawayTermsEn : giveawayTerms), { headers: {
      'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store', 'X-Robots-Tag': 'noindex, follow',
      'X-Content-Type-Options': 'nosniff', 'Referrer-Policy': 'strict-origin-when-cross-origin',
    } })
  }
  const asset = /^\/(static|icons|vendor|tutorials)\//.test(url.pathname)
    || (/^\/[^/]+\.[a-z0-9]+$/i.test(url.pathname) && !/^\/sitemap(?:-\d+)?\.xml$/.test(url.pathname))
  const resource = /^\/index\.php\/api\//.test(url.pathname) || url.pathname === '/asset.php' || url.pathname.startsWith('/storage/')
  const route = resolvePublicRoute(url.pathname)
  if (['GET', 'HEAD'].includes(request.method) && url.href.length <= 4096 && url.hostname !== 'www.cardora.gr'
    && !asset && !resource && (['public', 'notfound'].includes(route.kind) || /^\/sitemap(?:-\d+)?\.xml$/.test(url.pathname))) {
    try {
      // Build-time release binds HTML, scripts and styles to the exact same build.
      return await requestOriginPage(request, {...env, PUBLIC_RENDER_RELEASE: __PUBLIC_RENDER_RELEASE__})
    } catch {
      return new Response('Η σελίδα δεν είναι προσωρινά διαθέσιμη. Δοκίμασε ξανά σε λίγο.', {
        status:503, headers:{'Content-Type':'text/plain; charset=utf-8','Cache-Control':'no-store','Retry-After':'30','X-Robots-Tag':'noindex'},
      })
    }
  }
  return handle(request, env)
} }
