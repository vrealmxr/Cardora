import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'
import { useSeo, useServerHead } from '@/context/SeoContext'
import { getSeoUrls, shareImageUrl } from '@/utils/seoUrls'
import { useI18n } from '@/hooks/useI18n'
import { publishPageMetadata } from '@/utils/analytics'

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
function PageSeo({ pageKey, fallbackTitle, fallbackDescription, image, type = 'website', status = 200, noIndex = false }) {
  const { key, pathname, search } = useLocation()
  const { locale } = useI18n()
  const seo = useSeo(pageKey)
  const title = seo?.metaTitle || fallbackTitle
  const description = seo?.metaDescription || fallbackDescription
  const imageUrl = shareImageUrl(image)
  const robots = noIndex || status >= 400 ? 'noindex, follow' : getSeoUrls(pathname, search, locale).robots
  const serverHead = useServerHead()
  if (serverHead) {
    if (title) serverHead.title = title
    if (description) serverHead.description = description
    serverHead.image = imageUrl
    serverHead.type = type
    serverHead.status = status
    serverHead.robots = robots
  }

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
    upsertMeta('meta[property="og:image"]', { property: 'og:image', content: imageUrl })
    upsertMeta('meta[property="og:type"]', { property: 'og:type', content: type })
    upsertMeta('meta[name="twitter:image"]', { name: 'twitter:image', content: imageUrl })
    upsertMeta('meta[name="twitter:card"]', { name: 'twitter:card', content: 'summary_large_image' })
    upsertMeta('meta[name="robots"]', { name: 'robots', content: robots })
    publishPageMetadata({ navigationId: key, pathname, search, title: title || document.title, status })
  }, [title, description, key, pathname, search, imageUrl, type, robots, status])

  return null
}

export default PageSeo
