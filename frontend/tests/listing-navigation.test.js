import test from 'node:test'
import assert from 'node:assert/strict'
import { JSDOM } from './runtime/node_modules/jsdom/lib/api.js'

test('public listing navigation ignores stale responses, separates locales and distinguishes 404 from retryable failure', async () => {
  const dom = new JSDOM('<div id="root"></div>', { url: 'http://cardora.test/en' })
  globalThis.window = dom.window
  globalThis.document = dom.window.document
  globalThis.IS_REACT_ACT_ENVIRONMENT = true
  const NativeChannel = globalThis.MessageChannel
  const channels = []
  globalThis.MessageChannel = class extends NativeChannel { constructor() { super(); channels.push(this) } }
  const originalFetch = globalThis.fetch
  const pending = []
  globalThis.fetch = (url, options) => new Promise(resolve => pending.push({ url: new URL(url), options, resolve }))
  let root
  try {
    const { act, mountListing, getListingControls } = await import('../dist-session-tests/session-harness.js')
    const seed = { slug: 'old-card', locale: 'en', status: 200, product: { slug: 'old-card', status: 'sold' } }
    await act(async () => { root = mountListing(document.getElementById('root'), seed) })
    assert.equal(getListingControls().result.product.status, 'sold')
    await act(async () => getListingControls().setRoute({ slug: 'new-card', locale: 'en' }))
    assert.equal(getListingControls().result, null)
    await act(async () => pending[1].resolve(Response.json({ data: { slug: 'new-card', status: 'published' } })))
    assert.equal(getListingControls().result.product.slug, 'new-card')
    await act(async () => pending[0].resolve(Response.json({ data: { slug: 'old-card', status: 'published' } })))
    assert.equal(getListingControls().result.product.slug, 'new-card')
    await act(async () => getListingControls().setRoute({ slug: 'new-card', locale: 'el' }))
    assert.equal(getListingControls().result, null)
    await act(async () => pending[2].resolve(Response.json({ data: null }, { status: 404 })))
    assert.equal(getListingControls().result.status, 404)
    assert.equal(getListingControls().result.product, null)
    await act(async () => getListingControls().retry())
    await act(async () => pending[3].resolve(Response.json({ message: 'outage' }, { status: 503 })))
    assert.equal(getListingControls().result.status, 503)
    await act(async () => getListingControls().retry())
    await act(async () => pending[4].resolve(Response.json({ data: { slug: 'new-card', status: 'sold' } })))
    assert.equal(getListingControls().result.product.status, 'sold')
    for (const { options, url } of pending) {
      assert.equal(options.credentials, 'omit')
      assert.equal(options.cache, 'no-store')
      assert.equal(options.headers.Authorization, undefined)
      assert.ok(url.pathname.includes('/public-listings/'))
    }
  } finally {
    if (root) { const { act } = await import('../dist-session-tests/session-harness.js'); await act(async () => root.unmount()) }
    globalThis.fetch = originalFetch
    globalThis.MessageChannel = NativeChannel
    for (const channel of channels) { channel.port1.close(); channel.port2.close() }
    dom.window.close()
    delete globalThis.window; delete globalThis.document; delete globalThis.IS_REACT_ACT_ENVIRONMENT
  }
})
