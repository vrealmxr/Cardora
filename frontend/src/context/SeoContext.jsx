import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import { cardoraService } from '@/services/cardoraService'
import { useI18nContext } from '@/context/I18nContext'

const SeoContext = createContext(null)

export function SeoProvider({ children }) {
  const { locale } = useI18nContext()
  const [settingsByLocale, setSettingsByLocale] = useState({})

  useEffect(() => {
    if (settingsByLocale[locale]) return

    let cancelled = false

    cardoraService
      .getSeoSettings()
      .then((data) => {
        if (!cancelled && data) {
          setSettingsByLocale((prev) => ({ ...prev, [locale]: data }))
        }
      })
      .catch(() => {
        // Admin-editable overrides are optional — pages keep their hardcoded fallback copy.
      })

    return () => {
      cancelled = true
    }
  }, [locale, settingsByLocale])

  const value = useMemo(() => ({ settings: settingsByLocale[locale] ?? null }), [settingsByLocale, locale])

  return <SeoContext.Provider value={value}>{children}</SeoContext.Provider>
}

export function useSeo(pageKey) {
  const context = useContext(SeoContext)

  if (!context) {
    throw new Error('useSeo must be used within SeoProvider')
  }

  return context.settings?.[pageKey] ?? null
}
