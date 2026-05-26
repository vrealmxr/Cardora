const resolveLocale = () => {
  const locale =
    typeof window !== 'undefined' ? window.localStorage.getItem('cardora-locale') : null

  return locale === 'en' ? 'en-US' : 'el-GR'
}

export const formatCurrency = (value) =>
  new Intl.NumberFormat(resolveLocale(), {
    style: 'currency',
    currency: 'EUR',
    maximumFractionDigits: 2,
  }).format(value)

export const formatNumber = (value) =>
  new Intl.NumberFormat(resolveLocale(), { maximumFractionDigits: 0 }).format(value)

export const formatDate = (value) =>
  new Intl.DateTimeFormat(resolveLocale(), {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(value))

export const formatShortDateTime = (value) =>
  new Intl.DateTimeFormat(resolveLocale(), {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
