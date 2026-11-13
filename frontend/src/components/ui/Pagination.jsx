import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Link } from 'react-router-dom'
import Button from '@/components/ui/Button'
import { useI18n } from '@/hooks/useI18n'

function Pagination({ page, totalPages, onPageChange, getPageHref }) {
  const { locale } = useI18n()

  if (totalPages <= 1) return null

  const copy =
    locale === 'en'
      ? {
          previous: 'Previous',
          next: 'Next',
          label: `Page ${page} of ${totalPages}`,
        }
      : {
          previous: 'Προηγούμενη',
          next: 'Επόμενη',
          label: `Σελίδα ${page} από ${totalPages}`,
        }

  return (
    <nav aria-label={locale === 'en' ? 'Pagination' : 'Σελιδοποίηση'} className="mt-8 flex flex-wrap items-center justify-center gap-3">
      <Button
        as={getPageHref && page > 1 ? Link : 'button'}
        to={getPageHref && page > 1 ? getPageHref(page - 1) : undefined}
        rel={getPageHref && page > 1 ? 'prev' : undefined}
        variant="secondary"
        size="sm"
        disabled={page === 1}
        onClick={getPageHref ? undefined : () => onPageChange(page - 1)}
      >
        <ChevronLeft className="h-4 w-4" />
        {copy.previous}
      </Button>
      <div className="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-mist">
        {copy.label}
      </div>
      <Button
        as={getPageHref && page < totalPages ? Link : 'button'}
        to={getPageHref && page < totalPages ? getPageHref(page + 1) : undefined}
        rel={getPageHref && page < totalPages ? 'next' : undefined}
        variant="secondary"
        size="sm"
        disabled={page >= totalPages}
        onClick={getPageHref ? undefined : () => onPageChange(page + 1)}
      >
        {copy.next}
        <ChevronRight className="h-4 w-4" />
      </Button>
    </nav>
  )
}

export default Pagination
