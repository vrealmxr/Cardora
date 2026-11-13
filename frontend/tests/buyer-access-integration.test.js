import test from 'node:test'
import assert from 'node:assert/strict'
import { JSDOM } from './runtime/node_modules/jsdom/lib/api.js'

test('real providers permit unverified cart and checkout and bid while preserving seller and raffle gates', async () => {
  const dom = new JSDOM('<div id="root"></div>', { url: 'http://cardora.test/el' })
  globalThis.window = dom.window; globalThis.document = dom.window.document
  globalThis.CustomEvent = dom.window.CustomEvent; globalThis.IS_REACT_ACT_ENVIRONMENT = true
  const NativeMessageChannel = globalThis.MessageChannel; const channels = []
  globalThis.MessageChannel = class extends NativeMessageChannel { constructor() { super(); channels.push(this) } }
  const originalFetch = globalThis.fetch
  const requests = []
  const access = { can_buy: true, can_checkout: true, can_sell: false, can_create_listing: false,
    can_bid: true, can_join_draws: false, is_marketplace_ready: false,
    blocking_message: 'Seller verification required', participation_blocking_message: 'Participation verification required' }
  const fixture = { categories: [], products: [{ id: 11, title: 'Fixture card', stock: 3, price: 10,
    sellerId: 22, status: 'active', saleFormat: 'fixed_price' }], users: [{ id: 22, handle: 'fixture-seller' }],
    blogPosts: [], marketplaceAccess: access, cartItems: [] }
  globalThis.fetch = async (url, options) => {
    const path = new URL(url).pathname; requests.push({ path, options })
    if (path.endsWith('/public-page-data') || path.endsWith('/bootstrap')) return Response.json({ data: fixture })
    if (path.endsWith('/auth/me')) return Response.json({ data: { id: 1, handle: 'fixture-buyer', verified: false, marketplace_access: access } })
    if (path.endsWith('/cart') && options.method === 'POST') return Response.json({ data: { id: 1 } }, { status: 201 })
    if (path.endsWith('/auctions/listings/11/bids') && options.method === 'POST') return Response.json({ data: { amount: 10 } }, { status: 201 })
    if (path.endsWith('/checkout/session')) return Response.json({ data: { checkout_url: 'https://checkout.example.invalid/test' } }, { status: 201 })
    throw new Error(`Unexpected request blocked: ${options.method} ${path}`)
  }
  const harness = await import('../dist-session-tests/session-harness.js')
  const { act, getControls } = harness
  const flush = () => act(async () => { await new Promise(resolve => setTimeout(resolve, 0)) })
  let root
  try {
    await act(async () => { root = harness.mount(document.getElementById('root')) }); await flush()
    let result
    await act(async () => { result = await getControls().market.addToCart(11) })
    assert.equal(result.success, false, 'guests still need sign-in')
    await act(async () => getControls().auth.authenticateWithToken('qa-unverified')); await flush()
    assert.equal(getControls().auth.currentUser.verified, false)
    assert.equal(getControls().market.getMarketplaceGateMessage('buy'), null)
    await act(async () => { result = await getControls().market.addToCart(11) })
    assert.equal(result.success, true)
    assert.equal(requests.filter(x => x.path.endsWith('/cart') && x.options.method === 'POST').length, 1)
    await act(async () => { result = await getControls().market.placeOrder({ acceptedOfferId: 7, shippingAddress: { country_code: 'GR' } }) })
    assert.equal(result.checkout_url, 'https://checkout.example.invalid/test')
    const checkout = requests.find(x => x.path.endsWith('/checkout/session'))
    assert.equal(JSON.parse(checkout.options.body).accepted_offer_id, 7)
    await assert.rejects(() => getControls().market.createListing({}), /Seller verification/)
    await assert.rejects(() => getControls().market.updateListing(11, {}), /Seller verification/)
    await act(async () => { result = await getControls().market.placeAuctionBid(11, 10) })
    assert.equal(result.success, true)
    assert.equal(requests.filter(x => x.path.endsWith('/auctions/listings/11/bids')).length, 1)
    assert.equal((await getControls().market.addDrawToCart(1, 1)).success, false)
    assert.equal(requests.some(x => /\/(listings|draws)$/.test(x.path) && x.options.method === 'POST'), false)
  } finally {
    if (root) await act(async () => root.unmount())
    globalThis.fetch = originalFetch; dom.window.close()
    for (const ch of channels) { ch.port1.close(); ch.port2.close() }
    globalThis.MessageChannel = NativeMessageChannel
    delete globalThis.window; delete globalThis.document; delete globalThis.CustomEvent; delete globalThis.IS_REACT_ACT_ENVIRONMENT
  }
})
