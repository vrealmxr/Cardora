import test from 'node:test'
import assert from 'node:assert/strict'
import { JSDOM } from './runtime/node_modules/jsdom/lib/api.js'

test('React support notice, cookies and closing stay offline; explicit open starts the existing chat once', async () => {
  const dom = new JSDOM('<div id="root"></div>', { url: 'https://cardora.gr/el/kartes' })
  Object.assign(globalThis, { window: dom.window, document: dom.window.document, CustomEvent: dom.window.CustomEvent, IS_REACT_ACT_ENVIRONMENT: true })
  const NativeChannel = globalThis.MessageChannel
  const channels = []
  globalThis.MessageChannel = class extends NativeChannel { constructor() { super(); channels.push(this) } }
  let root
  try {
    const h = await import('../dist-analytics-tests/analytics-harness.js')
    const button = label => [...document.querySelectorAll('button')].find(item => item.textContent === label)
    await h.act(async () => { root = h.mountSupport(document.getElementById('root')) })
    assert.equal(document.scripts.length, 0)
    await h.act(async () => button('Αποδοχή όλων').click())
    assert.equal(document.scripts.length, 0, 'Cookie acceptance is not a request for support')
    await h.act(async () => button('Υποστήριξη').click())
    assert.ok(document.querySelector('[role="dialog"]').textContent.includes('Chatwoot'))
    assert.ok(document.querySelector('a[href="mailto:support@cardora.gr"]'))
    assert.equal(document.scripts.length, 0)
    assert.equal(document.activeElement.getAttribute('aria-label'), 'Κλείσιμο επιλογών υποστήριξης')
    await h.act(async () => document.activeElement.click())
    assert.equal(document.querySelector('[role="dialog"]'), null)
    assert.equal(document.activeElement.textContent, 'Υποστήριξη')
    await h.act(async () => button('Υποστήριξη').click())
    await h.act(async () => button('Άνοιγμα συνομιλίας').click())
    assert.equal(document.scripts.length, 1)
    let opened = 0
    window.chatwootSDK = { run: () => {
      window.$chatwoot = { hasLoaded: true, toggle: state => { assert.equal(state, 'open'); opened++ } }
      window.dispatchEvent(new window.Event('chatwoot:ready'))
    } }
    await h.act(async () => document.scripts[0].onload())
    assert.equal(opened, 1)
    assert.equal(document.querySelector('[role="dialog"]'), null)
    assert.equal(button('Υποστήριξη'), undefined, 'Existing SDK launcher takes over without a duplicate button')
  } finally {
    if (root) { const h = await import('../dist-analytics-tests/analytics-harness.js'); await h.act(async () => root.unmount()) }
    dom.window.close()
    channels.forEach(channel => { channel.port1.close(); channel.port2.close() })
    globalThis.MessageChannel = NativeChannel
    for (const name of ['window', 'document', 'CustomEvent', 'IS_REACT_ACT_ENVIRONMENT']) delete globalThis[name]
  }
})
