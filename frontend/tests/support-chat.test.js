import test from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { JSDOM } from './runtime/node_modules/jsdom/lib/api.js'
import { createSupportChat } from '../src/utils/supportChat.js'

test('support stays absent until explicitly opened, is deduplicated and opens only after SDK readiness', async () => {
  const dom = new JSDOM('', { url: 'https://cardora.gr/el/kartes' })
  try {
    const { window: win } = dom
    const client = createSupportChat(win, win.document)
    assert.equal(win.document.scripts.length, 0)
    const opened = client.open('el')
    assert.equal(client.open('el'), opened)
    assert.equal(win.document.scripts.length, 1)
    const script = win.document.scripts[0]
    assert.equal(script.src, 'https://app.chatwoot.com/packs/js/sdk.js')
    assert.equal(script.referrerPolicy, 'no-referrer')
    let config, count = 0
    win.chatwootSDK = { run: value => { config = value; win.$chatwoot = { hasLoaded: false, toggle: state => { assert.equal(state, 'open'); count++ } } } }
    script.onload()
    assert.equal(count, 0)
    assert.equal(win.chatwootSettings.locale, 'el')
    assert.equal(config.baseUrl, 'https://app.chatwoot.com')
    assert.deepEqual(Object.keys(config).sort(), ['baseUrl', 'websiteToken'])
    win.$chatwoot.hasLoaded = true
    win.dispatchEvent(new win.Event('chatwoot:ready'))
    await opened
    assert.equal(count, 1)
    await client.open('el')
    assert.equal(count, 2)
    assert.equal(win.document.scripts.length, 1)
  } finally { dom.window.close() }
})

test('preview and local hosts cannot load the live support SDK, including direct open calls', async () => {
  for (const host of ['preview.cardora-frontend.pages.dev', 'localhost', 'cardora.gr.attacker.test']) {
    const dom = new JSDOM('', { url: `https://${host}/el` })
    try {
      await assert.rejects(createSupportChat(dom.window, dom.window.document).open('en'), /preview/)
      assert.equal(dom.window.document.scripts.length, 0)
    } finally { dom.window.close() }
  }
  const template = await readFile(new URL('../index.html', import.meta.url), 'utf8')
  assert.ok(!template.includes('chatwoot'), 'Production HTML must not initialize the chat automatically')
})

test('script download failure permits retry but partially initialized SDK is not run twice', async () => {
  const dom = new JSDOM('', { url: 'https://www.cardora.gr/en' })
  try {
    const client = createSupportChat(dom.window, dom.window.document, { timeoutMs: 10 })
    const failed = client.open('en')
    const firstRejection = assert.rejects(failed, /unavailable/)
    dom.window.document.scripts[0].onerror()
    await firstRejection
    assert.equal(dom.window.document.scripts.length, 0)
    const retry = client.open('en')
    const timedOut = assert.rejects(retry, /reload/)
    let runs = 0
    dom.window.chatwootSDK = { run: () => { runs++ } }
    dom.window.document.scripts[0].onload()
    await timedOut
    await assert.rejects(client.open('en'), /reload/)
    assert.equal(runs, 1)
  } finally { dom.window.close() }
})
