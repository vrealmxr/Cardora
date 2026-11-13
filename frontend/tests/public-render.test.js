import test from 'node:test'
import assert from 'node:assert/strict'
import { renderPublicPage } from '../dist-ssr/entry-server.js'
import { catalogValueLabel } from '../src/utils/catalogLabels.js'
import { JSDOM } from './runtime/node_modules/jsdom/lib/api.js'

const categories = [{
  id: 'cards', slug: 'kartes', name: 'Κάρτες', description: 'Κάρτες για συλλέκτες.',
  metrics: { listings: 45, sold: 0, verified: 0 }, spotlightFilters: [],
}]
const products = Array.from({ length: 45 }, (_, index) => ({
  id: index + 1, slug: `test-card-${index + 1}`, title: `Test card ${index + 1}`,
  categoryId: 'cards', price: 10 + index, condition: 'Near Mint', rarity: 'Common',
  sellerId: 1, listedAt: '2026-10-23T00:00:00Z', status: 'active',
  tags: [], highlights: [], media: [],
}))
const publicData = { categories, products, faqGroups: [{
  title: 'Αγορές', items: [{ question: 'Πού βλέπω την κατάσταση;', answer: 'Στην περιγραφή της αγγελίας.' }],
}] }

test('known catalogue choices localize without rewriting seller content or raw filter values', async () => {
  assert.equal(catalogValueLabel('typeLabel', 'Μεμονωμένες κάρτες', 'en'), 'Single Cards')
  assert.equal(catalogValueLabel('rarity', 'Common', 'el'), 'Κοινή (Common)')
  assert.equal(catalogValueLabel('language', 'Αγγλικά'.normalize('NFD'), 'en'), 'English')
  for (const field of ['title', 'description', 'series', 'authenticity']) {
    assert.equal(catalogValueLabel(field, 'Άλλο', 'en'), 'Άλλο')
  }
  assert.equal(catalogValueLabel('brand', 'Custom ελληνικό brand', 'en'), 'Custom ελληνικό brand')
  const data = { ...publicData, products: [
    { ...products[0], title: 'Η συλλογή μου', typeLabel: 'Μεμονωμένες κάρτες', series: 'Μεμονωμένες κάρτες', rarity: 'Σπάνια (Rare)', language: 'Αγγλικά', franchise: 'Άλλο' },
    { ...products[1], typeLabel: 'Κουτί Booster', rarity: 'Κοινή (Common)' },
  ] }
  const original = JSON.stringify(data)
  const query = new URLSearchParams({ productType: 'Μεμονωμένες κάρτες' })
  const filtered = await renderPublicPage(`/en/kartes?${query}`, data)
  const doc = new JSDOM(filtered.html).window.document
  assert.ok([...doc.querySelectorAll('button')].some(b => b.textContent.trim() === 'Single Cards1'))
  assert.ok(doc.querySelector('a[href="/en/proion/test-card-1"]'))
  assert.ok(!doc.querySelector('a[href="/en/proion/test-card-2"]'), 'Raw stored filter still selects the same item')
  assert.ok(doc.body.textContent.includes('Η συλλογή μου'), 'Seller title remains original')
  assert.ok(!doc.body.textContent.includes('Σπάνια (Rare)'))
  assert.ok(!doc.body.textContent.includes('Μεμονωμένες κάρτες'), 'A repeated raw series/type does not duplicate the translated tag')
  const detail = await renderPublicPage('/en/proion/test-card-1', data)
  assert.ok(new JSDOM(detail.html).window.document.body.textContent.includes('LanguageEnglish'))
  assert.equal(JSON.stringify(data), original, 'Shared source snapshot is unchanged')
  for (const [locale, term] of [['en', 'Single Cards'], ['el', 'Μεμονωμένες κάρτες']]) {
    const search = await renderPublicPage(`/${locale}/anazitisi?q=${encodeURIComponent(term)}`, data)
    assert.ok(search.html.includes(`href="/${locale}/proion/test-card-1"`), 'Search finds the displayed type in either language')
  }
})

test('public trade listing and sign-in links retain the page language', async () => {
  for (const locale of ['el', 'en']) {
    const result = await renderPublicPage(`/${locale}/kliroseis`, { ...publicData,
      products: [{ ...products[0], saleFormat: 'trade' }],
    })
    const doc = new JSDOM(result.html).window.document
    const hrefs = [...doc.querySelectorAll('a[href]')].map(a => a.getAttribute('href'))
    assert.ok(hrefs.includes(`/${locale}/proion/test-card-1`))
    assert.ok(hrefs.includes(`/${locale}/eisodos`))
    assert.ok(!hrefs.some(href => /^\/(?:proion\/|eisodos$|kliroseis$)/.test(href)))
  }
})

