import { CATALOG_FILTER_KEYS, readPage } from './catalogQuery.js'

export const SITE_ORIGIN = 'https://cardora.gr'
export const DEFAULT_SHARE_IMAGE = `${SITE_ORIGIN}/asset.php?f=home-banner-20260716-960.jpg`

export function shareImageUrl(value) {
  try {
    const url = new URL(value || DEFAULT_SHARE_IMAGE, SITE_ORIGIN)
    return ['https:', 'http:'].includes(url.protocol) ? url.href : DEFAULT_SHARE_IMAGE
  } catch { return DEFAULT_SHARE_IMAGE }
}
const CATEGORY_PATHS = new Set(['/kartes', '/figoures', '/komik-vivlia', '/diafora'])
const PUBLIC_PATHS = new Set([
  '/syllektika', '/odigos-polisis', '/katastasi-kai-grading',
  '/', '/cardora', '/faq', '/epikoinonia', '/blog', '/tutorials',
  '/kentro-ypostiriksis', '/oroi-xrisis', '/politiki-aporritou', '/politiki-cookies',
  '/dsa-notice-action', '/epistrofes-kai-diafores', '/apagorevmena-antikeimena',
  '/kliroseis',
])

export function getSeoUrls(pathname, search = '', locale = 'el') {
  const lang = locale === 'en' ? 'en' : 'el'
  const path = ('/' + pathname.replace(/^\/(el|en)(?=\/|$)/, '').replace(/^\/+|\/+$/g, ''))
  const source = new URLSearchParams(search)
  const params = new URLSearchParams()
  const catalog = CATEGORY_PATHS.has(path) || path === '/anazitisi'
  if (catalog) {
    // Keep meaningful facets separate; never canonicalize a filtered result to an
    // unrelated unfiltered page. Tracking parameters do not identify content.
    for (const key of ['q', 'category', ...CATALOG_FILTER_KEYS, 'sort']) {
      const value = source.get(key) ?? (key === 'q' ? source.get('search') : null)
      if (value?.trim() && value !== 'all' && !(key === 'sort' && value === 'newest')) {
        params.set(key, value.trim())
      }
    }
  }
  if ((catalog || path === '/syllektika') && readPage(source.get('page')) > 1) params.set('page', String(readPage(source.get('page'))))
  const filtered = [...params.keys()].some((key) => key !== 'page')
  const publicRoute = CATEGORY_PATHS.has(path) || PUBLIC_PATHS.has(path)
    || /^\/(proion|blog|tutorials|sylloges)\/[^/]+$/.test(path)
  const query = params.size ? `?${params}` : ''
  const url = (language) => `${SITE_ORIGIN}/${language}${path === '/' ? '' : path}${query}`
  return {
    canonical: url(lang),
    alternates: publicRoute && !filtered ? { el: url('el'), en: url('en'), 'x-default': url('el') } : {},
    robots: publicRoute && !filtered ? 'index, follow' : 'noindex, follow',
  }
}
