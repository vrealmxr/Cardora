import { Link } from 'react-router-dom'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { useI18n } from '@/hooks/useI18n'

function ComingSoonPage({ titleEl, titleEn }) {
  const { locale } = useI18n()
  const isEnglish = locale === 'en'

  return (
    <div className="hero-bg relative flex min-h-screen items-center overflow-x-hidden py-16">
      <div className="container">
        <CardSurface className="mx-auto max-w-3xl text-center">
          <p className="text-xs uppercase tracking-[0.4em] text-gold-100">
            {isEnglish ? 'Coming soon' : 'Σύντομα κοντά σας'}
          </p>
          <h1 className="mt-5 font-display text-4xl text-white sm:text-5xl">
            {isEnglish ? titleEn : titleEl}
          </h1>
          <p className="mt-4 text-lg leading-8 text-mist">
            {isEnglish
              ? 'This feature is temporarily unavailable while we work on it. Check back soon.'
              : 'Αυτή η λειτουργία είναι προσωρινά μη διαθέσιμη όσο δουλεύουμε πάνω της. Ελάτε ξανά σύντομα.'}
          </p>
          <div className="mt-8 flex justify-center">
            <Button as={Link} to="/">
              {isEnglish ? 'Back to home' : 'Επιστροφή στην αρχική'}
            </Button>
          </div>
        </CardSurface>
      </div>
    </div>
  )
}

export default ComingSoonPage
