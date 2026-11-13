import test from 'node:test'
import assert from 'node:assert/strict'
import { JSDOM } from './runtime/node_modules/jsdom/lib/api.js'

test('real tracker waits for lazy metadata, deduplicates StrictMode and observes consent/settings', async () => {
  const dom = new JSDOM('<div id="root"></div>', { url: 'https://cardora.gr/el/kartes' })
  Object.assign(globalThis, { window: dom.window, document: dom.window.document, CustomEvent: dom.window.CustomEvent, IS_REACT_ACT_ENVIRONMENT: true })
  const originalFetch = globalThis.fetch
  globalThis.fetch = async () => Response.json({ data: {} })
  const NativeMessageChannel = globalThis.MessageChannel
  const channels = []
  globalThis.MessageChannel = class extends NativeMessageChannel {
    constructor() { super(); channels.push(this) }
  }
  let root
  try {
    const h = await import('../dist-analytics-tests/analytics-harness.js')
    const views = () => (window.dataLayer || []).filter(item => item[0] === 'event' && item[1] === 'page_view')
    await h.act(async () => { root = h.mount(document.getElementById('root')) })
    assert.equal(document.scripts.length, 0)
    const button = label => [...document.querySelectorAll('button')].find(item => item.textContent === label)
    await h.act(async () => button('Μόνο απαραίτητα').click())
    assert.equal(document.scripts.length, 0)
    await h.act(async () => h.openCookieSettings())
    assert.ok(button('Αποθήκευση επιλογών'))
    await h.act(async () => button('Αποδοχή όλων').click())
    assert.equal(views().length, 1)
    assert.equal(views()[0][2].page_title, 'Ready /el/kartes')
    await h.act(async () => { h.getControls().setReady(false); h.getControls().navigate('/en/faq') })
    assert.equal(views().length, 1, 'loading route sent stale page title')
    await h.act(async () => h.getControls().setReady(true))
    assert.equal(views().length, 2)
    assert.equal(views()[1][2].page_title, 'Ready /en/faq')
    await h.act(async () => h.getControls().navigate(-1))
    assert.equal(views().length, 3)
    assert.equal(document.scripts.length, 1)
    await h.act(async () => h.openCookieSettings())
    assert.equal(document.querySelector('a[href="/el/politiki-cookies"]')?.textContent, 'Πολιτική Cookies')
  } finally {
    if (root) { const h = await import('../dist-analytics-tests/analytics-harness.js'); await h.act(async () => root.unmount()) }
    dom.window.close()
    channels.forEach(channel => { channel.port1.close(); channel.port2.close() })
    globalThis.MessageChannel = NativeMessageChannel
    globalThis.fetch = originalFetch
    for (const name of ['window', 'document', 'CustomEvent', 'IS_REACT_ACT_ENVIRONMENT']) delete globalThis[name]
  }
})
