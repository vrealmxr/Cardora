import assert from 'node:assert/strict'
import { writeFile } from 'node:fs/promises'
import { resolve } from 'node:path'

const base = process.env.CARDORA_PREVIEW_URL
const output = process.env.CARDORA_PUBLIC_FIXTURES
if (!base || !new URL(base).hostname.endsWith('.cardora-frontend.pages.dev') || !output) {
  throw new Error('Set CARDORA_PREVIEW_URL to a Cardora Pages preview and CARDORA_PUBLIC_FIXTURES for evidence')
}
const cases = [
  ['/el/syllektika', 200, 'text/html'], ['/en/syllektika?page=2', 200, 'text/html'],
  ['/en/odigos-polisis', 200, 'text/html'], ['/el/katastasi-kai-grading', 200, 'text/html'],
  ['/en/sylloges/ninjaniarf', 200, 'text/html'], ['/el/sylloges/missing-qa-profile-20261023', 404, 'text/html'],
  ['/el', 200, 'text/html'], ['/en/kartes?page=2', 200, 'text/html'],
  ['/el/faq', 200, 'text/html'], ['/en/epikoinonia', 200, 'text/html'],
  ['/en/proion/doublade', 200, 'text/html'],
  ['/el/not-a-page', 404, 'text/html'], ['/en/proion/missing-qa-item', 404, 'text/html'],
  ['/en/kartes?page=9999', 404, 'text/html'], ['/en/profil', 200, 'text/html'],
  ['/en/terms', 301, 'text/plain'], ['/robots.txt', 200, 'text/plain'],
  ['/sitemap.xml', 200, 'application/xml'], ['/static/missing-qa.js', 404, 'text/plain'],
  ['/index.php/api/public-page-data?locale=en', 200, 'application/json'],
  ...['2024-25-panini-prizm-euroleague-bk-ramunas-siskauskas-black-11-103-2',
    '2025-26-topps-8-bit-ballers-8b-18',
    '2024-25-panini-donruss-euroleague-bk-daniel-hackett-purple-6699-155',
    '2024-25-panini-prizm-euroleague-bk-ramunas-siskauskas-black-11-103-4']
    .flatMap(slug => ['el', 'en'].map(locale => [`/${locale}/proion/${slug}`, 200, 'text/html'])),
]
const report = []
for (const [path, status, type] of cases) {
  const start = performance.now()
  const response = await fetch(base + path, { redirect: 'manual', headers: { 'User-Agent': 'Cardora-PublicRenderer/1.0 (+https://cardora.gr)' } })
  const body = await response.text()
  const row = { path, status: response.status, type: response.headers.get('Content-Type'),
    robots: response.headers.get('X-Robots-Tag'), bytes: Buffer.byteLength(body), ms: Math.round(performance.now() - start),
    canonical: body.match(/<link rel="canonical" href="([^"]+)/)?.[1] }
  report.push(row)
  assert.equal(response.status, status, path)
  assert.ok(row.type.includes(type), path)
  if (type === 'text/html') {
    assert.ok(row.robots.includes('noindex'), path)
    assert.ok(!body.includes('googletagmanager.com'), 'Preview must not execute production GTM')
    assert.ok(!body.includes('app.chatwoot.com'), 'Preview must not open support chat')
    if (path === '/en/profil') assert.ok(!body.includes('cardora-public-data'))
    else assert.equal((body.match(/<h1[\s>]/g) || []).length, 1, path)
  }
  if (path === '/en/epikoinonia') {
    assert.ok(body.includes('mailto:support@cardora.gr'))
    assert.ok(!body.includes('Message sent'))
  }
  if (path.includes('/proion/') && status === 200 && path !== '/en/proion/doublade') {
    assert.ok(body.includes(path.startsWith('/el/') ? 'Το αντικείμενο έχει πουληθεί' : 'This item has been sold'), path)
    // Available recommendations retain their own purchase controls below the archived detail.
    const detail = body.split('<section class="mt-16">')[0]
    for (const button of detail.matchAll(/<button\b[^>]*>([\s\S]*?)<\/button>/g)) {
      const text = button[1].replace(/<[^>]+>/g, '')
      assert.ok(!/Add to cart|Buy now|Place bid|Send offer|Αγορά τώρα|Προσθήκη στο καλάθι|Υποβολή προσφοράς/i.test(text), text)
    }
    const snapshot = JSON.parse(body.match(/id="cardora-public-data">([\s\S]*?)<\/script>/)[1])
    const product = snapshot.publicData.publicListing.product
    const priceLabel = product.saleFormat === 'trade'
      ? (path.startsWith('/el/') ? 'Δηλωμένη αξία ανταλλαγής' : 'Listed trade value')
      : (path.startsWith('/el/') ? 'Τελευταία τιμή αγγελίας' : 'Last listing price')
    assert.ok(body.includes(priceLabel), path)
    if (product.saleFormat === 'fixed_price' && product.price > 0) assert.ok(body.includes('https://schema.org/SoldOut'), path)
    else {
      const schemas = [...body.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)].map(match => JSON.parse(match[1]))
      const productSchema = schemas.flat().find(item => item['@type'] === 'Product')
      assert.ok(productSchema && !productSchema.offers, 'Trades must not claim a cash sale offer')
    }
    assert.equal(product.status, 'sold')
    assert.equal(product.stock, 0)
    assert.ok(!snapshot.publicData.products.some(item => item.id === product.id))
    assert.ok(body.includes(`href="/${path.startsWith('/el/') ? 'el' : 'en'}/proion/`), 'Similar item links')
  }
  if (path === '/sitemap.xml') {
    for (const locale of ['el', 'en']) assert.ok(body.includes(`/${locale}/proion/2025-26-topps-8-bit-ballers-8b-18</loc>`))
  }
}
await writeFile(resolve(output, 'preview-http-audit.json'), JSON.stringify({ base, checkedAt: new Date().toISOString(), report }, null, 2))
console.log(JSON.stringify({ base, checksPassed: report.length, responses: report.map(({ path, status }) => ({ path, status })) }, null, 2))
