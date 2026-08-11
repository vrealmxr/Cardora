import clsx from 'clsx'

export const cn = (...inputs) => clsx(inputs)
export const VALID_LOCALES = ['el', 'en']
export const DEFAULT_LOCALE = 'el'

export const isValidLocale = (value = '') => VALID_LOCALES.includes(String(value).toLowerCase())

export const getLocaleFromPathname = (pathname = '/') => {
  const [, firstSegment = ''] = String(pathname).split('/')
  const normalized = firstSegment.toLowerCase()

  return isValidLocale(normalized) ? normalized : null
}

export const stripLocaleFromPathname = (pathname = '/') => {
  const normalizedPath = pathname.startsWith('/') ? pathname : `/${pathname}`
  const locale = getLocaleFromPathname(normalizedPath)

  if (!locale) {
    return normalizedPath || '/'
  }

  const stripped = normalizedPath.replace(new RegExp(`^/${locale}(?=/|$)`), '')
  return stripped || '/'
}

export const localizePath = (path = '/', locale = DEFAULT_LOCALE) => {
  const safeLocale = isValidLocale(locale) ? locale : DEFAULT_LOCALE
  const normalizedPath = stripLocaleFromPathname(path || '/')

  if (normalizedPath === '/') {
    return `/${safeLocale}`
  }

  return `/${safeLocale}${normalizedPath}`
}

export const getInitials = (name = '') =>
  name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')

export const getUserDisplayName = (user) =>
  user?.displayName ??
  user?.display_name ??
  user?.nickname ??
  user?.handle ??
  user?.name ??
  ''

export const toSlug = (value = '') =>
  value
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')

export const getCategoryRoute = (slug, locale = DEFAULT_LOCALE) => {
  const map = {
    cards: '/kartes',
    figures: '/figoures',
    comics: '/komik-vivlia',
    misc: '/diafora',
  }

  return localizePath(map[slug] ?? '/', locale)
}

export const getCollectorProfileRoute = (handle = '', locale = DEFAULT_LOCALE) =>
  localizePath(`/sylloges/${handle}`, locale)

export const getStatusTone = (status = '') => {
  const normalized = String(status)
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')

  if (
    normalized.includes('released') ||
    normalized.includes('refunded') ||
    normalized.includes('completed') ||
    normalized.includes('approved') ||
    normalized.includes('verified') ||
    normalized.includes('sold')
  ) {
    return 'success'
  }

  if (
    normalized.includes('disputed') ||
    normalized.includes('cancel') ||
    normalized.includes('rejected') ||
    normalized.includes('failed')
  ) {
    return 'danger'
  }

  if (
    normalized.includes('pending') ||
    normalized.includes('review') ||
    normalized.includes('draft') ||
    normalized.includes('hold')
  ) {
    return 'warning'
  }

  if (
    normalized.includes('paid') ||
    normalized.includes('shipped') ||
    normalized.includes('delivery') ||
    normalized.includes('available')
  ) {
    return 'info'
  }

  return 'gold'
}

export const priceInRange = (price, range) => {
  if (!range) return true

  const normalized = String(range).trim()

  if (['Όλες', 'All'].includes(normalized)) return true
  if (['Έως 50€', 'Up to €50'].includes(normalized)) return price <= 50
  if (['50€ - 150€', '€50 - €150'].includes(normalized)) return price >= 50 && price <= 150
  if (['150€ - 500€', '€150 - €500'].includes(normalized)) return price >= 150 && price <= 500
  if (['500€ - 1.500€', '€500 - €1,500'].includes(normalized)) return price >= 500 && price <= 1500
  if (['1.500€+', '€1,500+'].includes(normalized)) return price >= 1500

  return true
}

