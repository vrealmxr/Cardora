import assert from 'node:assert/strict'
import { writeFile } from 'node:fs/promises'
import { resolve } from 'node:path'

const output = process.env.CARDORA_PUBLIC_FIXTURES
if (!output) throw new Error('Set CARDORA_PUBLIC_FIXTURES')
const base = 'https://api.cardora.gr/index.php/api/'
const sold = [
  '2024-25-panini-prizm-euroleague-bk-ramunas-siskauskas-black-11-103-2',
  '2025-26-topps-8-bit-ballers-8b-18',
  '2024-25-panini-donruss-euroleague-bk-daniel-hackett-purple-6699-155',
  '2024-25-panini-prizm-euroleague-bk-ramunas-siskauskas-black-11-103-4',
]
const report = []
async function read(path, status = 200) {
  const response = await fetch(base + path, { credentials: 'omit', signal: AbortSignal.timeout(15000),
    headers: { Accept: 'application/json', 'User-Agent': 'Cardora-PublicRenderer/1.0 (+https://cardora.gr)' } })
  assert.equal(response.status, status, path)
  assert.match(response.headers.get('Cache-Control'), status === 422 ? /private/ : /no-store/, path)
  report.push({ path, status: response.status })
  return response.json()
}
for (const locale of ['el', 'en']) {
  const listings = {}
  for (const slug of [...sold, 'doublade', 'gift-of-immortality']) {
    const { data } = await read(`public-listings/${slug}?locale=${locale}`)
    assert.equal(data.slug, slug)
    if (sold.includes(slug)) { assert.equal(data.status, 'sold'); assert.equal(data.stock, 0) }
    else assert.ok(['active', 'published'].includes(data.status))
    for (const key of ['winning_bidder', 'buyer_id', 'orders', 'compliance_flags', 'attributes']) assert.ok(!(key in data))
    for (const key of ['email', 'phone', 'address', 'stripe_account_id']) assert.ok(!(key in data.seller))
    listings[slug] = data
  }
  await read(`public-listings/missing-qa-listing-20261023?locale=${locale}`, 404)
  await writeFile(resolve(output, `public-listings-${locale}.json`), JSON.stringify({ data: listings }, null, 2))
}
const paths = await read('public-listing-paths')
assert.ok(sold.every(slug => paths.data.some(row => row.slug === slug)))
assert.ok(paths.data.every(row => Object.keys(row).every(key => ['slug', 'updatedAt'].includes(key))))
await writeFile(resolve(output, 'public-listing-paths.json'), JSON.stringify(paths, null, 2))
await read('public-listings/doublade?locale=invalid', 422)
await writeFile(resolve(output, 'public-listings-http-audit.json'), JSON.stringify({ checkedAt: new Date().toISOString(), report, paths: paths.data.length }, null, 2))
console.log(JSON.stringify({ passed: report.length, publicPaths: paths.data.length }))
