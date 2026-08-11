import { Navigate, useLocation } from 'react-router-dom'
import {
  DEFAULT_LOCALE,
  getLocaleFromPathname,
  isValidLocale,
  localizePath,
  stripLocaleFromPathname,
} from '@/utils/helpers'

function LocaleRedirect() {
  const location = useLocation()
  const pathnameLocale = getLocaleFromPathname(location.pathname)
  const storedLocale =
    typeof window !== 'undefined' ? window.localStorage.getItem('cardora-locale') : null
  const fallbackLocale = isValidLocale(storedLocale) ? storedLocale : DEFAULT_LOCALE
  const targetLocale = pathnameLocale ?? fallbackLocale
  const targetPath = localizePath(stripLocaleFromPathname(location.pathname), targetLocale)

  return <Navigate to={`${targetPath}${location.search}${location.hash}`} replace />
}

export default LocaleRedirect
