import { createContext, useCallback, useContext, useEffect, useLayoutEffect, useMemo, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { translations } from '@/i18n/translations'
import {
  DEFAULT_LOCALE,
  getLocaleFromPathname,
  isValidLocale,
} from '@/utils/helpers'
import { DEFAULT_SHARE_IMAGE, getSeoUrls } from '@/utils/seoUrls'
import { normalizePotentialMojibake } from '@/utils/textEncoding'

const STORAGE_KEY = 'cardora-locale'
const I18nContext = createContext(null)
const SEO_COPY = {
  el: {
    title: 'Cardora',
    description:
      'Η Cardora είναι premium marketplace για κάρτες, φιγούρες, κόμικς και συλλεκτικά με ασφαλές checkout, verification και αποστολές.',
    ogLocale: 'el_GR',
  },
  en: {
    title: 'Cardora',
    description:
      'Cardora is a premium marketplace for cards, figures, comics and collectibles with protected checkout, verification and parcel shipping.',
    ogLocale: 'en_US',
  },
}

function resolveMessage(locale, key) {
  return key.split('.').reduce((value, part) => value?.[part], translations[locale])
}

export function I18nProvider({ children }) {
  const location = useLocation()
  const [preferredLocale, setLocale] = useState(() => {
    if (typeof window !== 'undefined') {
      try {
        const storedLocale = window.localStorage.getItem(STORAGE_KEY)
        if (isValidLocale(storedLocale)) return storedLocale
      } catch {
        // Browsing remains available when storage is disabled.
      }
    }
    return DEFAULT_LOCALE
  })
  const locale = getLocaleFromPathname(location.pathname) || preferredLocale

  useEffect(() => {
    document.documentElement.lang = locale
    try { window.localStorage.setItem(STORAGE_KEY, locale) } catch { /* Optional preference storage. */ }
  }, [locale, location.pathname])

  // Layout effect (not a regular effect): this sets the generic per-locale
  // default title/meta *before* any page's <PageSeo> override effect runs.
  // Passive effects fire child-first then parent-last within a commit, so a
  // plain useEffect here would always win the race and stomp PageSeo's
  // title whenever the target locale's SEO data was already cached (no
  // async fetch gap left for PageSeo to re-apply itself afterwards) --
  // exactly the "toggling language sometimes leaves the wrong title/H1/H2
  // until a hard reload" bug reported live on cardora.gr 2026-09-29.
  // Layout effects always run before passive effects across the whole
  // tree, so this default reliably loses to PageSeo's override either way.
  useLayoutEffect(() => {
    const seo = SEO_COPY[locale] ?? SEO_COPY.el

    document.title = seo.title

    const upsertMeta = (selector, attributes) => {
      let meta = document.head.querySelector(selector)

      if (!meta) {
        meta = document.createElement('meta')
        document.head.appendChild(meta)
      }

      Object.entries(attributes).forEach(([key, value]) => {
        meta.setAttribute(key, value)
      })
    }

    upsertMeta('meta[name="description"]', { name: 'description', content: seo.description })
    upsertMeta('meta[property="og:title"]', { property: 'og:title', content: seo.title })
    upsertMeta('meta[property="og:description"]', { property: 'og:description', content: seo.description })
    upsertMeta('meta[property="og:locale"]', { property: 'og:locale', content: seo.ogLocale })
    upsertMeta('meta[name="twitter:title"]', { name: 'twitter:title', content: seo.title })
    upsertMeta('meta[name="twitter:description"]', { name: 'twitter:description', content: seo.description })
    upsertMeta('meta[property="og:image"]', { property: 'og:image', content: DEFAULT_SHARE_IMAGE })
    upsertMeta('meta[property="og:type"]', { property: 'og:type', content: 'website' })
    upsertMeta('meta[name="twitter:image"]', { name: 'twitter:image', content: DEFAULT_SHARE_IMAGE })
    upsertMeta('meta[name="twitter:card"]', { name: 'twitter:card', content: 'summary_large_image' })
  }, [locale, location.pathname])

  useLayoutEffect(() => {
    const urls = getSeoUrls(location.pathname, location.search, locale)

    const upsertLink = (selector, attributes) => {
      let link = document.head.querySelector(selector)

      if (!link) {
        link = document.createElement('link')
        document.head.appendChild(link)
      }

      Object.entries(attributes).forEach(([key, value]) => {
        link.setAttribute(key, value)
      })
    }

    upsertLink('link[rel="canonical"]', { rel: 'canonical', href: urls.canonical })
    document.head.querySelectorAll('link[rel="alternate"][hreflang]').forEach((link) => link.remove())
    Object.entries(urls.alternates).forEach(([hreflang, href]) => {
      upsertLink(`link[rel="alternate"][hreflang="${hreflang}"]`, { rel: 'alternate', hreflang, href })
    })
    for (const [attribute, key, content] of [['name', 'robots', urls.robots], ['property', 'og:url', urls.canonical]]) {
      let meta = document.head.querySelector(`meta[${attribute}="${key}"]`)
      if (!meta) {
        meta = document.createElement('meta')
        meta.setAttribute(attribute, key)
        document.head.appendChild(meta)
      }
      meta.content = content
    }
  }, [locale, location.pathname, location.search])

  const t = useCallback((key, fallback = key) => {
    const message = resolveMessage(locale, key) ?? fallback
    return typeof message === 'string' ? normalizePotentialMojibake(message) : message
  }, [locale])

  const value = useMemo(
    () => ({
      locale,
      setLocale,
      t,
    }),
    [locale, t],
  )

  return (
    <I18nContext.Provider value={value}>{children}</I18nContext.Provider>
  )
}

export function useI18nContext() {
  const context = useContext(I18nContext)

  if (!context) {
    throw new Error('useI18nContext must be used within I18nProvider')
  }

  return context
}
