import { ChevronLeft, ChevronRight } from 'lucide-react'
import Button from '@/components/ui/Button'
import { useI18n } from '@/hooks/useI18n'

function Pagination({ page, totalPages, onPageChange }) {
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
    <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
      <Button
        variant="secondary"
        size="sm"
        disabled={page === 1}
        onClick={() => onPageChange(page - 1)}
      >
        <ChevronLeft className="h-4 w-4" />
        {copy.previous}
      </Button>
      <div className="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-mist">
        {copy.label}
      </div>
      <Button
        variant="secondary"
        size="sm"
        disabled={page === totalPages}
        onClick={() => onPageChange(page + 1)}
      >
        {copy.next}
        <ChevronRight className="h-4 w-4" />
      </Button>
    </div>
  )
}

export default Pagination