test('category statistics follow the browsable catalogue and unique verified sellers, not stale backend counts', async () => {
  const data = { ...publicData, products: products.slice(0, 2),
    categories: [{ ...categories[0], metrics: { listings: 9999, verified: 8888, sold: 7 } }],
    users: [{ id: 1, displayName: 'Collector', handle: 'collector', verified: true }],
  }
  for (const route of ['/en/kartes', '/en']) {
    const result = await renderPublicPage(route, data)
    assert.ok(!result.html.includes('9999') && !result.html.includes('8888'), route)
    const text = result.html.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ')
    assert.match(text, /Listings2Orders7Verified sellers(?: here)?1/, route)
  }
  const empty = await renderPublicPage('/en/kartes', { ...data, products: [] })
  const text = empty.html.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ')
  assert.match(text, /Listings0Orders7Verified sellers here0/)
  assert.equal(data.categories[0].metrics.listings, 9999, 'Shared snapshot remains untouched')
})

test('public rendering is network-free, includes HTML and preserves admin metadata', async () => {
  const originalFetch = globalThis.fetch
  globalThis.fetch = () => { throw new Error('SSR must not initiate API requests') }
  try {
    const result = await renderPublicPage('/el/kartes?page=2', publicData, {
      'category.cards': { metaTitle: 'Κάρτες στην Cardora', metaDescription: 'Περιγραφή από διαχειριστή', h1: 'Συλλεκτικές κάρτες' },
    })
    assert.equal(result.head.title, 'Κάρτες στην Cardora')
    assert.equal(result.head.description, 'Περιγραφή από διαχειριστή')
    assert.equal(result.head.canonical, 'https://cardora.gr/el/kartes?page=2')
    assert.match(result.html, /<h1[^>]*>Συλλεκτικές κάρτες<\/h1>/)
    assert.ok(result.html.includes('href="/el/kartes?page=3"'))
    assert.ok(result.html.includes('href="/el/proion/test-card-21"'))
    assert.ok(!result.html.includes('href="/el/proion/test-card-1"'))
    assert.ok(!result.html.includes('z-[140]'), 'SSR must not obscure content with the boot overlay')
  } finally { globalThis.fetch = originalFetch }
})

test('FAQ answers and primary heading exist before JavaScript executes', async () => {
  const result = await renderPublicPage('/el/faq', publicData)
  assert.match(result.html, /<h1[\s>]/)
  assert.ok(result.html.includes('<details'))
  assert.ok(result.html.includes('Στην περιγραφή της αγγελίας.'))
  assert.ok(result.html.includes('href="/el/epikoinonia"'))
})

test('public verification FAQ describes the verified process without promising unverified provider settings', async () => {
  for (const [locale, question, phrase] of [
    ['el', 'Πώς γίνεται η επαλήθευση Cardora;', 'δεν αποτελεί πιστοποίηση γνησιότητας'],
    ['en', 'How does Cardora verification work?', 'does not certify the authenticity'],
  ]) {
    const result = await renderPublicPage(`/${locale}/faq`, { ...publicData, faqGroups: [{ title: 'Verification', items: [
      { question, answer: 'LEGACY_PROVIDER_SETTINGS' },
    ] }] })
    assert.ok(!result.html.includes('LEGACY_PROVIDER_SETTINGS'))
    assert.ok(result.html.includes(phrase))
    assert.ok(result.html.includes('Didit') && result.html.includes('Stripe'))
  }
})

test('FAQ distinguishes payment release from bank payout and keeps new API questions', async () => {
  const cases = [
    ['el', 'Πότε πληρώνεται ο πωλητής;', 'Μέσω τίνος γίνονται το checkout και τα seller payouts;', 'τραπεζική κατάθεση την ίδια ημέρα'],
    ['en', 'When does the seller receive the money?', 'What powers checkout and seller payouts?', 'bank deposit on the same day'],
  ]
  for (const [locale, payout, payment, distinction] of cases) {
    const result = await renderPublicPage(`/${locale}/faq`, { ...publicData, faqGroups: [{ title: 'Payments', items: [
      { question: payout, answer: 'LEGACY_PAYOUT_COPY' }, { question: payment, answer: 'LEGACY_PAYMENT_COPY' },
      { question: 'Additional support question', answer: 'New API content is retained.' },
    ] }] })
    assert.ok(!result.html.includes('LEGACY_'))
    assert.ok(result.html.includes(distinction))
    assert.ok(result.html.includes('New API content is retained.'))
    assert.equal((result.html.match(/<details[\s>]/g) || []).length, 11)
    assert.ok(result.html.includes(`href="/${locale}/odigos-polisis"`))
    assert.ok(result.html.includes(`href="/${locale}/epistrofes-kai-diafores"`))
  }
})

