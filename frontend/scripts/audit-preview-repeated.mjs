import { writeFile } from 'node:fs/promises'
import { resolve } from 'node:path'

const base = process.env.CARDORA_PREVIEW_URL
const directory = process.env.CARDORA_PUBLIC_FIXTURES
const tag = process.argv[2] || 'current'
if (!base || !new URL(base).hostname.endsWith('.cardora-frontend.pages.dev') || !directory || !/^[a-z0-9-]+$/.test(tag)) throw new Error('Set a Cardora preview URL and evidence directory')
const paths = ['/el/faq', '/en/epikoinonia', '/el/syllektika', '/en/odigos-polisis']
const rows = []
for (let index = 0; index < 40; index++) {
  const path = paths[index % paths.length], started = performance.now()
  try {
    const response = await fetch(base + path, { signal: AbortSignal.timeout(20000), headers: { 'User-Agent': 'Cardora-PublicRenderer/1.0 (+https://cardora.gr)' } })
    const body = await response.text()
    rows.push({ path, status: response.status, ms: Math.round(performance.now() - started), bytes: Buffer.byteLength(body), cacheControl: response.headers.get('Cache-Control'),
      ...(response.status !== 200 ? { errorExcerpt: body.slice(0, 500) } : {}) })
  } catch (error) { rows.push({ path, status: 0, error: error.message, ms: Math.round(performance.now() - started) }) }
}
const summary = { base, checkedAt: new Date().toISOString(), requests: rows.length, successes: rows.filter(row => row.status === 200).length, rows }
await writeFile(resolve(directory, `preview-repeat-${tag}.json`), JSON.stringify(summary, null, 2))
console.log(JSON.stringify({ base, requests: summary.requests, successes: summary.successes, statuses: [...new Set(rows.map(row => row.status))] }))
if (summary.successes !== summary.requests || rows.some(row => row.cacheControl !== 'no-store')) process.exitCode = 1
