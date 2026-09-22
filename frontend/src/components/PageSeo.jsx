import { useEffect } from 'react'
import { useSeo } from '@/context/SeoContext'

function upsertMeta(selector, attributes) {
  let meta = document.head.querySelector(selector)

  if (!meta) {
    meta = document.createElement('meta')
    document.head.appendChild(meta)
  }

  Object.entries(attributes).forEach(([key, value]) => {
    meta.setAttribute(key, value)
  })
}

/**
 * Per-page override of the <title>/meta description that I18nProvider sets site-wide.
 * Renders nothing — mount it once near the top of a page component. Falls back to the
 * page's existing hardcoded copy until an admin sets an override for pageKey via the
 * "SEO Pages" Filament resource.
 */
function PageSeo({ pageKey, fallbackTitle, fallbackDescription }) {
  const seo = useSeo(pageKey)
  const title = seo?.metaTitle || fallbackTitle
  const description = seo?.metaDescription || fallbackDescription

  useEffect(() => {
    if (title) {
      document.title = title
      upsertMeta('meta[property="og:title"]', { property: 'og:title', content: title })
      upsertMeta('meta[name="twitter:title"]', { name: 'twitter:title', content: title })
    }

    if (description) {
      upsertMeta('meta[name="description"]', { name: 'description', content: description })
      upsertMeta('meta[property="og:description"]', { property: 'og:description', content: description })
      upsertMeta('meta[name="twitter:description"]', { name: 'twitter:description', content: description })
    }
  }, [title, description])

  return null
}

export default PageSeo
