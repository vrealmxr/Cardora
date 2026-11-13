import template from '../../dist/index.html?raw'
import { renderPublicPage } from '../entry-server'
import { createPublicHandler } from './publicHandler'
import { tutorials } from '../data/tutorials'
import { normalizeTextTree } from '../utils/textEncoding'
import { sanitizePublicProfile } from '../utils/publicProfile'

// Receives only the anonymous DTO assembled by the signed PHP gateway.
// This process has no network transport and never receives browser credentials.
export async function renderOrigin(input) {
  if (input.version !== 1 || !['preview', 'production'].includes(input.mode)
    || typeof input.path !== 'string' || !input.path.startsWith('/') || input.path.startsWith('//')
    || input.path.length > 4096) throw new Error('Invalid rendering envelope')
  const data = normalizeTextTree(input.data || {})
  const handle = createPublicHandler({ template, render: renderPublicPage,
    tutorialSlugs: tutorials.map(item => item.slug),
    loadData: async () => data,
    loadSeo: async () => normalizeTextTree(input.seo || {}),
    loadListing: async () => normalizeTextTree(input.listing ?? null),
    loadProfile: async () => sanitizePublicProfile(normalizeTextTree(input.profile ?? null)),
    loadListingPaths: async () => input.listingPaths || [],
  })
  const host = input.mode === 'production' ? 'cardora.gr' : 'origin-preview.cardora-frontend.pages.dev'
  const response = await handle(new Request(`https://${host}${input.path}`), {})
  return { status: response.status, headers: Object.fromEntries(response.headers), body: await response.text() }
}
