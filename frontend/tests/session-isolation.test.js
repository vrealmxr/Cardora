import test from 'node:test'
import assert from 'node:assert/strict'
import { JSDOM } from './runtime/node_modules/jsdom/lib/api.js'

test('real providers isolate account data, ignore stale locale responses and load guests anonymously', async () => {
  const dom = new JSDOM('<div id="root"></div>', { url: 'http://cardora.test/el' })
  globalThis.window = dom.window
  globalThis.document = dom.window.document
  globalThis.CustomEvent = dom.window.CustomEvent
  globalThis.IS_REACT_ACT_ENVIRONMENT = true
  const NativeMessageChannel = globalThis.MessageChannel
  const channels = []
  globalThis.MessageChannel = class extends NativeMessageChannel {
    constructor() { super(); channels.push(this) }
  }
  const requests = []
  const pending = []
  const publicData = { categories: [], products: [], users: [], blogPosts: [] }
  const originalFetch = globalThis.fetch
  globalThis.fetch = (url, options) => {
    const target = new URL(url)
    requests.push({ target, options })
    if (target.pathname.endsWith('/public-page-data')) return Promise.resolve(Response.json({ data: publicData }))
    if (target.pathname.endsWith('/auth/me')) {
      const id = options.headers.Authorization.endsWith('qa-a') ? 101 : 202
      return Promise.resolve(Response.json({ data: { id, display_name: `Test ${id}`, handle: `test-${id}` } }))
    }
    if (target.pathname.endsWith('/auth/logout')) return Promise.resolve(Response.json({ data: null }))
    if (target.pathname.endsWith('/bootstrap')) return new Promise((resolve) => pending.push({ target, options, resolve }))
    throw new Error(`Unexpected request blocked by test: ${url}`)
  }
  const harness = await import('../dist-session-tests/session-harness.js')
  const { act, getControls } = harness
  const flush = () => act(async () => { await new Promise(resolve => setTimeout(resolve, 0)) })
  const reply = (request, data, status = 200) => act(async () => request.resolve(Response.json({ data }, { status })))
  const privateData = (id) => ({ ...publicData, orders: [{ id: `order-${id}` }],
    conversations: [{ id: `conversation-${id}` }], supportTickets: [{ id: `ticket-${id}` }],
    notifications: [{ id: `notification-${id}` }], favoriteProductIds: [id] })
  let root
  try {
    await act(async () => { root = harness.mount(document.getElementById('root')) })
    await flush()
    const guest = requests.find(({ target }) => target.pathname.endsWith('/public-page-data'))
    assert.equal(guest.options.credentials, 'omit')
    assert.equal(guest.options.headers.Authorization, undefined)
    assert.equal(pending.length, 0, 'guest must never call private bootstrap')

    await act(async () => getControls().auth.authenticateWithToken('qa-a'))
    await flush()
    const firstA = pending.shift()
    assert.equal(firstA.options.headers.Authorization, 'Bearer qa-a')
    await reply(firstA, privateData(101))
    assert.equal(getControls().market.orders[0].id, 'order-101')

    let lateRefresh
    await act(async () => { lateRefresh = getControls().market.refreshBootstrap() })
    const lateA = pending.shift()
    await act(async () => getControls().auth.logout())
    await flush()
    for (const key of ['orders', 'conversations', 'supportTickets', 'notifications', 'favoriteProductIds']) {
      assert.deepEqual(getControls().market[key], [], `${key} remains after logout`)
    }
    await reply(lateA, privateData(101))
    await lateRefresh
    assert.deepEqual(getControls().market.orders, [], 'late account response restored private state')

    await act(async () => getControls().auth.authenticateWithToken('qa-b'))
    await flush()
    assert.deepEqual(getControls().market.orders, [])
    await reply(pending.shift(), privateData(202))
    assert.equal(getControls().market.orders[0].id, 'order-202')

    let oldLocaleRequest
    await act(async () => { oldLocaleRequest = getControls().market.refreshBootstrap() })
    const greek = pending.shift()
    await act(async () => getControls().navigate('/en'))
    await flush()
    const english = pending.shift()
    assert.equal(english.target.searchParams.get('locale'), 'en')
    assert.equal(english.options.headers['X-Locale'], 'en')
    const interestProducts = [
      { id: 1, title: 'Magic card', franchise: 'Magic', tags: ['spell'] },
      { id: 2, title: 'Pokemon card', franchise: 'Pokemon', tags: ['electric'] },
    ]
    await reply(english, { ...privateData(202), products: interestProducts, favoriteProductIds: [1], supportArticles: [{ title: 'English help' }] })
    await reply(greek, { ...privateData(202), supportArticles: [{ title: 'Greek help' }] })
    await oldLocaleRequest
    assert.equal(getControls().market.supportArticles[0].title, 'English help')
    assert.ok(getControls().market.getPersonalizationScore(interestProducts[0]) > 0, 'favorite interests lost')
    assert.equal(getControls().market.getPersonalizationScore(interestProducts[1]), 0)
    let changedInterests
    await act(async () => { changedInterests = getControls().market.refreshBootstrap() })
    await reply(pending.shift(), { ...privateData(202), products: interestProducts, favoriteProductIds: [2] })
    await changedInterests
    assert.equal(getControls().market.getPersonalizationScore(interestProducts[0]), 0, 'score cache retained previous interests')
    assert.ok(getControls().market.getPersonalizationScore(interestProducts[1]) > 0)

    const oldTokenRequest = harness.apiClient.get('/bootstrap').catch(error => error)
    const oldToken = pending.shift()
    harness.setStoredAuthToken('qa-new-session')
    await reply(oldToken, null, 401)
    assert.equal((await oldTokenRequest).status, 401)
    assert.equal(harness.getStoredAuthToken(), 'qa-new-session', 'stale 401 invalidated a newer session')
  } finally {
    if (root) await act(async () => root.unmount())
    globalThis.fetch = originalFetch
    dom.window.close()
    for (const channel of channels) { channel.port1.close(); channel.port2.close() }
    globalThis.MessageChannel = NativeMessageChannel
    delete globalThis.window
    delete globalThis.document
    delete globalThis.CustomEvent
    delete globalThis.IS_REACT_ACT_ENVIRONMENT
  }
})
