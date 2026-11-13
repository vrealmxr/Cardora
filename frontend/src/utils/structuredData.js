import { SITE_ORIGIN } from './seoUrls.js'

const context = 'https://schema.org'
const pageUrl = (locale, path = '') => `${SITE_ORIGIN}/${locale === 'en' ? 'en' : 'el'}${path ? '/' + path : ''}`
const imageUrl = value => {
  if (typeof value !== 'string' || !value.trim()) return null
  try { const url = new URL(value, SITE_ORIGIN); return /^https?:$/.test(url.protocol) ? url.href : null } catch { return null }
}

export function siteStructuredData(locale) {
  return { '@context': context, '@graph': [
    { '@type': 'Organization', '@id': `${SITE_ORIGIN}/#organization`, name: 'Cardora', legalName: 'VRealm I.K.E.',
      url: SITE_ORIGIN, logo: `${SITE_ORIGIN}/asset.php?f=logo-20260716-compact.png`, email: 'support@cardora.gr',
      sameAs: ['https://www.instagram.com/cardora.gr', 'https://www.tiktok.com/@cardora.gr', 'https://discord.gg/cPzWg6S34'] },
    { '@type': 'WebSite', '@id': `${SITE_ORIGIN}/#website`, name: 'Cardora', url: SITE_ORIGIN,
      inLanguage: locale === 'en' ? 'en' : 'el', publisher: { '@id': `${SITE_ORIGIN}/#organization` } },
  ] }
}

export function productStructuredData(product, locale) {
  if (!product?.title || !product.slug) return null
  const url = pageUrl(locale, `proion/${encodeURIComponent(product.slug)}`)
  const images = (product.media || []).filter(item => !item.type || item.type === 'image')
    .map(item => imageUrl(typeof item === 'string' ? item : item.url)).filter(Boolean)
  const schema = { '@context': context, '@type': 'Product', '@id': `${url}#product`, url, name: product.title,
    ...(images.length ? { image: [...new Set(images)] } : {}),
    ...(product.description || product.shortDescription ? { description: product.description || product.shortDescription } : {}),
  }
  const price = Number(product.price)
  // Trade valuations, auction bids and individual-card lot totals are not fixed offers.
  const fixed = !product.saleFormat || product.saleFormat === 'fixed_price'
  const active = !product.status || ['active', 'published'].includes(product.status)
  const sold = product.status === 'sold'
  if (fixed && ((active && Number(product.stock) > 0) || sold) && Number.isFinite(price) && price > 0 && !product.lot?.allowsIndividualPurchase) {
    schema.offers = { '@type': 'Offer', url, price: price.toFixed(2), priceCurrency: 'EUR', availability: `${context}/${sold ? 'SoldOut' : 'InStock'}` }
  }
  return schema
}

export function collectionStructuredData({ title, canonical, products, locale, start = 0 }) {
  return { '@context': context, '@type': 'CollectionPage', '@id': `${canonical}#collection`, name: title, url: canonical,
    mainEntity: { '@type': 'ItemList', numberOfItems: products.length,
      itemListElement: products.map((product, index) => ({ '@type': 'ListItem', position: start + index + 1,
        name: product.title, url: pageUrl(locale, `proion/${encodeURIComponent(product.slug)}`) })) },
  }
}

export function breadcrumbStructuredData(items, canonical, localize) {
  items = items.filter(item => item.label)
  if (items.length < 2) return null
  return { '@context': context, '@type': 'BreadcrumbList', itemListElement: items.map((item, index) => ({
    '@type': 'ListItem', position: index + 1, name: item.label,
    item: item.href ? new URL(localize(item.href), SITE_ORIGIN).href : canonical,
  })) }
}
