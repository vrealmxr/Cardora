import fs from 'node:fs/promises'
import assert from 'node:assert/strict'

const base = process.argv[2]
const output = process.argv[3]
if (!/^https:\/\/[a-z0-9]+\.cardora-frontend\.pages\.dev$/.test(base || '') || !output) throw new Error('Provide a preview URL and evidence path')
const cases = [
  ['/en/kartes?productType=' + encodeURIComponent('Μεμονωμένες κάρτες'), ['Single Cards', '181 listings match']],
  ['/el/kartes?productType=' + encodeURIComponent('Μεμονωμένες κάρτες'), ['Μεμονωμένες κάρτες', '181 αγγελίες']],
  ['/en/blog/pos-na-apotimiseis-sosta-mia-karta-prin-ti-valeis-pros-polisi-stin-cardora', ["Start with the card&#x27;s exact identity", 'Look for completed sales']],
  ['/el/blog/pos-na-apotimiseis-sosta-mia-karta-prin-ti-valeis-pros-polisi-stin-cardora', ['Ξεκίνα από την ακριβή ταυτότητα της κάρτας']],
  ['/en/kliroseis', ['Trades']],
  ['/en/proion/gift-of-immortality', ['Single Cards', 'English']],
  ['/el/proion/gift-of-immortality', ['Μεμονωμένες κάρτες']],
  ['/en/proion/2025-26-topps-8-bit-ballers-8b-18', ['This item has been sold']],
]
const rows = []
for (const [path, fragments] of cases) {
  const at = new Date().toISOString()
  const response = await fetch(base + path, {headers:{'User-Agent':'Cardora-PublicRenderer/1.0 (+https://cardora.gr)'}, signal:AbortSignal.timeout(20000), redirect:'manual'})
  const body = await response.text()
  rows.push({path, at, status:response.status, requestRay:response.headers.get('cf-ray'),
    robots:response.headers.get('x-robots-tag'), h1:(body.match(/<h1[\s>]/g)||[]).length,
    hasInitialData:body.includes('id="cardora-public-data"'), checks:fragments.map(fragment=>({fragment, present:body.includes(fragment)})),
    canonical:body.match(/<link rel="canonical" href="([^"]+)/)?.[1]})
}
await fs.writeFile(output,JSON.stringify({base,rows},null,2))
console.log(JSON.stringify(rows,null,2))
assert.ok(rows.every(row=>row.status===200 && row.h1===1 && row.hasInitialData && row.robots?.includes('noindex') && row.checks.every(check=>check.present)))
