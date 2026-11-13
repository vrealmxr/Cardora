import test from 'node:test'
import assert from 'node:assert/strict'
import { JSDOM } from './runtime/node_modules/jsdom/lib/api.js'
import { createAnalyticsClient, ANALYTICS_ID } from '../src/utils/analytics.js'
import { normalizeCookieConsent, readCookieConsent, writeCookieConsent, resetSessionCookieConsent } from '../src/utils/cookieConsent.js'

const page = (navigationId, pathname = '/el/kartes', search = '') => ({ navigationId, pathname, search, title: 'Cards | Cardora', status: 200 })
const views = win => (win.dataLayer || []).filter(item => item[0] === 'event' && item[1] === 'page_view')

test('cookie choices fail closed and apply even when storage writes fail', () => {
  assert.equal(normalizeCookieConsent({ analytics: 'false', preferences: 1 }).analytics, false)
  const dom = new JSDOM('', { url: 'https://cardora.gr' })
  globalThis.window = dom.window
  globalThis.CustomEvent = dom.window.CustomEvent
  const prototype = dom.window.Storage.prototype
  const set = prototype.setItem
  try {
    writeCookieConsent({ analytics: true })
    prototype.setItem = () => { throw new Error('Quota exceeded') }
    writeCookieConsent({ analytics: false })
    assert.equal(readCookieConsent().analytics, false, 'old persisted grant overrode withdrawal')
    prototype.setItem = set
    resetSessionCookieConsent()
    window.localStorage.clear()
    assert.equal(readCookieConsent(), null, 'cleared storage retained a grant')
  } finally {
    prototype.setItem = set
    resetSessionCookieConsent()
    dom.window.close()
    delete globalThis.window
    delete globalThis.CustomEvent
  }
})

test('basic consent gates all scripts/events; one event per ready visit; withdrawal unloads tracking', () => {
  const dom = new JSDOM('', { url: 'https://cardora.gr/el/kartes' })
  const win = dom.window
  let reloads = 0
  const client = createAnalyticsClient(win, win.document, { reload: () => { reloads++ } })
  client.track(page('a'))
  assert.equal(win.document.scripts.length, 0)
  assert.equal(win.dataLayer, undefined)
  client.setConsent({ analytics: 'false' })
  assert.equal(client.track(page('a')), false)
  client.setConsent({ analytics: true })
  assert.equal(client.track(page('a')), true)
  assert.equal(client.track(page('a')), false)
  assert.equal(win.document.scripts.length, 1)
  assert.deepEqual([...win.dataLayer[0]].slice(0, 2), ['consent', 'default'])
  assert.equal(win.dataLayer[0][2].analytics_storage, 'denied')
  assert.equal(win.dataLayer[2][2].ad_storage, 'denied')
  client.track(page('b', '/en/faq'))
  client.track(page('a')) // Back is a new visit, despite reusing history key.
  assert.equal(views(win).length, 3)
  assert.equal(views(win)[1][2].page_referrer, 'https://cardora.gr/el/kartes')
  client.setConsent({ analytics: false })
  assert.equal(win[`ga-disable-${ANALYTICS_ID}`], true)
  assert.equal(reloads, 1)
  client.track(page('c'))
  assert.equal(views(win).length, 3)
  dom.window.close()
})

test('preview/local hosts, private paths, errors and query secrets are excluded', () => {
  for (const hostname of ['localhost', 'seo-content-preview.cardora-frontend.pages.dev', 'cardora.gr.attacker.test']) {
    const dom = new JSDOM('', { url: `https://${hostname}/el` })
    const client = createAnalyticsClient(dom.window, dom.window.document)
    client.setConsent({ analytics: true })
    client.track(page('a'))
    assert.equal(dom.window.document.scripts.length, 0)
    assert.equal(views(dom.window).length, 0)
    dom.window.close()
  }
  const dom = new JSDOM('', { url: 'https://cardora.gr/el' })
  const client = createAnalyticsClient(dom.window, dom.window.document)
  client.setConsent({ analytics: true })
  for (const path of ['/el/checkout', '/el/minymata', '/el/epanafora-kodikou', '/el/cardora-scanner', '/el/does-not-exist']) {
    client.track(page(path, path, '?token=secret'))
  }
  client.track({ ...page('missing', '/el/proion/missing'), status: 404 })
  assert.equal(dom.window.document.scripts.length, 0)
  client.track({ ...page('search', '/el/anazitisi', '?q=private@example.test&token=secret&page=2'), title: 'Search private@example.test' })
  assert.equal(views(dom.window)[0][2].page_path, '/el/anazitisi?page=2')
  assert.equal(views(dom.window)[0][2].page_title, 'Cardora search')
  assert.doesNotMatch(JSON.stringify(dom.window.dataLayer), /private@|secret/)
  dom.window.close()
})
