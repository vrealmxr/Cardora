import { readFile, writeFile } from 'node:fs/promises'
import { resolve } from 'node:path'
import { renderPublicPage } from '../dist-ssr/entry-server.js'
import { buildDocument } from '../src/server/document.js'
import { normalizeTextTree } from '../src/utils/textEncoding.js'

const directory = process.env.CARDORA_PUBLIC_FIXTURES
if (!directory) throw new Error('Set CARDORA_PUBLIC_FIXTURES')
const tag = process.argv[2] || 'current'
if (!/^[a-z0-9-]+$/.test(tag)) throw new Error('Invalid benchmark label')
const fixtures = {}
for (const locale of ['el', 'en']) {
  const raw = JSON.parse(await readFile(resolve(directory, `public-page-data-${locale}.json`), 'utf8'))
  fixtures[locale] = normalizeTextTree(raw.data || raw)
}
const template = '<html lang="en"><head></head><body><div id="root"></div></body></html>'
const routes = ['/el/faq', '/en/epikoinonia', '/el/syllektika', '/en/kartes', '/el/kartes?franchise=Pokemon', '/en/proion/doublade']
const report = []
for (const route of routes) {
  const locale = route.startsWith('/en') ? 'en' : 'el'
  const data = fixtures[locale]
  await renderPublicPage(route, data)
  const runs = []
  for (let index = 0; index < 5; index++) {
    const cpu = process.cpuUsage(), start = performance.now()
    const rendered = await renderPublicPage(route, data)
    const html = buildDocument(template, rendered, { version: 1, path: route, publicData: data, seo: {} })
    const usage = process.cpuUsage(cpu)
    runs.push({ wallMs: +(performance.now() - start).toFixed(2), cpuMs: +((usage.user + usage.system) / 1000).toFixed(2), bytes: Buffer.byteLength(html) })
  }
  report.push({ route, runs, medianWallMs: [...runs].sort((a,b) => a.wallMs - b.wallMs)[2].wallMs })
}
await writeFile(resolve(directory, `render-benchmark-${tag}.json`), JSON.stringify({ environment: 'Local Node; not Cloudflare CPU entitlement or Core Web Vitals', report }, null, 2))
console.log(JSON.stringify(report.map(({ route, medianWallMs }) => ({ route, medianWallMs })), null, 2))