test('search has localized metadata and one H1, with empty results and overflow distinguished', async () => {
  for (const locale of ['el', 'en']) {
    const result = await renderPublicPage(`/${locale}/anazitisi?q=test`, publicData)
    assert.equal((result.html.match(/<h1[\s>]/g) || []).length, 1)
    assert.equal(result.head.title, locale === 'el' ? 'Αποτελέσματα αναζήτησης | Cardora' : 'Search results | Cardora')
    assert.equal(result.head.robots, 'noindex, follow')
    assert.equal(result.head.status, 200)
    const empty = await renderPublicPage(`/${locale}/anazitisi?q=absent-qa`, publicData)
    assert.equal(empty.head.status, 200)
    const overflow = await renderPublicPage(`/${locale}/anazitisi?q=test&page=999`, publicData)
    assert.equal(overflow.head.status, 404)
  }
})

test('English category links stay English and server requests do not share metadata', async () => {
  const [english, greek] = await Promise.all([
    renderPublicPage('/en/kartes', publicData, { 'category.cards': { metaTitle: 'Cards | Cardora' } }),
    renderPublicPage('/el/faq', publicData, { faq: { metaTitle: 'Βοήθεια | Cardora' } }),
  ])
  assert.equal(english.head.title, 'Cards | Cardora')
  assert.equal(greek.head.title, 'Βοήθεια | Cardora')
  assert.ok(english.html.includes('href="/en/proion/test-card-1"'))
  assert.ok(english.html.includes('href="/en/kartes?page=2"'))
})

test('listing metadata identifies the actual item and uses its public photo', async () => {
  const data = {
    ...publicData,
    users: [{ id: 1, handle: 'test-seller', displayName: 'Test seller', rating: 0, verified: false }],
    products: [{ ...products[0], description: 'A specific card description.',
      media: [{ url: 'https://cardora.gr/storage/test-card.jpg', thumbUrl: '/test-card-preview.webp',
        width: 1200, height: 1800, thumbWidth: 400, thumbHeight: 600, type: 'image', alt: 'Test card' }],
    }],
  }
  const result = await renderPublicPage('/en/proion/test-card-1', data)
  assert.equal(result.head.title, 'Test card 1 | Cardora')
  assert.equal(result.head.image, 'https://cardora.gr/storage/test-card.jpg')
  assert.equal(result.head.description, 'A specific card description.')
  assert.ok(result.html.includes('href="/en/kartes"'))
  const original = (result.html.match(/<img\b[^>]*>/g) || []).find(tag => tag.includes('src="https://cardora.gr/storage/test-card.jpg"'))
  assert.match(original, /width="1200" height="1800"/)
  assert.match(original, /loading="eager" fetch[Pp]riority="high"/)
  const catalogue = await renderPublicPage('/en/kartes', data)
  assert.match(catalogue.html, /srcSet="\/test-card-preview.webp 400w, https:\/\/cardora.gr\/storage\/test-card.jpg 1200w" sizes="auto, 100vw"/)
})

test('collectibles hub derives live counts, renders eight answers and paginates 24 listings', async () => {
  const result = await renderPublicPage('/el/syllektika?page=2', {
    ...publicData, categories: [{ ...categories[0], metrics: { listings: 9999 } }],
  })
  assert.equal(result.head.canonical, 'https://cardora.gr/el/syllektika?page=2')
  assert.equal(result.head.robots, 'index, follow')
  assert.equal((result.html.match(/<details[\s>]/g) || []).length, 8)
  assert.match(result.html.replace(/<[^>]*>/g, ''), /45 διαθέσιμες αγγελίες/)
  assert.ok(!result.html.includes('9999'))
  assert.ok(result.html.includes('href="/el/proion/test-card-21"'))
  assert.ok(!result.html.includes('href="/el/proion/test-card-45"'))
  assert.ok(result.html.includes('href="/el/odigos-polisis"'))
  assert.ok(result.html.includes('href="/el/katastasi-kai-grading"'))
})

test('selling and condition pages render both languages with correct links and headings', async () => {
  for (const locale of ['el', 'en']) {
    for (const path of ['odigos-polisis', 'katastasi-kai-grading']) {
      const result = await renderPublicPage(`/${locale}/${path}`, publicData)
      assert.equal((result.html.match(/<h1[\s>]/g) || []).length, 1)
      assert.ok(result.html.includes(`href="/${locale}/syllektika"`))
      assert.equal(result.head.locale, locale)
      assert.equal(result.head.status, 200)
    }
  }
})
