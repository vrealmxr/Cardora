import test from 'node:test'
import assert from 'node:assert/strict'
import { JSDOM } from './runtime/node_modules/jsdom/lib/api.js'

test('secondary listing photos load on pointer interest and never replace the front before success', async () => {
  const dom = new JSDOM('<div id="root"></div>', { url: 'https://cardora.gr/el/kartes' })
  Object.assign(globalThis, { window: dom.window, document: dom.window.document, IS_REACT_ACT_ENVIRONMENT: true })
  const NativeChannel = globalThis.MessageChannel
  const channels = []
  globalThis.MessageChannel = class extends NativeChannel { constructor() { super(); channels.push(this) } }
  let root
  const h = await import('../dist-analytics-tests/analytics-harness.js')
  const product = { title: 'Test card', media: [{ url: '/front.webp' }, { url: '/back.webp' }] }
  try {
    await h.act(async () => { root = h.mountVisual(document.getElementById('root'), product) })
    const images = () => [...document.images]
    const front = () => images().find(image => image.alt === product.title)
    const enter = async pointerType => h.act(async () => {
      const event = new window.Event('pointerover', { bubbles: true })
      Object.defineProperty(event, 'pointerType', { value: pointerType })
      front().dispatchEvent(event)
    })
    assert.equal(images().length, 2)
    assert.ok(images().every(image => image.getAttribute('src') === '/front.webp'))
    assert.equal(images().filter(image => image.alt).length, 1, 'Decorative layers are not repeated to assistive technology')
    await enter('touch')
    assert.equal(images().length, 2, 'Touch scrolling does not fetch the unused hover photo')
    await enter('mouse')
    const back = images().find(image => image.getAttribute('src') === '/back.webp')
    assert.ok(back)
    assert.ok(!front().className.includes('group-hover:opacity-0'), 'Slow download keeps the front visible')
    await h.act(async () => back.dispatchEvent(new window.Event('error')))
    assert.ok(!front().className.includes('group-hover:opacity-0'), 'Failed download keeps the front visible')
    await h.act(async () => back.dispatchEvent(new window.Event('load')))
    assert.ok(front().className.includes('group-hover:opacity-0'))
    assert.equal(images().length, 4)
    await h.act(async () => root.render({ ...product, media: [{ url: '/other-front.webp' }, { url: '/other-back.webp' }] }))
    assert.equal(images().length, 2, 'Reusing a card for another product does not inherit the loaded back')
    assert.ok(images().every(image => image.getAttribute('src') === '/other-front.webp'))
    await h.act(async () => root.render({ ...product, media: [{ url: '/full.jpg', thumbUrl: '/small.webp', width: 1200, height: 1800, thumbWidth: 400, thumbHeight: 600 }] }))
    assert.equal(front().width, 400)
    assert.equal(front().height, 600)
    assert.equal(front().getAttribute('srcset'), '/small.webp 400w, /full.jpg 1200w')
    assert.equal(front().getAttribute('sizes'), 'auto, 100vw')
    assert.equal(front().loading ?? front().getAttribute('loading'), 'lazy')
  } finally {
    if (root) await h.act(async () => root.unmount())
    dom.window.close()
    channels.forEach(channel => { channel.port1.close(); channel.port2.close() })
    globalThis.MessageChannel = NativeChannel
    for (const name of ['window', 'document', 'IS_REACT_ACT_ENVIRONMENT']) delete globalThis[name]
  }
})
