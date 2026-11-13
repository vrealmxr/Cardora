import template from '../dist/index.html?raw'
import { renderPublicPage } from './entry-server'
import { createPublicHandler } from './server/publicHandler'
import { createPublicDataReader } from './server/publicDataReader'
import { tutorials } from './data/tutorials'
import { readPreviewResource } from './server/previewResource'

import { sanitizePublicProfile } from './utils/publicProfile'
import { createPublicRenderSigner } from './server/publicRenderSigner'

const readPublicData = createPublicDataReader({ cache: () => caches.default })
const signerFor = context => context.signer ??= createPublicRenderSigner(context.request, context.env.PUBLIC_RENDER_SIGNING_KEY)
const read = (path, locale, missing, context) => readPublicData(path, locale, missing, signerFor(context))
const handle = createPublicHandler({
  template, render: renderPublicPage, tutorialSlugs: tutorials.map((item) => item.slug),
  previewResource: (request, env) => {
    const context = { request, env }
    return readPreviewResource(request, fetch, (path, locale, missing) => read(path, locale, missing, context))
  },
  loadData: async (locale, context) => {
    const data = await read('public-page-data', locale, false, context)
    if (!Array.isArray(data.categories) || !Array.isArray(data.products)) throw new Error('Invalid public catalog')
    return data
  },
  loadProfile: async (handle, locale, context) => sanitizePublicProfile(await read(`profiles/${encodeURIComponent(handle)}`, locale, true, context)),
  loadListing: (slug, locale, context) => read(`public-listings/${encodeURIComponent(slug)}`, locale, true, context),
  loadListingPaths: context => read('public-listing-paths', 'el', false, context),
  loadSeo: (locale, context) => read('seo', locale, false, context),
})
export default { fetch: handle }
