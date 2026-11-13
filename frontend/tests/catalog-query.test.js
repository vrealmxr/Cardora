import test from 'node:test'
import assert from 'node:assert/strict'
import { readCatalogQuery, readPage, pageSearch, changeCatalogQuery } from '../src/utils/catalogQuery.js'
import { getSeoUrls } from '../src/utils/seoUrls.js'

test('deep links retain page, facets, order and search', () => {
  const parsed = readCatalogQuery(new URLSearchParams('page=2&sort=price-asc&condition=Mint&q=Pikachu'), { condition: 'all' })
  assert.equal(parsed.page, 2)
  assert.equal(parsed.sort, 'price-asc')
  assert.equal(parsed.filters.condition, 'Mint')
  assert.equal(parsed.filters.search, 'Pikachu')
  for (const invalid of ['0', '-1', '1.5', '2e2', 'Infinity', '9007199254740992', null]) {
    assert.equal(readPage(invalid), 1)
  }
})

test('paging preserves filters and produces a first-page URL without page=1', () => {
  const query = new URLSearchParams('franchise=Pokemon&sort=price-desc&page=2')
  assert.equal(pageSearch(query, 3), '?franchise=Pokemon&sort=price-desc&page=3')
  assert.equal(pageSearch(query, 1), '?franchise=Pokemon&sort=price-desc')
  assert.equal(query.get('page'), '2')
})

test('filter or sort changes reset pagination and only modify requested fields', () => {
  const query = new URLSearchParams('q=Charizard&condition=Mint&page=4&sort=price-asc')
  const next = changeCatalogQuery(query, { condition: 'all', sort: 'newest' })
  assert.equal(next.toString(), 'q=Charizard')
  assert.equal(query.get('page'), '4')
  assert.equal(changeCatalogQuery(new URLSearchParams('search=old&page=2'), { search: 'new' }).toString(), 'q=new')
})

test('page 2 is self canonical and locale alternatives retain page 2', () => {
  const seo = getSeoUrls('/el/kartes/', '?page=2&utm_source=test&gclid=tracking', 'el')
  assert.equal(seo.canonical, 'https://cardora.gr/el/kartes?page=2')
  assert.equal(seo.alternates.en, 'https://cardora.gr/en/kartes?page=2')
  assert.equal(seo.robots, 'index, follow')
})

test('filtered results remain distinct from the category, without indexing filter combinations', () => {
  const seo = getSeoUrls('/en/kartes', '?franchise=Pokemon&sort=price-asc&page=3&utm_source=x', 'en')
  assert.equal(seo.canonical, 'https://cardora.gr/en/kartes?franchise=Pokemon&sort=price-asc&page=3')
  assert.equal(seo.robots, 'noindex, follow')
  assert.deepEqual(seo.alternates, {})
})

test('non-content and unknown routes are not indexed and URL secrets are not copied to metadata', () => {
  for (const path of ['/el/checkout', '/el/profil', '/el/unknown-test', '/en/epanafora-kodikou']) {
    const seo = getSeoUrls(path, '?token=secret&email=private', path.startsWith('/en') ? 'en' : 'el')
    assert.equal(seo.robots, 'noindex, follow')
    assert.ok(!seo.canonical.includes('?'))
    assert.deepEqual(seo.alternates, {})
  }
  assert.equal(getSeoUrls('/el/anazitisi', '?q=Mew').robots, 'noindex, follow')
})

test('default filters and first page do not generate duplicate canonicals', () => {
  assert.equal(getSeoUrls('/el/kartes', '?page=1&condition=all&sort=newest').canonical, 'https://cardora.gr/el/kartes')
  assert.equal(getSeoUrls('/en', '', 'en').canonical, 'https://cardora.gr/en')
})
