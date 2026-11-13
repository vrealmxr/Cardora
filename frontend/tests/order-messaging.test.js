import test from 'node:test'
import assert from 'node:assert/strict'
import { JSDOM } from './runtime/node_modules/jsdom/lib/api.js'

test('order messaging passes the order identity, while listing inquiries retain listing identity', async () => {
  const dom = new JSDOM('<div id="root"></div>', { url: 'http://cardora.test/el' })
  globalThis.window = dom.window; globalThis.document = dom.window.document
  globalThis.CustomEvent = dom.window.CustomEvent; globalThis.IS_REACT_ACT_ENVIRONMENT = true
  const NativeMessageChannel = globalThis.MessageChannel; const channels = []
  globalThis.MessageChannel = class extends NativeMessageChannel { constructor() { super(); channels.push(this) } }
  const originalFetch = globalThis.fetch; const requests = []
  const fixture = { categories: [], products: [], users: [], blogPosts: [], conversations: [], cartItems: [] }
  globalThis.fetch = async (url, options) => {
    const path = new URL(url).pathname
    if (path.endsWith('/public-page-data') || path.endsWith('/bootstrap')) return Response.json({ data: fixture })
    if (path.endsWith('/auth/me')) return Response.json({ data: { id: 10, handle: 'seller-fixture' } })
    if (path.endsWith('/messages/conversations') && options.method === 'POST') {
      requests.push(JSON.parse(options.body))
      return Response.json({ data: { id: 77, listing_id: 318, buyer_id: 36, seller_id: 10 } }, { status: 201 })
    }
    throw new Error(`Unexpected request blocked: ${options.method} ${path}`)
  }
  const harness = await import('../dist-session-tests/session-harness.js'); const { act, getControls } = harness
  const flush = () => act(async () => { await new Promise(resolve => setTimeout(resolve, 0)) })
  let root
  try {
    const order = { id: 'CDR-20261029132106-FSWB', databaseId: 41, productId: 318, buyerId: 36, sellerId: 10,
      itemType: 'listing', statusKey: 'paid_pending_release', shipmentStatus: 'in_transit', title: 'Fixture book',
      totalAmount: 7.75, sellerAmount: 4, shippingCarrier: 'boxnow', orderedAt: '2026-10-29T13:21:06Z' }
    await act(async () => { root = harness.mount(document.getElementById('root'), order) }); await flush()
    await act(async () => getControls().auth.authenticateWithToken('qa-seller')); await flush()
    const button = [...document.querySelectorAll('button')].find(el => /Μήνυμα στον αγοραστή|Message buyer/.test(el.textContent))
    assert.ok(button, 'actual seller order card exposes the message buyer action')
    await act(async () => { button.click() }); await flush()
    assert.deepEqual(requests[0], { order_id: 41 })
    assert.equal(getControls().location.pathname, '/minymata')
    assert.equal(getControls().location.search, '?conversation=77')
    await act(async () => { await getControls().market.startConversationForOrder({ id: 42, buyerId: 36, sellerId: 10 }, '  hello  ') })
    assert.deepEqual(requests[1], { order_id: 42, initial_message: 'hello' })
    await act(async () => { await getControls().market.startConversationForListing(456) })
    assert.deepEqual(requests[2], { listing_id: 456 })
    await assert.rejects(() => getControls().market.startConversationForOrder({ id: 'CDR-invalid' }), /Ανανέωσε|refresh/)
    assert.equal(requests.length, 3, 'invalid identity never reaches the API as null')
  } finally {
    if (root) await act(async () => root.unmount())
    globalThis.fetch = originalFetch; dom.window.close()
    for (const channel of channels) { channel.port1.close(); channel.port2.close() }
    globalThis.MessageChannel = NativeMessageChannel
    delete globalThis.window; delete globalThis.document; delete globalThis.CustomEvent; delete globalThis.IS_REACT_ACT_ENVIRONMENT
  }
})
