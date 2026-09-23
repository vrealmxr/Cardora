import { CheckCircle2, KeyRound, ShieldCheck } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input } from '@/components/ui/Input'
import SectionHeader from '@/components/ui/SectionHeader'
import { useI18n } from '@/hooks/useI18n'
import { cardoraService } from '@/services/cardoraService'
import { normalizeTextTree } from '@/utils/textEncoding'

function ResetPasswordPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const { locale } = useI18n()
  const [email, setEmail] = useState(searchParams.get('email') ?? '')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [error, setError] = useState('')

  const token = searchParams.get('token') ?? ''

  const copy = normalizeTextTree(
    locale === 'en'
      ? {
          eyebrow: 'Choose new password',
          title: 'Set a new password for your Cardora account',
          description:
            'This page is opened from the secure reset email. Choose a strong password and then sign in again.',
          email: 'Email',
          password: 'New password',
          passwordConfirmation: 'Confirm new password',
          emailPlaceholder: 'collector@cardora.com',
          passwordPlaceholder: 'At least 8 characters',
          submit: 'Save new password',
          saving: 'Saving...',
          invalidTitle: 'This reset link is missing or incomplete',
          invalidText:
            'Open the password reset email again or request a fresh reset link to continue safely.',
          requestNew: 'Request new reset link',
          backLogin: 'Back to login',
          checklistTitle: 'A good password should',
          checklist: [
            'Have at least 8 characters',
            'Be different from old passwords you already used',
            'Stay private and never be shared in messages',
          ],
          mismatch: 'The password confirmation does not match.',
        }
      : {
          eyebrow: 'Νέος κωδικός',
          title: 'Όρισε νέο κωδικό για τον λογαριασμό σου στην Cardora',
          description:
            'Η σελίδα αυτή ανοίγει από το ασφαλές email επαναφοράς. Διάλεξε ισχυρό κωδικό και μετά συνδέσου ξανά κανονικά.',
          email: 'Email',
          password: 'Νέος κωδικός',
          passwordConfirmation: 'Επιβεβαίωση νέου κωδικού',
          emailPlaceholder: 'collector@cardora.gr',
          passwordPlaceholder: 'Τουλάχιστον 8 χαρακτήρες',
          submit: 'Αποθήκευση νέου κωδικού',
          saving: 'Αποθήκευση...',
          invalidTitle: 'Ο σύνδεσμος επαναφοράς λείπει ή δεν είναι πλήρης',
          invalidText:
            'Άνοιξε ξανά το email επαναφοράς ή ζήτησε νέο σύνδεσμο για να συνεχίσεις με ασφάλεια.',
          requestNew: 'Νέο email επαναφοράς',
          backLogin: 'Επιστροφή στην είσοδο',
          checklistTitle: 'Ένας σωστός κωδικός καλό είναι να',
          checklist: [
            'Έχει τουλάχιστον 8 χαρακτήρες',
            'Είναι διαφορετικός από παλιούς κωδικούς που έχεις χρησιμοποιήσει',
            'Μένει ιδιωτικός και να μην κοινοποιείται ποτέ σε μηνύματα',
          ],
          mismatch: 'Η επιβεβαίωση κωδικού δεν ταιριάζει.',
        },
  )

  const invalidLink = !token
  const mismatch = useMemo(
    () => Boolean(passwordConfirmation) && password !== passwordConfirmation,
    [password, passwordConfirmation],
  )

  const handleSubmit = async (event) => {
    event.preventDefault()
    if (invalidLink || mismatch) return

    setIsSubmitting(true)
    setError('')

    try {
      await cardoraService.resetPassword({
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      })
      navigate('/eisodos?reset=1')
    } catch (submitError) {
      setError(submitError.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  if (invalidLink) {
    return (
      <div className="container pb-16">
        <SectionHeader eyebrow={copy.eyebrow} title={copy.invalidTitle} description={copy.invalidText} />
        <CardSurface className="mx-auto max-w-3xl p-6 text-center sm:p-8">
          <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full border border-rose-400/20 bg-rose-500/10 text-rose-800">
            <ShieldCheck className="h-7 w-7" />
          </div>
          <p className="mt-5 text-sm leading-7 text-mist">{copy.invalidText}</p>
          <div className="mt-6 flex flex-wrap justify-center gap-3">
            <Button as={Link} to="/xechasa-kodiko">
              {copy.requestNew}
            </Button>
            <Button as={Link} to="/eisodos" variant="secondary">
              {copy.backLogin}
            </Button>
          </div>
        </CardSurface>
      </div>
    )
  }

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />

      <div className="grid gap-6 xl:grid-cols-[1fr,0.95fr]">
        <CardSurface className="p-6 sm:p-8">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="mb-2 block text-sm text-mist">{copy.email}</label>
              <Input
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                placeholder={copy.emailPlaceholder}
              />
            </div>

            <div>
              <label className="mb-2 block text-sm text-mist">{copy.password}</label>
              <Input
                type="password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                placeholder={copy.passwordPlaceholder}
              />
            </div>

            <div>
              <label className="mb-2 block text-sm text-mist">{copy.passwordConfirmation}</label>
              <Input
                type="password"
                value={passwordConfirmation}
                onChange={(event) => setPasswordConfirmation(event.target.value)}
                placeholder={copy.passwordPlaceholder}
              />
            </div>

            {mismatch ? (
              <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-800">
                {copy.mismatch}
              </div>
            ) : null}

            {error ? (
              <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-800">
                {error}
              </div>
            ) : null}

            <div className="flex flex-wrap gap-3">
              <Button type="submit" disabled={isSubmitting || !email.trim() || !password || !passwordConfirmation || mismatch}>
                <KeyRound className="h-4 w-4" />
                {isSubmitting ? copy.saving : copy.submit}
              </Button>
              <Button as={Link} to="/eisodos" variant="secondary">
                {copy.backLogin}
              </Button>
            </div>
          </form>
        </CardSurface>

        <CardSurface className="p-6 sm:p-8">
          <Badge tone="gold">{copy.eyebrow}</Badge>
          <h2 className="mt-4 font-display text-3xl text-white">{copy.checklistTitle}</h2>
          <div className="mt-4 space-y-3">
            {copy.checklist.map((item) => (
              <div
                key={item}
                className="flex items-start gap-3 rounded-2xl border border-white/8 bg-white/5 px-4 py-3 text-sm leading-7 text-white/80"
              >
                <CheckCircle2 className="mt-1 h-4 w-4 shrink-0 text-gold-100" />
                <span>{item}</span>
              </div>
            ))}
          </div>
        </CardSurface>
      </div>
    </div>
  )
}

export default ResetPasswordPage
