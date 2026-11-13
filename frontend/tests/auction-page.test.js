import test from 'node:test'
import assert from 'node:assert/strict'
import { JSDOM } from './runtime/node_modules/jsdom/lib/api.js'

test('unverified auction winner sees purchase action and can add it to cart on the actual page', async () => {
  const dom = new JSDOM('<div id="root"></div>', { url: 'http://cardora.test/en/proion/fixture-auction' })
  globalThis.window = dom.window; globalThis.document = dom.window.document
  globalThis.CustomEvent = dom.window.CustomEvent; globalThis.IS_REACT_ACT_ENVIRONMENT = true
  const NativeChannel = globalThis.MessageChannel; const channels = []
  globalThis.MessageChannel = class extends NativeChannel { constructor() { super(); channels.push(this) } }
  const originalFetch = globalThis.fetch
  const calls = []
  const access = { can_buy: true, can_bid: true, can_sell: false, can_join_draws: false, is_marketplace_ready: false }
  const product = { id: 11, slug: 'fixture-auction', title: 'Fixture auction', categoryId: 'cards',
    category: { id: 'cards', slug: 'kartes', name: 'Cards' }, status: 'active', stock: 1, price: 25, saleFormat: 'auction',
    sellerId: 22, seller: { id: 22, handle: 'fixture-seller', displayName: 'Fixture seller', name: 'Fixture seller' },
    media: [], tags: [], highlights: [], condition: 'Mint',
    auction: { startingBid: 20, currentBid: 25, reservePrice: 25, bidIncrement: 2, bidCount: 1, winningBidderId: 1, endsAt: '2020-01-01T00:00:00Z' } }
  const fixture = { categories: [product.category], products: [product], users: [product.seller], blogPosts: [], marketplaceAccess: access, cartItems: [] }
  globalThis.fetch = async (url, options) => {
    const path = new URL(url).pathname; calls.push({ path, options })
    if (path.endsWith('/public-page-data') || path.endsWith('/bootstrap')) return Response.json({ data: fixture })
    if (path.endsWith('/public-listings/fixture-auction')) return Response.json({ data: product })
    if (path.endsWith('/auth/me')) return Response.json({ data: { id: 1, handle: 'fixture-buyer', verified: false, marketplace_access: access } })
    if (path.endsWith('/cart') && options.method === 'POST') return Response.json({ data: { id: 1 } }, { status: 201 })
    throw new Error(`Unexpected test request: ${path}`)
  }
  const h = await import('../dist-session-tests/session-harness.js')
  let root
  const flush = () => h.act(async () => { await new Promise(resolve => setTimeout(resolve, 0)) })
  try {
    await h.act(async () => { root = h.mountAuctionPage(document.getElementById('root')) }); await flush()
    assert.ok(!document.body.textContent.includes('You won this auction'))
    await h.act(async () => h.getControls().auth.authenticateWithToken('qa-unverified')); await flush()
    assert.equal(h.getControls().auth.currentUser.verified, false)
    assert.ok(document.body.textContent.includes('You won this auction'))
    const buttons = [...document.querySelectorAll('button')]
    assert.equal(buttons.find(b => b.textContent.includes('Place bid'))?.disabled, true)
    const buy = buttons.find(b => b.textContent === 'Add winning bid to cart')
    assert.ok(buy && !buy.disabled)
    await h.act(async () => buy.click()); await flush()
    assert.equal(calls.filter(c => c.path.endsWith('/cart') && c.options.method === 'POST').length, 1)
    assert.ok(document.body.textContent.includes('Added to cart.'))
  } finally {
    if (root) await h.act(async () => root.unmount())
    globalThis.fetch = originalFetch; dom.window.close()
    for (const ch of channels) { ch.port1.close(); ch.port2.close() }
    globalThis.MessageChannel = NativeChannel
    delete globalThis.window; delete globalThis.document; delete globalThis.CustomEvent; delete globalThis.IS_REACT_ACT_ENVIRONMENT
  }
})
