export const CATALOG_FILTER_KEYS = [
  'price', 'condition', 'rarity', 'franchise', 'brand', 'productType',
  'graded', 'availability', 'sellerRating',
]
export const CATALOG_SORTS = ['newest', 'price-asc', 'price-desc', 'rating']

export function readPage(value) {
  if (!/^[1-9]\d*$/.test(String(value ?? ''))) return 1
  const number = Number(value)
  return Number.isSafeInteger(number) ? number : 1
}

export function readCatalogQuery(params, defaults) {
  return {
    page: readPage(params.get('page')),
    sort: CATALOG_SORTS.includes(params.get('sort')) ? params.get('sort') : 'newest',
    category: params.get('category') || 'all',
    filters: {
      ...defaults,
      search: params.get('q') ?? params.get('search') ?? '',
      ...Object.fromEntries(CATALOG_FILTER_KEYS.map((key) => [key, params.get(key) ?? defaults[key]])),
    },
  }
}

export function pageSearch(params, page) {
  const next = new URLSearchParams(params)
  next.delete('page')
  if (readPage(page) > 1) next.set('page', String(readPage(page)))
  const query = next.toString()
  return query ? `?${query}` : ''
}

export function changeCatalogQuery(params, changes, allValue = 'all') {
  const next = new URLSearchParams(params)
  next.delete('page')
  for (const [key, value] of Object.entries(changes)) {
    const param = key === 'search' ? 'q' : key
    if (key === 'search') next.delete('search')
    if (value == null || String(value).trim() === '' || String(value) === String(allValue)
      || (key === 'sort' && value === 'newest')) {
      next.delete(param)
    } else {
      next.set(param, String(value).trim())
    }
  }
  return next
}
