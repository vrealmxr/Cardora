import { KeyRound, Mail } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input } from '@/components/ui/Input'
import SectionHeader from '@/components/ui/SectionHeader'
import { useI18n } from '@/hooks/useI18n'
import { cardoraService } from '@/services/cardoraService'
import { normalizeTextTree } from '@/utils/textEncoding'

function ForgotPasswordPage() {
  const { locale } = useI18n()
  const [email, setEmail] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [success, setSuccess] = useState('')
  const [error, setError] = useState('')

  const copy = normalizeTextTree(
    locale === 'en'
      ? {
          eyebrow: 'Password reset',
          title: 'Get a reset link by email',
          description:
            'Enter the email tied to your Cardora account and we will send you a secure link to choose a new password.',
          email: 'Email',
          placeholder: 'collector@cardora.com',
          submit: 'Send reset email',
          sending: 'Sending...',
          noteTitle: 'What happens next',
          notes: [
            'You will receive a password reset email if the address belongs to an account.',
            'The reset link opens a secure page where you can choose a new password.',
            'If nothing arrives, check spam or promotions as well.',
          ],
          backLogin: 'Back to login',
        }
      : {
          eyebrow: 'Επαναφορά κωδικού',
          title: 'Λάβε email για νέο κωδικό',
          description:
            'Συμπλήρωσε το email του λογαριασμού σου στην Cardora και θα σου στείλουμε ασφαλή σύνδεσμο για να ορίσεις νέο κωδικό.',
          email: 'Email',
          placeholder: 'collector@cardora.gr',
          submit: 'Αποστολή email επαναφοράς',
          sending: 'Αποστολή...',
          noteTitle: 'Τι γίνεται μετά',
          notes: [
            'Θα λάβεις email επαναφοράς αν η διεύθυνση ανήκει σε λογαριασμό.',
            'Ο σύνδεσμος ανοίγει ασφαλή σελίδα όπου ορίζεις νέο κωδικό.',
            'Αν δεν εμφανιστεί άμεσα, έλεγξε και spam ή promotions.',
          ],
          backLogin: 'Επιστροφή στην είσοδο',
        },
  )

  const handleSubmit = async (event) => {
    event.preventDefault()
    setIsSubmitting(true)
    setSuccess('')
    setError('')

    try {
      const response = await cardoraService.forgotPassword({ email })
      setSuccess(response?.message ?? '')
    } catch (submitError) {
      setError(submitError.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />

      <div className="grid gap-6 xl:grid-cols-[0.95fr,1.05fr]">
        <CardSurface className="p-6 sm:p-8">
          <div className="flex items-start gap-4">
            <div className="rounded-2xl border border-gold-300/20 bg-gold-300/10 p-3 text-gold-100">
              <Mail className="h-6 w-6" />
            </div>
            <div>
              <Badge tone="gold">{copy.eyebrow}</Badge>
              <p className="mt-4 text-sm leading-7 text-mist">{copy.description}</p>
            </div>
          </div>

          <form onSubmit={handleSubmit} className="mt-8 space-y-4">
            <div>
              <label className="mb-2 block text-sm text-mist">{copy.email}</label>
              <Input
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                placeholder={copy.placeholder}
              />
            </div>

            {success ? (
              <div className="rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-800">
                {success}
              </div>
            ) : null}

            {error ? (
              <div className="rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-800">
                {error}
              </div>
            ) : null}

            <div className="flex flex-wrap gap-3">
              <Button type="submit" disabled={isSubmitting || !email.trim()}>
                <KeyRound className="h-4 w-4" />
                {isSubmitting ? copy.sending : copy.submit}
              </Button>
              <Button as={Link} to="/eisodos" variant="secondary">
                {copy.backLogin}
              </Button>
            </div>
          </form>
        </CardSurface>

        <CardSurface className="p-6 sm:p-8">
          <h2 className="font-display text-3xl text-white">{copy.noteTitle}</h2>
          <div className="mt-4 space-y-3">
            {copy.notes.map((note) => (
              <div
                key={note}
                className="rounded-2xl border border-white/8 bg-white/5 px-4 py-3 text-sm leading-7 text-white/80"
              >
                {note}
              </div>
            ))}
          </div>
        </CardSurface>
      </div>
    </div>
  )
}

export default ForgotPasswordPage
