import { createContext, useContext, useEffect, useState } from 'react'
import { translations } from '@/i18n/translations'
import { normalizePotentialMojibake } from '@/utils/textEncoding'

const STORAGE_KEY = 'cardora-locale'
const I18nContext = createContext(null)

function resolveMessage(locale, key) {
  return key.split('.').reduce((value, part) => value?.[part], translations[locale])
}

export function I18nProvider({ children }) {
  const [locale, setLocale] = useState(() => window.localStorage.getItem(STORAGE_KEY) || 'el')

  useEffect(() => {
    document.documentElement.lang = locale
    window.localStorage.setItem(STORAGE_KEY, locale)
  }, [locale])

  const t = (key, fallback = key) => {
    const message = resolveMessage(locale, key) ?? fallback
    return typeof message === 'string' ? normalizePotentialMojibake(message) : message
  }

  return (
    <I18nContext.Provider
      value={{
        locale,
        setLocale,
        t,
      }}
    >
      {children}
    </I18nContext.Provider>
  )
}

export function useI18nContext() {
  const context = useContext(I18nContext)

  if (!context) {
    throw new Error('useI18nContext must be used within I18nProvider')
  }

  return context
}
