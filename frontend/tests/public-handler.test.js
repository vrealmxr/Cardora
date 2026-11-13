import test from 'node:test'
import assert from 'node:assert/strict'
import { createPublicHandler } from '../src/server/publicHandler.js'
import { buildDocument } from '../src/server/document.js'
import { createSitemap } from '../src/server/sitemap.js'
import { renderPublicPage } from '../dist-ssr/entry-server.js'
import { readPreviewResource } from '../src/server/previewResource.js'
import { sanitizePublicProfile } from '../src/utils/publicProfile.js'
import { createPublicDataReader } from '../src/server/publicDataReader.js'

const template = '<!doctype html><html lang="en"><head><title>Old</title><meta property="og:site_name" content="Old"></head><body><div id="root"></div></body></html>'
const data = { categories: [{ id: 'cards', slug: 'kartes', name: 'Cards', metrics: {}, spotlightFilters: [] }], products: [], blogPosts: [] }
const request = (path, options) => new Request(`https://cardora.gr${path}`, options)
function handler(overrides = {}) {
  return createPublicHandler({ template, render: renderPublicPage, loadData: async () => data,
    loadSeo: async () => ({}), tutorialSlugs: [], ...overrides })
}

test('HTML encoding prevents metadata and snapshot script injection', () => {
  const dangerous = '</script><script>alert("x")</script>&\u2028'
  const output = buildDocument(template, { html: '<h1>Cardora</h1>', head: {
    title: dangerous, locale: 'el', description: '" onload="alert(1)', canonical: 'https://cardora.gr/el',
  } }, { value: dangerous })
  assert.equal((output.match(/<title>/g) || []).length, 1)
  assert.equal((output.match(/og:site_name/g) || []).length, 1)
  assert.ok(output.includes('content="Cardora"'))
  assert.ok(!output.includes('<script>alert'))
  const encoded = output.match(/id="cardora-public-data">([\s\S]*?)<\/script>/)[1]
  assert.deepEqual(JSON.parse(encoded), { value: dangerous })
})

test('unknown routes, missing products/blog posts and out-of-range pages are HTTP 404', async () => {
  for (const path of ['/el/not-a-page', '/en/proion/missing', '/el/blog/missing', '/en/kartes?page=999']) {
    const result = await handler()(request(path))
    assert.equal(result.status, 404, path)
    const html = await result.text()
    assert.match(html, /<h1[\s>]/)
    assert.match(html, /content="noindex, follow"/)
    assert.ok(!html.includes('hreflang='))
  }
})

test('private and scanner routes do not fetch or render any public snapshot', async () => {
  const forbidden = () => { throw new Error('Must not fetch or render') }
  const handle = handler({ loadData: forbidden, loadSeo: forbidden, render: forbidden })
  for (const path of ['/el/profil', '/en/checkout', '/el/cardora-scanner']) {
    const result = await handle(request(path))
    assert.equal(result.status, 200)
    const html = await result.text()
    assert.match(html, /noindex, follow/)
    assert.ok(!html.includes('cardora-public-data'))
  }
})

test('sold detail is authoritative, indexable and cannot offer purchase; missing detail beats stale catalog', async () => {
  const active = { id: 1, slug: 'sold-card', title: 'Sold card', categoryId: 'cards', status: 'published', stock: 1,
    price: 25, saleFormat: 'fixed_price', media: [], tags: [], category: data.categories[0] }
  const similar = { ...active, id: 2, slug: 'available-card', title: 'Available card' }
  for (const locale of ['el', 'en']) {
    for (const saleFormat of ['fixed_price', 'auction', 'trade']) {
      const sold = { ...active, status: 'sold', stock: 0, saleFormat, acceptOffers: true,
        auction: saleFormat === 'auction' ? { currentBid: 25, bidIncrement: 1 } : null,
        lot: { allowsIndividualPurchase: true, individualCards: [{ id: 'a', title: 'One card', price: 2 }] } }
      const response = await handler({ loadData: async () => ({ ...data, products: [active, similar] }),
        loadListing: async () => sold })(request(`/${locale}/proion/sold-card`))
      const html = await response.text()
      assert.equal(response.status, 200)
      assert.ok(html.includes('content="index, follow"'))
      assert.ok(html.includes(locale === 'en' ? 'This item has been sold' : 'Το αντικείμενο έχει πουληθεί'))
      assert.ok(html.includes(`href="/${locale}/proion/available-card"`))
      const buttons = [...html.split('<section class="mt-16">')[0].matchAll(/<button[^>]*>([\s\S]*?)<\/button>/g)].map(match => match[1].replace(/<[^>]+>/g, ''))
      assert.ok(!buttons.some(text => /Add to cart|Buy now|Place bid|Προσθήκη στο καλάθι|Υποβολή προσφοράς|Αγορά τώρα/.test(text)))
      assert.ok(!html.includes('href="/dashboard-politi/kliroseis"'))
    }
    const missing = await handler({ loadData: async () => ({ ...data, products: [active] }), loadListing: async () => null })(request(`/${locale}/proion/sold-card`))
    assert.equal(missing.status, 404)
    assert.ok(!(await missing.text()).includes('<h1 class="mt-4 font-display text-4xl text-white">Sold card'))
  }
  const failed = await handler({ loadListing: async () => { throw new Error('Expected listing outage') } })(request('/en/proion/sold-card'))
  assert.equal(failed.status, 503)
})

