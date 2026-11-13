import test from 'node:test'
import assert from 'node:assert/strict'
import { createHmac } from 'node:crypto'
import { createPublicRenderSigner } from '../src/server/publicRenderSigner.js'
import { createPublicDataReader } from '../src/server/publicDataReader.js'
import { readPreviewResource } from '../src/server/previewResource.js'

const secret = 'a'.repeat(64)
const time = 1792717000
const request = ip => new Request('https://preview.pages.dev/en/kartes', { headers: { 'CF-Connecting-IP': ip, Cookie: 'private', Authorization: 'Bearer private' } })

test('signed public reads bind timestamp, resource and locale to a private stable visitor partition', async () => {
  const sign = createPublicRenderSigner(request('192.0.2.10'), secret, () => time * 1000)
  const headers = await sign('public-listings/%CE%BA%CE%AC%CF%81%CF%84%CE%B1', 'en')
  const visitor = createHmac('sha256', secret).update('cardora-public-visitor-v1\n192.0.2.10').digest('hex')
  assert.equal(headers['X-Cardora-Public-Visitor'], visitor)
  assert.equal(headers['X-Cardora-Public-Signature'], createHmac('sha256', secret).update(['v1', time, 'GET', 'public-listings/κάρτα', 'en', visitor].join('\n')).digest('hex'))
  assert.equal(headers['X-Cardora-Public-Time'], String(time))
  assert.ok(!JSON.stringify(headers).includes('192.0.2.10'))
  assert.equal(headers.Cookie, undefined); assert.equal(headers.Authorization, undefined)
  const second = await createPublicRenderSigner(request('192.0.2.11'), secret, () => time * 1000)('seo', 'en')
  assert.notEqual(second['X-Cardora-Public-Visitor'], visitor)
  assert.notEqual((await sign('public-listings/κάρτα', 'el'))['X-Cardora-Public-Signature'], headers['X-Cardora-Public-Signature'])
  for (const path of ['auth/me', 'scanner/scan', 'admin', 'orders', 'public-listings/card%2Fprivate']) await assert.rejects(sign(path, 'el'))
  assert.throws(() => createPublicRenderSigner(request('192.0.2.1'), ''), /not configured/)
})

test('origin read sends signed attribution without forwarding browser credentials or caching detail', async () => {
  const calls = []
  const read = createPublicDataReader({ fetchResource: async (url, options) => {
    calls.push({ url, options }); return Response.json({ data: { slug: 'card', status: 'sold' } })
  } })
  for (const ip of ['192.0.2.10', '192.0.2.11']) await read('public-listings/card', 'en', true, createPublicRenderSigner(request(ip), secret))
  assert.equal(calls.length, 2)
  assert.notEqual(calls[0].options.headers['X-Cardora-Public-Visitor'], calls[1].options.headers['X-Cardora-Public-Visitor'])
  for (const call of calls) { assert.equal(call.options.credentials, 'omit'); assert.equal(call.options.headers.Authorization, undefined); assert.equal(call.options.headers.Cookie, undefined) }
})

test('preview detail and profile reads use signed reader and preserve missing status', async () => {
  const paths = []
  for (const kind of ['profiles', 'public-listings']) {
    const response = await readPreviewResource(new Request(`https://preview.pages.dev/index.php/api/${kind}/missing?locale=en`),
      () => { throw new Error('Unsigned fetch must not be used') }, async (...args) => { paths.push(args); return null })
    assert.equal(response.status, 404)
    assert.deepEqual(await response.json(), { data: null })
  }
  assert.deepEqual(paths, [['profiles/missing', 'en', true], ['public-listings/missing', 'en', true]])
})

test('unclassified edge 429 has bounded retries with the same deadline and signed request', async () => {
  const calls = [], delays = []
  const read = createPublicDataReader({ pause: async ms => delays.push(ms), fetchResource: async (url, options) => {
    calls.push({url, options})
    return calls.length < 3 ? new Response(null, {status:429}) : Response.json({data:{slug:'card'}})
  } })
  assert.deepEqual(await read('public-listings/card', 'el', false, createPublicRenderSigner(request('192.0.2.1'), secret)), {slug:'card'})
  assert.equal(calls.length, 3)
  assert.ok(delays[0] >= 250 && delays[0] < 350 && delays[1] >= 500 && delays[1] < 600)
  assert.equal(calls[0].options, calls[2].options)
  assert.equal(calls[2].options.credentials, 'omit')
})

test('persistent edge failures stop; explicit throttles and other errors never retry', async () => {
  for (const [status, headers, attempts] of [
    [429, {}, 3], [429, {'Retry-After':'60'}, 1], [429, {'X-RateLimit-Limit':'90'}, 1],
    [429, {'CF-Mitigated':'challenge'}, 1], [403, {}, 1], [500, {}, 1],
  ]) {
    let calls = 0
    const read = createPublicDataReader({pause: async () => {}, fetchResource: async () => {
      calls++; return new Response(null, {status, headers})
    }})
    await assert.rejects(read('public-listings/card','el'), new RegExp(`unavailable \\(${status}\\)`))
    assert.equal(calls, attempts)
  }
})


