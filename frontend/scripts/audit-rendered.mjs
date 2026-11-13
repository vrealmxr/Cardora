import { readFile, writeFile } from 'node:fs/promises'
import { resolve } from 'node:path'
import { renderPublicPage } from '../dist-ssr/entry-server.js'
import { normalizeTextTree } from '../src/utils/textEncoding.js'
import { PUBLIC_STATIC_PATHS } from '../src/server/routePolicy.js'
import { editorialGuideIndex } from '../src/data/editorialGuideIndex.js'

const directory = process.env.CARDORA_PUBLIC_FIXTURES
if (!directory) throw new Error('Set CARDORA_PUBLIC_FIXTURES')
const report = []
for (const locale of ['el', 'en']) {
  const read = async (name) => {
    const json = JSON.parse(await readFile(resolve(directory, `${name}-${locale}.json`), 'utf8'))
    return normalizeTextTree(json.data ?? json)
  }
  const data = await read('public-page-data')
  const seo = await read('seo')
  const paths = [...PUBLIC_STATIC_PATHS, `proion/${data.products[0].slug}`, `blog/${data.blogPosts[0].slug}`, 'kartes?page=2', 'kartes?page=999', 'proion/missing-qa-item', 'anazitisi?q=Magic', 'anazitisi?q=not-found-qa', 'anazitisi?page=999']
  paths.push(...editorialGuideIndex.map(guide => `blog/${guide.slug}`))
  for (const path of paths) {
    const url = `/${locale}${path ? '/' + path : ''}`
    try {
      const started = performance.now()
      const result = await renderPublicPage(url, data, seo)
      report.push({ url, status: result.head.status || 200, title: result.head.title,
        h1Count: (result.html.match(/<h1[\s>]/g) || []).length, bytes: Buffer.byteLength(result.html),
        ms: Math.round(performance.now() - started), canonical: result.head.canonical })
    } catch (error) { report.push({ url, error: error.message }) }
  }
}
await writeFile(resolve(directory, 'rendered-route-audit.json'), JSON.stringify(report, null, 2))
console.log(JSON.stringify({ routes: report.length, errors: report.filter(row => row.error),
  missingH1: report.filter(row => row.h1Count !== 1), maxRenderMs: Math.max(...report.map(row => row.ms || 0)) }, null, 2))
if (report.some(row => row.error)) process.exitCode = 1
