import { writeFile } from 'node:fs/promises'
import { resolve } from 'node:path'
import { editorialGuideIndex } from '../src/data/editorialGuideIndex.js'
import { setTimeout as pause } from 'node:timers/promises'

const base = process.env.CARDORA_PREVIEW_URL
const directory = process.env.CARDORA_PUBLIC_FIXTURES
const tag = process.argv[2] || 'current'
if (!base || !(new URL(base).hostname.endsWith('.cardora-frontend.pages.dev') || ['https://cardora.gr/__cardora_release_check', 'https://cardora.gr'].includes(base)) || !directory || !/^[a-z0-9-]+$/.test(tag)) throw new Error('Set an approved Cardora QA URL and evidence directory')
const production = base === 'https://cardora.gr'
const routes = [
  '/el', '/en', '/el/kartes', '/en/kartes?page=2', '/el/kartes?franchise=Pokemon',
  '/el/kartes?q=Sapphire', '/en/anazitisi?q=Magic', '/el/anazitisi?q=Topps',
  '/el/figoures', '/en/komik-vivlia', '/el/diafora', '/en/syllektika?page=2',
  '/el/proion/gift-of-immortality', '/en/proion/doublade', '/en/sylloges/ninjaniarf',
  ...['2024-25-panini-prizm-euroleague-bk-ramunas-siskauskas-black-11-103-2',
    '2025-26-topps-8-bit-ballers-8b-18',
    '2024-25-panini-donruss-euroleague-bk-daniel-hackett-purple-6699-155',
    '2024-25-panini-prizm-euroleague-bk-ramunas-siskauskas-black-11-103-4']
    .flatMap(slug => ['el', 'en'].map(locale => `/${locale}/proion/${slug}`)),
  ...editorialGuideIndex.flatMap(guide => ['el', 'en'].map(locale => `/${locale}/blog/${guide.slug}`)),
  ...['politiki-aporritou', 'politiki-cookies', 'epistrofes-kai-diafores', 'apagorevmena-antikeimena']
    .flatMap(path => ['el', 'en'].map(locale => `/${locale}/${path}`)),
]
const rows = []
// A bounded three-reader diagnostic, not a capacity or load-test claim.
for (let round = 0; round < 2; round++) {
  for (let start = 0; start < routes.length; start += 3) {
    const batch = await Promise.all(routes.slice(start, start + 3).map(async path => {
      const began = performance.now()
      try {
        const response = await fetch(base + path, { signal: AbortSignal.timeout(20000), headers: { 'User-Agent': 'Cardora-PublicRenderer/1.0 (+https://cardora.gr)' } })
        const body = await response.text()
        const schemaErrors = []
        for (const match of body.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)) {
          try { JSON.parse(match[1]) } catch { schemaErrors.push('Invalid JSON-LD') }
        }
        return { round, path, status: response.status, ms: Math.round(performance.now() - began), bytes: Buffer.byteLength(body),
          h1Count: (body.match(/<h1[\s>]/g) || []).length, schemaErrors,
          cacheControl: response.headers.get('Cache-Control'), robots: response.headers.get('X-Robots-Tag'),
          ...(response.status !== 200 ? { errorExcerpt: body.slice(0, 500) } : {}) }
      } catch (error) { return { round, path, status: 0, error: error.message, ms: Math.round(performance.now() - began) } }
    }))
    rows.push(...batch)
    // Respect the origin's per-minute read limit; this is functional QA, not a load test.
    await pause(5000)
  }
}
const failures = rows.filter(row => row.status !== 200 || row.h1Count !== 1 || row.schemaErrors?.length || row.cacheControl !== 'no-store' || (!production && !row.robots?.includes('noindex')))
await writeFile(resolve(directory, `preview-mixed-${tag}.json`), JSON.stringify({ base, checkedAt: new Date().toISOString(), concurrency: 3, rows, failures }, null, 2))
console.log(JSON.stringify({ base, requests: rows.length, successes: rows.length - failures.length, failures: failures.map(({ path, status, error }) => ({ path, status, error })) }))
if (failures.length) process.exitCode = 1