test('sitemap includes authoritative sold paths and drops stale or withdrawn catalog URLs', async () => {
  const response = await handler({ loadData: async () => ({ ...data, products: [{ slug: 'stale-card' }] }),
    loadListingPaths: async () => [{ slug: 'sold-card', updatedAt: '2026-10-23T00:00:00Z' }] })(request('/sitemap.xml'))
  const xml = await response.text()
  assert.ok(xml.includes('/el/proion/sold-card'))
  assert.ok(xml.includes('/en/proion/sold-card'))
  assert.ok(!xml.includes('stale-card'))
})

test('redirects retain query parameters and malformed paths fail', async () => {
  const handle = handler()
  for (const [path, target] of [['/faq?x=1', '/el/faq?x=1'], ['/en/terms', '/en/oroi-xrisis'], ['/en/kartes/?page=2', '/en/kartes?page=2']]) {
    const result = await handle(request(path))
    assert.equal(result.status, 301)
    assert.equal(result.headers.get('Location'), target)
  }
  assert.equal((await handle(request('/el/proion/a%2Fb'))).status, 400)
  assert.equal((await handle(request('/el/%ZZ'))).status, 400)
})

test('origin failure returns retryable 503 rather than an empty indexable page', async () => {
  const result = await handler({ loadData: async () => { throw new Error('Expected simulated outage') } })(request('/el/faq'))
  assert.equal(result.status, 503)
  assert.equal(result.headers.get('Retry-After'), '30')
  assert.equal(result.headers.get('X-Robots-Tag'), 'noindex')
})

test('preview is noindex, HEAD is bodyless and non-read requests cannot reach the renderer', async () => {
  const handle = handler()
  const preview = await handle(new Request('https://qa.cardora-frontend.pages.dev/en/faq'))
  assert.equal(preview.status, 200)
  assert.equal(preview.headers.get('X-Robots-Tag'), 'noindex, nofollow')
  assert.match(await preview.text(), /content="noindex, nofollow"/)
  const head = await handle(request('/en/faq', { method: 'HEAD' }))
  assert.equal(head.status, 200)
  assert.equal(await head.text(), '')
  assert.equal((await handle(request('/el/faq', { method: 'POST' }))).status, 405)
})

test('robots and sitemap use actual text/XML; sitemap includes both locales and valid dated listings', async () => {
  const handle = handler({ loadData: async () => ({ ...data, products: [{ slug: 'real-card', updatedAt: '2026-10-23T00:00:00Z' }] }) })
  const robots = await handle(request('/robots.txt'))
  assert.match(robots.headers.get('Content-Type'), /text\/plain/)
  assert.match(await robots.text(), /Sitemap: https:\/\/cardora.gr\/sitemap.xml/)
  const sitemap = await handle(request('/sitemap.xml'))
  assert.match(sitemap.headers.get('Content-Type'), /application\/xml/)
  const xml = await sitemap.text()
  assert.ok(xml.includes('/el/proion/real-card</loc>'))
  assert.ok(xml.includes('/en/proion/real-card</loc>'))
  assert.ok(!xml.includes('checkout'))
  assert.equal((await handle(request('/sitemap-99.xml'))).status, 404)
  const huge = createSitemap({ products: Array.from({ length: 21000 }, (_, i) => ({ slug: `item-${i}` })) }, [])
  assert.ok(huge.xml.includes('<sitemapindex'))
})

test('missing assets cannot become a successful SPA fallback', async () => {
  const result = await handler()(request('/static/missing.js'), { ASSETS: { fetch: async () => new Response(template, { headers: { 'Content-Type': 'text/html' } }) } })
  assert.equal(result.status, 404)
})

test('preview proxy strips credentials, maps bootstrap to public data and rejects account/scanner paths', async () => {
  const calls = []
  const fetchResource = async (url, options) => { calls.push({ url, options }); return Response.json({ data: {} }) }
  for (const path of ['auth/me', 'scanner/scan', 'admin', 'orders', 'https://example.com']) {
    const result = await readPreviewResource(new Request(`https://qa.pages.dev/index.php/api/${path}`), fetchResource)
    assert.equal(result.status, 403)
  }
  assert.equal(calls.length, 0)
  const result = await readPreviewResource(new Request('https://qa.pages.dev/index.php/api/bootstrap?secret=ignored', {
    headers: { Cookie: 'private', Authorization: 'Bearer private', 'X-Locale': 'en' },
  }), fetchResource)
  assert.equal(result.status, 200)
  assert.equal(calls[0].url, 'https://api.cardora.gr/index.php/api/public-page-data?locale=en')
  assert.equal(calls[0].options.headers.Cookie, undefined)
  assert.equal(calls[0].options.headers.Authorization, undefined)
})

