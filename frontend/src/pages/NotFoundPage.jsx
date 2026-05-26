import { Link } from 'react-router-dom'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'

function NotFoundPage() {
  return (
    <div className="container pb-16">
      <CardSurface className="mx-auto max-w-3xl text-center">
        <p className="text-xs uppercase tracking-[0.4em] text-gold-100">404</p>
        <h1 className="mt-5 font-display text-6xl text-white">Η σελίδα δεν βρέθηκε.</h1>
        <p className="mt-4 text-lg leading-8 text-mist">Ίσως μετακινήθηκε ή το collectible που έψαχνες δεν είναι πλέον διαθέσιμο.</p>
        <div className="mt-8 flex justify-center">
          <Button as={Link} to="/">
            Επιστροφή στην αρχική
          </Button>
        </div>
      </CardSurface>
    </div>
  )
}

export default NotFoundPage
