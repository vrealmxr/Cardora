import { renderOrigin } from './renderer.mjs'
import process from 'node:process'
import { Buffer } from 'node:buffer'

// No daemon, public listener, model, or network access. PHP owns this child process.
globalThis.fetch = () => { throw new Error('Network access is forbidden in the public renderer') }
let bytes = 0
const chunks = []
try {
  for await (const chunk of process.stdin) {
    bytes += chunk.length
    if (bytes > 8 * 1024 * 1024) throw new Error('Rendering input too large')
    chunks.push(chunk)
  }
  const result = await renderOrigin(JSON.parse(Buffer.concat(chunks).toString('utf8')))
  process.stdout.write(JSON.stringify(result))
} catch {
  process.stderr.write('Public rendering failed\n')
  process.exitCode = 1
}
