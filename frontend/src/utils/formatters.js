const resolveLocale = (selectedLocale) => {
  let locale = selectedLocale
  if (!locale && typeof window !== 'undefined') {
    try { locale = window.localStorage.getItem('cardora-locale') } catch { /* Optional storage. */ }
  }

  return locale === 'en' ? 'en-US' : 'el-GR'
}

// Intl instances are immutable. Reuse the eight locale/type combinations instead
// of constructing a formatter for every card, count and date on each request.
const currency = new Map()
const number = new Map()
const date = new Map()
const dateTime = new Map()
const formatter = (cache, locale, create) => {
  const language = resolveLocale(locale)
  if (!cache.has(language)) cache.set(language, create(language))
  return cache.get(language)
}

export const formatCurrency = (value, locale) => formatter(currency, locale, language =>
  new Intl.NumberFormat(language, { style: 'currency', currency: 'EUR', maximumFractionDigits: 2 })).format(value)

export const formatNumber = (value, locale) => formatter(number, locale, language =>
  new Intl.NumberFormat(language, { maximumFractionDigits: 0 })).format(value)

export const formatDate = (value, locale) => formatter(date, locale, language =>
  new Intl.DateTimeFormat(language, { timeZone: 'Europe/Athens', day: '2-digit', month: 'short', year: 'numeric' })).format(new Date(value))

export const formatShortDateTime = (value, locale) => formatter(dateTime, locale, language =>
  new Intl.DateTimeFormat(language, { timeZone: 'Europe/Athens', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })).format(new Date(value))