test('public profiles render authoritative fields and keep nested account data out of HTML', async () => {
  const profile = sanitizePublicProfile({ id: 7, handle: 'collector', display_name: 'Actual collector',
    bio: 'Public collection biography', name: 'Private legal name', email: 'private@example.test',
    purchase_count: 800, profile_likes_count: 27, followers_count: 12, liked_by_auth_user: true,
    collection_entries: [{ id: 1, title: 'Collection card', media: [{ url: '/storage/card.jpg', secret: 'MEDIA-SECRET' }],
      metadata: { secret: 'ENTRY-SECRET' }, product: { title: 'Card', slug: 'card', seller: { email: 'NESTED-SECRET' } } },
      { id: 2, title: 'PRIVATE-ENTRY', visibility: 'private' }],
  })
  const result = await handler({ loadData: async () => ({ ...data, users: [{ id: 7, handle: 'collector', displayName: 'Stale summary' }] }),
    loadProfile: async () => profile })(request('/en/sylloges/collector'))
  assert.equal(result.status, 200)
  const html = await result.text()
  assert.match(html, /<title>Actual collector \| Cardora<\/title>/)
  assert.match(html, /Public collection biography/)
  assert.match(html, /Collection card/)
  assert.match(html, /https:\/\/cardora.gr\/en\/sylloges\/collector/)
  for (const secret of ['Private legal name', 'private@example.test', 'MEDIA-SECRET', 'ENTRY-SECRET', 'NESTED-SECRET', 'PRIVATE-ENTRY', 'purchase_count', 'liked_by_auth_user']) assert.ok(!html.includes(secret), secret)
  const snapshot = JSON.parse(html.match(/id="cardora-public-data">([\s\S]*?)<\/script>/)[1])
  assert.equal(snapshot.publicData.publicProfile.profile.profile_likes_count, 27)
  assert.equal(snapshot.publicData.publicProfile.profile.followers_count, 12)
})

test('private/missing profiles return 404 even with an old public summary; outages return 503', async () => {
  const loadData = async () => ({ ...data, users: [{ id: 7, handle: 'collector', displayName: 'Old public name' }] })
  const missing = await handler({ loadData, loadProfile: async () => null })(request('/el/sylloges/collector'))
  assert.equal(missing.status, 404)
  assert.match(await missing.text(), /content="noindex, follow"/)
  const failed = await handler({ loadData, loadProfile: async () => { throw new Error('Expected profile outage') } })(request('/el/sylloges/collector'))
  assert.equal(failed.status, 503)
  assert.equal(sanitizePublicProfile({ id: 7, handle: 'collector', profile_visibility: 'private' }), null)
})

test('anonymous data cache separates languages, coalesces misses and never caches profiles or errors', async () => {
  const stored = new Map(), calls = []
  const cache = { match: async key => stored.get(key.url)?.clone(), put: async (key, response) => stored.set(key.url, response) }
  let fail = false
  const read = createPublicDataReader({ cache: () => cache, fetchResource: async (url, options) => {
    calls.push({ url, options })
    if (fail) return new Response('', { status: 429 })
    return Response.json({ data: { categories: [], products: [], label: url } })
  } })
  const [first, second] = await Promise.all([read('public-page-data', 'el'), read('public-page-data', 'el')])
  assert.equal(calls.length, 1)
  first.products.push({ id: 'mutated' })
  assert.equal(second.products.length, 0)
  assert.equal((await read('public-page-data', 'el')).products.length, 0)
  await read('public-page-data', 'en')
  assert.equal(calls.length, 2)
  assert.match([...stored.values()][0].headers.get('Cache-Control'), /max-age=30/)
  await read('seo', 'el')
  assert.match([...stored.values()][2].headers.get('Cache-Control'), /max-age=300/)
  await read('profiles/collector', 'el'); await read('profiles/collector', 'el')
  assert.equal(calls.length, 5)
  assert.equal(stored.size, 3)
  for (const path of ['public-listings/sold-card', 'public-listing-paths']) {
    const before = calls.length
    await read(path, 'el'); await read(path, 'el')
    assert.equal(calls.length, before + 2, `${path} must recheck current visibility`)
    assert.equal(stored.size, 3)
  }
  for (const path of ['bootstrap', 'auth/me', 'orders', 'scanner/scan']) await assert.rejects(read(path, 'el'), /Not a public resource/)
  stored.clear(); fail = true
  await assert.rejects(read('public-page-data', 'el'), /429/)
  assert.equal(stored.size, 0)
  fail = false
  await read('public-page-data', 'el')
  assert.equal(stored.size, 1)
  assert.ok(calls.every(call => call.options.credentials === 'omit' && !call.options.headers.Authorization && !call.options.headers.Cookie))
})
