import { useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'

function GoogleAuthCallbackPage() {
  const { locale } = useI18n()
  const { authenticateWithToken } = useAuth()
  const [isCompleting, setIsCompleting] = useState(true)
  const [error, setError] = useState('')
  const hasStartedRef = useRef(false)

  const copy = useMemo(
    () =>
      locale === 'en'
        ? {
            title: 'Completing Google sign in',
            loading: 'Please wait while we securely sign you in...',
            fallbackError: 'Google sign in failed. Please try again.',
            backToLogin: 'Back to login',
          }
        : {
            title: 'Ολοκλήρωση σύνδεσης Google',
            loading: 'Περίμενε λίγο, σε συνδέουμε με ασφάλεια...',
            fallbackError: 'Η σύνδεση με Google απέτυχε. Δοκίμασε ξανά.',
            backToLogin: 'Επιστροφή στην είσοδο',
          },
    [locale],
  )

  useEffect(() => {
    if (hasStartedRef.current) {
      return undefined
    }

    hasStartedRef.current = true
    let isActive = true

    const completeGoogleAuth = async () => {
      const hash = window.location.hash.startsWith('#')
        ? window.location.hash.slice(1)
        : window.location.hash
      const search = window.location.search.startsWith('?')
        ? window.location.search.slice(1)
        : window.location.search
      const hashParams = new URLSearchParams(hash)
      const searchParams = new URLSearchParams(search)
      const status = hashParams.get('status') ?? searchParams.get('status')
      const token = hashParams.get('token') ?? searchParams.get('token')
      const message = hashParams.get('message') ?? searchParams.get('message')
      const isNewUser = (hashParams.get('new_user') ?? searchParams.get('new_user')) === '1'
      const cleanPath = window.location.pathname

      if (window.location.hash || window.location.search) {
        window.history.replaceState({}, document.title, cleanPath)
      }

      if (status !== 'success' || !token) {
        if (isActive) {
          setError(message || copy.fallbackError)
          setIsCompleting(false)
        }
        return
      }

      try {
        await authenticateWithToken(token)
      } catch (exception) {
        if (!isActive) return

        setError(message || exception?.message || copy.fallbackError)
        setIsCompleting(false)
        return
      }

      const destination = isNewUser ? '/profil?welcome=1&google=1' : '/profil?activation=1&google=1'
      window.location.replace(destination)
    }

    completeGoogleAuth().catch((exception) => {
      if (!isActive) return

      setError(exception?.message || copy.fallbackError)
      setIsCompleting(false)
    })

    return () => {
      isActive = false
    }
  }, [authenticateWithToken, copy.fallbackError])

  return (
    <div className="container py-16">
      <CardSurface className="mx-auto w-full max-w-xl p-8 sm:p-10">
        <h1 className="font-display text-4xl text-white">{copy.title}</h1>
        <p className="mt-4 text-base leading-7 text-mist">
          {isCompleting ? copy.loading : error}
        </p>
        {!isCompleting ? (
          <div className="mt-8">
            <Button as={Link} to="/eisodos" variant="secondary">
              {copy.backToLogin}
            </Button>
          </div>
        ) : null}
      </CardSurface>
    </div>
  )
}

export default GoogleAuthCallbackPage
