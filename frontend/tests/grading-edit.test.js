import test from 'node:test'
import assert from 'node:assert/strict'
import { JSDOM } from './runtime/node_modules/jsdom/lib/api.js'

test('real edit form preserves old sealed grading value and condition while showing separate grading guidance', async () => {
  const dom = new JSDOM('<div id="root"></div>', { url: 'http://cardora.test/en/dimiourgia-aggelias?edit=1' })
  Object.assign(globalThis, { window: dom.window, document: dom.window.document, CustomEvent: dom.window.CustomEvent, IS_REACT_ACT_ENVIRONMENT: true })
  window.scrollTo = () => {}
  window.requestAnimationFrame = callback => window.setTimeout(callback, 0)
  window.cancelAnimationFrame = handle => window.clearTimeout(handle)
  window.HTMLElement.prototype.scrollIntoView = () => {}
  const NativeChannel = globalThis.MessageChannel
  const channels = []
  globalThis.MessageChannel = class extends NativeChannel { constructor() { super(); channels.push(this) } }
  const originalFetch = globalThis.fetch
  const data = { categories: [{ id: 'comics', databaseId: 3, slug: 'komik-vivlia', name: 'Comics', metrics: {} }], products: [], users: [], blogPosts: [], marketplaceAccess: { can_create_listing: true } }
  const requests = []
  const listing = { id: 1, seller_id: 101, category_id: 3, category: { slug: 'comics' }, title: 'Legacy comic', description: 'Fixture', condition: 'Mint',
    sale_format: 'fixed_price', product: { title: 'Legacy comic', brand: 'Marvel', specifications: {} }, attributes: { graded_company: 'Sealed', grade: 'Near Mint' } }
  globalThis.fetch = async (url, options) => {
    const path = new URL(url).pathname
    assert.ok(!options.method || options.method === 'GET', 'Test must never create or update listings')
    requests.push(path)
    if (path.endsWith('/auth/me')) return Response.json({ data: { id: 101, display_name: 'QA', handle: 'qa' } })
    if (path.endsWith('/bootstrap') || path.endsWith('/public-page-data')) return Response.json({ data })
    if (path.endsWith('/listings/1')) return Response.json({ data: listing })
    throw new Error(`Unexpected test request: ${path}`)
  }
  let root
  try {
    const h = await import('../dist-session-tests/session-harness.js')
    await h.act(async () => { root = h.mountListingForm(document.getElementById('root'), data) })
    await h.act(async () => h.getControls().auth.authenticateWithToken('qa-form'))
    await h.act(async () => { await new Promise(resolve => setTimeout(resolve, 0)) })
    const step = number => [...document.querySelectorAll('button')].find(button => button.querySelector('p')?.textContent === number)
    assert.ok(step('04'), document.body.textContent.slice(0, 500))
    await h.act(async () => step('04').click())
    const select = document.getElementById('listing-gradedCompany')
    assert.equal(select?.value, 'Sealed')
    assert.ok(select.selectedOptions[0].textContent.includes('not a grading company'))
    assert.equal(document.getElementById('listing-grade').value, 'Near Mint', 'Legacy notes must not be silently erased')
    assert.ok(document.getElementById('listing-grade-help').textContent.includes('Sealed packaging is not grading'))
    await h.act(async () => step('02').click())
    assert.equal(document.getElementById('listing-condition').value, 'Mint')
    assert.ok(document.querySelector('a[href="/en/katastasi-kai-grading"][target="_blank"]'))
    assert.ok(requests.some(path => path.endsWith('/listings/1')))
  } finally {
    if (root) { const h = await import('../dist-session-tests/session-harness.js'); await h.act(async () => root.unmount()) }
    globalThis.fetch = originalFetch
    globalThis.MessageChannel = NativeChannel
    channels.forEach(channel => { channel.port1.close(); channel.port2.close() })
    dom.window.close()
    for (const name of ['window', 'document', 'CustomEvent', 'IS_REACT_ACT_ENVIRONMENT']) delete globalThis[name]
  }
})
