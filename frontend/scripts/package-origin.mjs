import { readFile, writeFile, copyFile } from 'node:fs/promises'
import { createHash } from 'node:crypto'

await copyFile('scripts/origin-render-cli.mjs', 'dist-origin/origin-render-cli.mjs')
const files = {}
for (const file of ['renderer.mjs', 'origin-render-cli.mjs']) files[file] = createHash('sha256').update(await readFile(`dist-origin/${file}`)).digest('hex')
const release = createHash('sha256').update(JSON.stringify(files)).digest('hex').slice(0, 16)
await writeFile('dist-origin/release.json', JSON.stringify({release, files}, null, 2))
console.log(`Origin rendering release: ${release}`)
