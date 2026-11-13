import { test } from 'node:test'
import assert from 'node:assert/strict'
import worker from '../src/server/legacyProductRedirect.js'

test('legacy product URLs redirect to a fixed canonical host without query data', () => {
  for (const locale of ['el', 'en']) for (const method of ['GET', 'HEAD']) {
    const response = worker.fetch(new Request(`https://api.cardora.gr/${locale}/proion/card-123/?token=discard`, { method }))
    assert.equal(response.status, 301)
    assert.equal(response.headers.get('Location'), `https://cardora.gr/${locale}/proion/card-123`)
  }
})
test('does not proxy API/private paths, other hosts, or write requests', () => {
  for (const url of ['https://api.cardora.gr/api/users', 'https://api.cardora.gr/en/proion/a/b', 'https://cardora.gr/en/proion/a', 'https://api.cardora.gr/en/account']) {
    assert.equal(worker.fetch(new Request(url)).status, 404)
  }
  assert.equal(worker.fetch(new Request('https://api.cardora.gr/en/proion/a', { method: 'POST' })).status, 405)
})
