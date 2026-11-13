import { PUBLIC_STATIC_PATHS } from './routePolicy.js'
import { escapeHtml } from './document.js'
import { SITE_ORIGIN } from '../utils/seoUrls.js'
import { mergeEditorialGuides } from '../data/editorialGuideIndex.js'

const LIMIT = 40000
export function sitemapPaths(data, tutorialSlugs) {
  const entries = PUBLIC_STATIC_PATHS.map((path) => ({ path }))
  for (const product of data.publicListingPaths ?? data.products) entries.push({ path: `proion/${product.slug}`, modified: product.updatedAt })
  for (const post of mergeEditorialGuides(data.blogPosts)) entries.push({ path: `blog/${post.slug}`, modified: post.updatedAt || post.publishedAt })
  for (const slug of tutorialSlugs) entries.push({ path: `tutorials/${slug}` })
  return [...new Map(entries.map((row) => [row.path, row])).values()]
    .flatMap((row) => ['el', 'en'].map((locale) => ({ ...row, url: `${SITE_ORIGIN}/${locale}${row.path ? '/' + row.path : ''}` })))
}

export function createSitemap(data, tutorialSlugs, page = null) {
  const entries = sitemapPaths(data, tutorialSlugs)
  const pages = Math.ceil(entries.length / LIMIT)
  const prefix = '<?xml version="1.0" encoding="UTF-8"?>\n'
  if (page === null && pages > 1) {
    return { status: 200, xml: prefix + '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' +
      Array.from({ length: pages }, (_, i) => `<sitemap><loc>${SITE_ORIGIN}/sitemap-${i + 1}.xml</loc></sitemap>`).join('') + '</sitemapindex>' }
  }
  if (page !== null && (!Number.isInteger(page) || page < 1 || page > pages)) return { status: 404, xml: '' }
  const chunk = entries.slice(((page || 1) - 1) * LIMIT, (page || 1) * LIMIT)
  const xml = chunk.map(({ url, modified }) => {
    const validDate = modified && !Number.isNaN(Date.parse(modified)) ? new Date(modified).toISOString() : null
    return `<url><loc>${escapeHtml(url)}</loc>${validDate ? `<lastmod>${validDate}</lastmod>` : ''}</url>`
  }).join('')
  return { status: 200, xml: prefix + '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' + xml + '</urlset>' }
}
