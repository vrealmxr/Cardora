import fs from 'node:fs/promises'
import { renderPublicPage } from '../dist-ssr/entry-server.js'
import { PUBLIC_STATIC_PATHS } from '../src/server/routePolicy.js'
import { JSDOM } from '../tests/runtime/node_modules/jsdom/lib/api.js'
import { editorialGuideIndex } from '../src/data/editorialGuideIndex.js'
import { tutorials } from '../src/data/tutorials.js'
import { normalizeTextTree } from '../src/utils/textEncoding.js'

const output = process.argv[2]
if (!output) throw new Error('Provide an evidence JSON path')
const rows = []
for (const locale of ['el', 'en']) {
  const response = await fetch(`https://cardora.gr/index.php/api/public-page-data?locale=${locale}`, { headers: { 'User-Agent': 'Cardora-public-locale-QA/1.0' }, signal: AbortSignal.timeout(30000) })
  if (!response.ok) throw new Error(`Public data ${locale}: ${response.status}`)
  const { data: rawData } = await response.json()
  const data = normalizeTextTree(rawData)
  const seoResponse = await fetch(`https://cardora.gr/index.php/api/seo?locale=${locale}`, { headers: { 'User-Agent': 'Cardora-public-locale-QA/1.0' }, signal: AbortSignal.timeout(30000) })
  if (!seoResponse.ok) throw new Error(`Public SEO ${locale}: ${seoResponse.status}`)
  const seoJson = await seoResponse.json()
  const seo = normalizeTextTree(seoJson.data ?? seoJson)
  const paths = [...new Set([...PUBLIC_STATIC_PATHS,
    ...[...(data.blogPosts || []), ...editorialGuideIndex].map(post => `blog/${post.slug}`),
    ...tutorials.map(tutorial => `tutorials/${tutorial.slug}`),
  ])]
  for (const slug of paths) {
    const path = `/${locale}${slug ? '/' + slug : ''}`
    const { html, head } = await renderPublicPage(path, data, seo)
    const dom = new JSDOM(html)
    const doc = dom.window.document
    const texts = []
    const walker = doc.createTreeWalker(doc.body, 4)
    while (walker.nextNode()) {
      const node = walker.currentNode
      if (!node.parentElement?.closest('script,style') && /[\u0370-\u03ff]/u.test(node.textContent)) texts.push(node.textContent.trim())
    }
    rows.push({ path, status: head.status || 200, h1: [...doc.querySelectorAll('h1')].map(n=>n.textContent),
      canonical: head.canonical, alternates: head.alternates,
      unprefixedInternalLinks: [...new Set([...doc.querySelectorAll('a[href]')].map(a=>a.getAttribute('href')).filter(href=>/^\/(?!el(?:\/|$)|en(?:\/|$)|storage\/|asset.php)/.test(href) && !/^\/tutorials\/[^?#]+\.(?:webp|png|jpe?g)$/i.test(href)))],
      greekTextForReview: locale === 'en' ? [...new Set(texts)] : [],
    })
    dom.window.close()
  }
}
await fs.writeFile(output, JSON.stringify({ checkedAt:new Date().toISOString(), method:'Local SSR with current anonymous public data; not live route status or automatic translation quality certification', rows }, null, 2))
console.log(JSON.stringify(rows.map(({path,status,h1,unprefixedInternalLinks,greekTextForReview})=>({path,status,h1Count:h1.length,unprefixedInternalLinks,greekFragmentCount:greekTextForReview.length})),null,2))
