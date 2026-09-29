import { createContext, useContext, useEffect, useLayoutEffect, useMemo, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { translations } from '@/i18n/translations'
import {
  DEFAULT_LOCALE,
  getLocaleFromPathname,
  isValidLocale,
  localizePath,
  stripLocaleFromPathname,
} from '@/utils/helpers'
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
  const [locale, setLocale] = useState(() => {
    const pathnameLocale = getLocaleFromPathname(window.location.pathname)
    const storedLocale = window.localStorage.getItem(STORAGE_KEY)

    if (pathnameLocale) {
      return pathnameLocale
    }

    return isValidLocale(storedLocale) ? storedLocale : DEFAULT_LOCALE
  })

  useEffect(() => {
    const pathnameLocale = getLocaleFromPathname(location.pathname)

    if (pathnameLocale && pathnameLocale !== locale) {
      setLocale(pathnameLocale)
    }
  }, [locale, location.pathname])

  useEffect(() => {
    document.documentElement.lang = locale
    window.localStorage.setItem(STORAGE_KEY, locale)
  }, [locale])

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
  }, [locale])

  useEffect(() => {
    const canonicalPath = localizePath(stripLocaleFromPathname(location.pathname), locale)
    const canonicalUrl = `${window.location.origin}${canonicalPath}${location.search}`
    const alternatePaths = {
      el: `${window.location.origin}${localizePath(stripLocaleFromPathname(location.pathname), 'el')}${location.search}`,
      en: `${window.location.origin}${localizePath(stripLocaleFromPathname(location.pathname), 'en')}${location.search}`,
    }

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

    upsertLink('link[rel="canonical"]', { rel: 'canonical', href: canonicalUrl })
    upsertLink('link[rel="alternate"][hreflang="el"]', {
      rel: 'alternate',
      hreflang: 'el',
      href: alternatePaths.el,
    })
    upsertLink('link[rel="alternate"][hreflang="en"]', {
      rel: 'alternate',
      hreflang: 'en',
      href: alternatePaths.en,
    })
    upsertLink('link[rel="alternate"][hreflang="x-default"]', {
      rel: 'alternate',
      hreflang: 'x-default',
      href: alternatePaths.el,
    })
  }, [locale, location.pathname, location.search])

  const t = (key, fallback = key) => {
    const message = resolveMessage(locale, key) ?? fallback
    return typeof message === 'string' ? normalizePotentialMojibake(message) : message
  }

  const value = useMemo(
    () => ({
      locale,
      setLocale,
      t,
    }),
    [locale],
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
