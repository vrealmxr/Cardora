import { CheckCircle2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { localizePath } from '@/utils/helpers'

function CardoraProSuccessPage() {
  const { locale } = useI18n()
  const { refreshCurrentUser } = useAuth()
  const isEnglish = locale === 'en'
  const localized = (path) => localizePath(path, locale)
  const [ready, setReady] = useState(false)

  useEffect(() => {
    document.title = 'Cardora PRO'
    // Stripe's webhook usually lands within a second or two of the redirect —
    // give it a moment before pulling the fresh plan status onto the user.
    const timer = setTimeout(async () => {
      await refreshCurrentUser?.()
      setReady(true)
    }, 1500)

    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const copy = isEnglish
    ? {
        title: "You're on Cardora PRO",
        description:
          'Your subscription is active. Unlimited AI Scanner, advanced Binder tools and lower marketplace fees are ready to use.',
        binderCta: 'Open Cardora Binder',
        accountCta: 'Manage subscription',
      }
    : {
        title: 'Είσαι πλέον σε Cardora PRO',
        description:
          'Η συνδρομή σου είναι ενεργή. Unlimited AI Scanner, προηγμένα εργαλεία Binder και χαμηλότερες προμήθειες είναι έτοιμα για χρήση.',
        binderCta: 'Άνοιγμα Cardora Binder',
        accountCta: 'Διαχείριση συνδρομής',
      }

  return (
    <div className="container pb-16 pt-10">
      <CardSurface className="featured-glow mx-auto max-w-xl text-center">
        <CheckCircle2 className="mx-auto h-10 w-10 text-[#9d6a17]" />
        <h1 className="mt-4 font-display text-3xl text-ink">{copy.title}</h1>
        <p className="mt-3 text-sm leading-7 text-mist">{copy.description}</p>
        <div className="mt-6 flex flex-wrap justify-center gap-3">
          <Button as={Link} to={localized('/cardora-binder')} disabled={!ready}>
            {copy.binderCta}
          </Button>
          <Button as={Link} to={localized('/rythmiseis-logariasmou')} variant="secondary">
            {copy.accountCta}
          </Button>
        </div>
      </CardSurface>
    </div>
  )
}

export default CardoraProSuccessPage
