import test from 'node:test'
import assert from 'node:assert/strict'
import { productStructuredData } from '../src/utils/structuredData.js'
import { renderPublicPage } from '../dist-ssr/entry-server.js'

const categories = [{ id: 'cards', slug: 'kartes', name: 'Cards', metrics: {}, spotlightFilters: [] }]
const product = { id: 1, slug: 'blue-sapphire', title: 'Blue Sapphire Falcon', categoryId: 'cards', stock: 1, price: 12.5, saleFormat: 'fixed_price', status: 'active', media: [{ type: 'image', url: '/storage/card.jpg' }], tags: [], highlights: [] }
const scripts = html => [...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)].map(match => JSON.parse(match[1]))

test('legacy sealed packaging and raw-condition notes are not presented as a grading company or certified score', async () => {
  const legacy = { ...product, categoryId: 'comics', gradedCompany: 'Sealed', grade: 'Near Mint' }
  const category = { ...categories[0], id: 'comics', slug: 'komik-vivlia', name: 'Comics' }
  for (const locale of ['en', 'el']) {
    const result = await renderPublicPage(`/${locale}/proion/${legacy.slug}`, { categories: [category], products: [legacy] })
    assert.ok(result.html.includes(locale === 'en' ? 'Seller’s condition note' : 'Σχόλιο κατάστασης πωλητή'))
    assert.ok(result.html.includes(locale === 'en' ? 'Packaging' : 'Συσκευασία'))
    assert.ok(!result.html.includes(locale === 'en' ? 'Grading company' : 'Εταιρεία grading'))
    assert.ok(result.html.includes('Near Mint'))
  }
  const graded = await renderPublicPage('/en/proion/blue-sapphire', { categories, products: [{ ...product, gradedCompany: 'PSA', grade: '10 GEM MT' }] })
  assert.ok(graded.html.includes('Grade on the label'))
  assert.ok(graded.html.includes('10 GEM MT'))
})

test('fixed-price product offers use actual prices and stock, never seller ratings or trade valuations', () => {
  const schema = productStructuredData(product, 'en')
  assert.equal(schema.offers.price, '12.50')
  assert.equal(schema.offers.priceCurrency, 'EUR')
  assert.equal(schema.offers.availability, 'https://schema.org/InStock')
  assert.equal(schema.offers.url, 'https://cardora.gr/en/proion/blue-sapphire')
  assert.deepEqual(schema.image, ['https://cardora.gr/storage/card.jpg'])
  assert.equal(schema.aggregateRating, undefined)
  assert.equal(productStructuredData({ ...product, status: 'sold', stock: 0 }, 'en').offers.availability, 'https://schema.org/SoldOut')
  for (const changes of [{ saleFormat: 'trade' }, { saleFormat: 'auction' }, { stock: 0 }, { status: 'rejected' }, { lot: { allowsIndividualPurchase: true } }, { price: -2 }, { price: null }]) {
    assert.equal(productStructuredData({ ...product, ...changes }, 'en').offers, undefined)
  }
  assert.equal(productStructuredData({ ...product, media: [{ url: 'javascript:alert(1)' }, {}, { type: 'video', url: '/video.mp4' }] }, 'en').image, undefined)
})

test('rendered product JSON-LD matches visible content, escapes script delimiters and includes breadcrumbs', async () => {
  const dangerous = '</script><script>alert("seller")</script>'
  const result = await renderPublicPage('/en/proion/blue-sapphire', { categories, products: [{ ...product, title: dangerous }] })
  const schema = scripts(result.html)
  const item = schema.find(item => item['@type'] === 'Product')
  assert.equal(item.name, dangerous)
  assert.ok(!result.html.includes('<script>alert'))
  const breadcrumbs = schema.find(item => item['@type'] === 'BreadcrumbList')
  assert.equal(breadcrumbs.itemListElement.length, 3)
  assert.equal(breadcrumbs.itemListElement[1].item, 'https://cardora.gr/en/kartes')
  assert.equal(breadcrumbs.itemListElement[2].name, dangerous)
  const missing = await renderPublicPage('/en/proion/missing', { categories, products: [product] })
  assert.equal(scripts(missing.html).length, 0)
})

test('collection markup describes only the rendered page, and searching still builds its lazy index', async () => {
  const products = Array.from({ length: 45 }, (_, index) => ({ ...product, id: index + 1, slug: `card-${index + 1}`, title: `Card ${index + 1}`, listedAt: '2026-10-23T00:00:00Z' }))
  products[0] = { ...product, id: 1 }
  const result = await renderPublicPage('/en/syllektika?page=2', { categories, products })
  const schema = scripts(result.html).find(item => item['@type'] === 'CollectionPage')
  assert.equal(schema.url, 'https://cardora.gr/en/syllektika?page=2')
  assert.equal(schema.mainEntity.numberOfItems, 21)
  assert.equal(schema.mainEntity.itemListElement[0].position, 25)
  assert.ok(result.html.includes(schema.mainEntity.itemListElement[0].name))
  const searched = await renderPublicPage('/en/kartes?q=Sapphire', { categories, products })
  assert.ok(searched.html.includes('Blue Sapphire Falcon'))
  assert.ok(!searched.html.includes('href="/en/proion/card-2"'))
  assert.equal(searched.head.robots, 'noindex, follow')
})
