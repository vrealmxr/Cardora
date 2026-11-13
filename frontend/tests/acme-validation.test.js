import { test } from 'node:test'
import assert from 'node:assert/strict'
import worker from '../src/server/acmeValidation.js'

test('ACME endpoint rejects non-token paths, foreign hosts and writes before fetching', async () => {
  for (const url of ['https://cardora.gr/api/users', 'https://cardora.gr/.well-known/acme-challenge/a/b', 'https://other.test/.well-known/acme-challenge/token']) {
    assert.equal((await worker.fetch(new Request(url))).status, 404)
  }
  assert.equal((await worker.fetch(new Request('https://cardora.gr/.well-known/acme-challenge/token', { method: 'POST' }))).status, 404)
})
test('only public token path and fixed headers reach the origin; upstream redirects are rejected', async (t) => {
  let args
  t.mock.method(globalThis, 'fetch', async (...input) => {
    args = input
    return new Response('public-token')
  })
  const response = await worker.fetch(new Request('https://www.cardora.gr/.well-known/acme-challenge/token?secret=discard', {
    headers: { Authorization: 'Bearer discard', Cookie: 'discard=1' },
  }))
  assert.equal(await response.text(), 'public-token')
  assert.equal(args[0], 'http://cardora.gr/.well-known/acme-challenge/token')
  assert.deepEqual(Object.keys(args[1].headers).sort(), ['Host', 'User-Agent'])
  assert.equal(args[1].redirect, 'manual')
  assert.equal(response.headers.get('Cache-Control'), 'no-store')
  globalThis.fetch.mock.mockImplementation(async () => new Response(null, { status: 302, headers: { Location: '/private' } }))
  assert.equal((await worker.fetch(new Request('https://cardora.gr/.well-known/acme-challenge/token'))).status, 404)
})
