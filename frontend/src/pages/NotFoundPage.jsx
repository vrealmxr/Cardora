import { Link } from 'react-router-dom'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import PageSeo from '@/components/PageSeo'
import { useI18n } from '@/hooks/useI18n'
import { localizePath } from '@/utils/helpers'

function NotFoundPage() {
  const { locale } = useI18n()
  const title = locale === 'en' ? 'Page not found' : 'Η σελίδα δεν βρέθηκε.'
  const description = locale === 'en' ? 'The page may have moved or the item is no longer available.' : 'Ίσως μετακινήθηκε ή το αντικείμενο που έψαχνες δεν είναι πλέον διαθέσιμο.'
  return (
    <div className="container pb-16">
      <PageSeo fallbackTitle={`${title} | Cardora`} fallbackDescription={description} status={404} />
      <CardSurface className="mx-auto max-w-3xl text-center">
        <p className="text-xs uppercase tracking-[0.4em] text-gold-100">404</p>
        <h1 className="mt-5 font-display text-6xl text-white">{title}</h1>
        <p className="mt-4 text-lg leading-8 text-mist">{description}</p>
        <div className="mt-8 flex justify-center">
          <Button as={Link} to={localizePath('/', locale)}>
            {locale === 'en' ? 'Back to home' : 'Επιστροφή στην αρχική'}
          </Button>
        </div>
      </CardSurface>
    </div>
  )
}

export default NotFoundPage
