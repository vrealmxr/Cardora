import { ChevronRight } from 'lucide-react'
import StructuredData from '@/components/StructuredData'
import { breadcrumbStructuredData } from '@/utils/structuredData'
import { getSeoUrls } from '@/utils/seoUrls'
import { useLocation, Link } from 'react-router-dom'
import { useI18n } from '@/hooks/useI18n'
import { localizePath } from '@/utils/helpers'

function Breadcrumbs({ items }) {
  const { locale } = useI18n()
  const location = useLocation()
  return (
    <nav aria-label={locale === 'en' ? 'Breadcrumb' : 'Διαδρομή σελίδας'} className="flex flex-wrap items-center gap-2 text-sm text-white/55">
      <StructuredData value={breadcrumbStructuredData(items, getSeoUrls(location.pathname, location.search, locale).canonical, value => localizePath(value, locale))} />
      {items.map((item, index) => (
        <div key={item.label} className="flex items-center gap-2">
          {item.href ? (
            <Link to={localizePath(item.href, locale)} className="transition hover:text-gold-100">
              {item.label}
            </Link>
          ) : (
            <span className="text-white">{item.label}</span>
          )}
          {index < items.length - 1 ? <ChevronRight className="h-4 w-4" /> : null}
        </div>
      ))}
    </nav>
  )
}

export default Breadcrumbs
