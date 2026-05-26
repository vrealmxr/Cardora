import { KeyRound, Loader2, MailCheck, ShieldCheck } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Link, Navigate } from 'react-router-dom'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input } from '@/components/ui/Input'
import SectionHeader from '@/components/ui/SectionHeader'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { cardoraService } from '@/services/cardoraService'

const getFieldError = (error, field) => {
  const value = error?.errors?.[field]

  if (Array.isArray(value) && value.length) {
    return value[0]
  }

  return ''
}

function FeedbackBox({ feedback }) {
  if (!feedback) return null

  return (
    <div
      className={`rounded-2xl border px-4 py-3 text-sm ${
        feedback.tone === 'success'
          ? 'border-emerald-400/25 bg-emerald-500/10 text-emerald-100'
          : 'border-rose-400/25 bg-rose-500/10 text-rose-100'
      }`}
    >
      {feedback.text}
    </div>
  )
}

function AccountSettingsPage() {
  const { locale } = useI18n()
  const { currentUser, refreshCurrentUser } = useAuth()
  const [emailForm, setEmailForm] = useState({
    email: '',
    currentPassword: '',
  })
  const [passwordForm, setPasswordForm] = useState({
    currentPassword: '',
    password: '',
    passwordConfirmation: '',
  })
  const [emailErrors, setEmailErrors] = useState({})
  const [passwordErrors, setPasswordErrors] = useState({})
  const [emailFeedback, setEmailFeedback] = useState(null)
  const [passwordFeedback, setPasswordFeedback] = useState(null)
  const [verificationFeedback, setVerificationFeedback] = useState(null)
  const [emailSaving, setEmailSaving] = useState(false)
  const [passwordSaving, setPasswordSaving] = useState(false)
  const [verificationSending, setVerificationSending] = useState(false)

  const copy = useMemo(
    () =>
      locale === 'en'
        ? {
            eyebrow: 'Account settings',
            title: 'Security and login details',
            description:
              'Change your email, update your password and keep account access protected.',
            emailCardTitle: 'Email address',
            emailCardText:
              'Update your login email. For security, we ask for your current password first.',
            currentEmail: 'Current email',
            verified: 'Verified',
            notVerified: 'Not verified',
            newEmail: 'New email',
            currentPassword: 'Current password',
            saveEmail: 'Save email',
            emailSaved: 'Your email was updated successfully.',
            emailFailed: 'Email update failed. Please try again.',
            verificationSent: 'A verification email was sent to your new address.',
            verificationNotSent:
              'Your email was updated, but we could not send the verification message right now.',
            resendVerification: 'Send verification email again',
            resendSent: 'A new verification email was sent.',
            resendFailed: 'Could not send verification email right now.',
            passwordCardTitle: 'Password',
            passwordCardText:
              'Use a strong password with at least 8 characters and keep it unique for Cardora.',
            newPassword: 'New password',
            confirmPassword: 'Confirm new password',
            savePassword: 'Save password',
            passwordSaved: 'Your password was updated successfully.',
            passwordFailed: 'Password update failed. Please try again.',
            helpTitle: 'Extra security',
            helpText:
              'If you signed up with Google and do not know your current password, use password reset to set a new one.',
            forgotPassword: 'Open password reset',
            saving: 'Saving...',
            sending: 'Sending...',
          }
        : {
            eyebrow: 'Ρυθμίσεις λογαριασμού',
            title: 'Ασφάλεια και στοιχεία σύνδεσης',
            description:
              'Άλλαξε email, ανανέωσε κωδικό και κράτα τον λογαριασμό σου ασφαλή.',
            emailCardTitle: 'Διεύθυνση email',
            emailCardText:
              'Άλλαξε το email σύνδεσης. Για ασφάλεια ζητείται πρώτα ο τρέχων κωδικός σου.',
            currentEmail: 'Τρέχον email',
            verified: 'Επιβεβαιωμένο',
            notVerified: 'Μη επιβεβαιωμένο',
            newEmail: 'Νέο email',
            currentPassword: 'Τρέχων κωδικός',
            saveEmail: 'Αποθήκευση email',
            emailSaved: 'Το email ενημερώθηκε επιτυχώς.',
            emailFailed: 'Η αλλαγή email απέτυχε. Δοκίμασε ξανά.',
            verificationSent: 'Στάλθηκε email επιβεβαίωσης στη νέα διεύθυνση.',
            verificationNotSent:
              'Το email άλλαξε, αλλά δεν στάθηκε δυνατό να σταλεί τώρα το μήνυμα επιβεβαίωσης.',
            resendVerification: 'Επαναποστολή email επιβεβαίωσης',
            resendSent: 'Στάλθηκε νέο email επιβεβαίωσης.',
            resendFailed: 'Δεν ήταν δυνατή η αποστολή email επιβεβαίωσης τώρα.',
            passwordCardTitle: 'Κωδικός πρόσβασης',
            passwordCardText:
              'Χρησιμοποίησε ισχυρό κωδικό με τουλάχιστον 8 χαρακτήρες και κράτα τον μοναδικό για την Cardora.',
            newPassword: 'Νέος κωδικός',
            confirmPassword: 'Επιβεβαίωση νέου κωδικού',
            savePassword: 'Αποθήκευση κωδικού',
            passwordSaved: 'Ο κωδικός ενημερώθηκε επιτυχώς.',
            passwordFailed: 'Η αλλαγή κωδικού απέτυχε. Δοκίμασε ξανά.',
            helpTitle: 'Έξτρα ασφάλεια',
            helpText:
              'Αν έχεις μπει με Google και δεν γνωρίζεις τον τρέχοντα κωδικό, χρησιμοποίησε επαναφορά κωδικού για να ορίσεις νέο.',
            forgotPassword: 'Άνοιγμα επαναφοράς κωδικού',
            saving: 'Αποθήκευση...',
            sending: 'Αποστολή...',
          },
    [locale],
  )

  if (!currentUser) {
    return <Navigate to="/eisodos" replace state={{ from: '/rythmiseis-logariasmou' }} />
  }

  const emailVerified = Boolean(currentUser?.emailVerified ?? currentUser?.emailVerifiedAt)

  const handleEmailSubmit = async (event) => {
    event.preventDefault()
    setEmailSaving(true)
    setEmailErrors({})
    setEmailFeedback(null)

    try {
      const result = await cardoraService.updateAccountEmail({
        email: emailForm.email,
        current_password: emailForm.currentPassword,
      })

      await refreshCurrentUser()
      setEmailFeedback({
        tone: 'success',
        text:
          result?.message ||
          (result?.verificationEmailSent === false ? copy.verificationNotSent : copy.emailSaved),
      })
      setVerificationFeedback(
        result?.verificationEmailSent === false
          ? { tone: 'danger', text: copy.verificationNotSent }
          : { tone: 'success', text: copy.verificationSent },
      )
      setEmailForm((previous) => ({
        ...previous,
        currentPassword: '',
      }))
    } catch (error) {
      const nextErrors = {
        email: getFieldError(error, 'email'),
        currentPassword: getFieldError(error, 'current_password'),
      }

      setEmailErrors(nextErrors)
      setEmailFeedback({ tone: 'danger', text: error?.message || copy.emailFailed })
    } finally {
      setEmailSaving(false)
    }
  }

  const handlePasswordSubmit = async (event) => {
    event.preventDefault()
    setPasswordSaving(true)
    setPasswordErrors({})
    setPasswordFeedback(null)

    try {
      const result = await cardoraService.updateAccountPassword({
        current_password: passwordForm.currentPassword,
        password: passwordForm.password,
        password_confirmation: passwordForm.passwordConfirmation,
      })

      setPasswordFeedback({
        tone: 'success',
        text: result?.message || copy.passwordSaved,
      })
      setPasswordForm({
        currentPassword: '',
        password: '',
        passwordConfirmation: '',
      })
    } catch (error) {
      const nextErrors = {
        currentPassword: getFieldError(error, 'current_password'),
        password: getFieldError(error, 'password'),
      }

      setPasswordErrors(nextErrors)
      setPasswordFeedback({ tone: 'danger', text: error?.message || copy.passwordFailed })
    } finally {
      setPasswordSaving(false)
    }
  }

  const handleResendVerification = async () => {
    setVerificationSending(true)
    setVerificationFeedback(null)

    try {
      const response = await cardoraService.resendVerificationEmail()
      setVerificationFeedback({
        tone: 'success',
        text: response?.message || copy.resendSent,
      })
    } catch (error) {
      setVerificationFeedback({
        tone: 'danger',
        text: error?.message || copy.resendFailed,
      })
    } finally {
      setVerificationSending(false)
    }
  }

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />

      <div className="grid gap-6 xl:grid-cols-2">
        <CardSurface>
          <div className="flex items-center gap-3">
            <div className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-gold-300/18 bg-gold-300/10 text-gold-100">
              <MailCheck className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-2xl font-semibold text-white">{copy.emailCardTitle}</h2>
              <p className="mt-1 text-sm leading-7 text-white/72">{copy.emailCardText}</p>
            </div>
          </div>

          <div className="mt-5 rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
            <p className="text-xs uppercase tracking-[0.24em] text-white/45">{copy.currentEmail}</p>
            <div className="mt-2 flex flex-wrap items-center gap-2">
              <p className="text-sm font-semibold text-white">{currentUser.email}</p>
              <span
                className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                  emailVerified
                    ? 'border border-emerald-300/40 bg-emerald-500/15 text-emerald-100'
                    : 'border border-amber-300/40 bg-amber-500/15 text-amber-100'
                }`}
              >
                {emailVerified ? copy.verified : copy.notVerified}
              </span>
            </div>
          </div>

          <form className="mt-5 space-y-4" onSubmit={handleEmailSubmit}>
            <div>
              <label className="mb-2 block text-sm text-mist">{copy.newEmail}</label>
              <Input
                type="email"
                value={emailForm.email}
                onChange={(event) => {
                  setEmailForm((previous) => ({ ...previous, email: event.target.value }))
                  setEmailErrors((previous) => ({ ...previous, email: '' }))
                }}
                required
              />
              {emailErrors.email ? <p className="mt-2 text-xs text-rose-200">{emailErrors.email}</p> : null}
            </div>

            <div>
              <label className="mb-2 block text-sm text-mist">{copy.currentPassword}</label>
              <Input
                type="password"
                value={emailForm.currentPassword}
                onChange={(event) => {
                  setEmailForm((previous) => ({ ...previous, currentPassword: event.target.value }))
                  setEmailErrors((previous) => ({ ...previous, currentPassword: '' }))
                }}
                required
              />
              {emailErrors.currentPassword ? (
                <p className="mt-2 text-xs text-rose-200">{emailErrors.currentPassword}</p>
              ) : null}
            </div>

            <FeedbackBox feedback={emailFeedback} />

            <Button type="submit" disabled={emailSaving}>
              {emailSaving ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
              {emailSaving ? copy.saving : copy.saveEmail}
            </Button>
          </form>

          {!emailVerified ? (
            <div className="mt-6 rounded-2xl border border-gold-300/20 bg-gold-300/10 p-4">
              <p className="text-sm text-gold-50">
                {locale === 'en'
                  ? 'Your current email is not verified yet.'
                  : 'Το τρέχον email σου δεν είναι επιβεβαιωμένο ακόμα.'}
              </p>
              <div className="mt-3 flex flex-wrap gap-2">
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => void handleResendVerification()}
                  disabled={verificationSending}
                >
                  {verificationSending ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
                  {verificationSending ? copy.sending : copy.resendVerification}
                </Button>
              </div>
              <div className="mt-3">
                <FeedbackBox feedback={verificationFeedback} />
              </div>
            </div>
          ) : null}
        </CardSurface>

        <CardSurface>
          <div className="flex items-center gap-3">
            <div className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-gold-300/18 bg-gold-300/10 text-gold-100">
              <KeyRound className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-2xl font-semibold text-white">{copy.passwordCardTitle}</h2>
              <p className="mt-1 text-sm leading-7 text-white/72">{copy.passwordCardText}</p>
            </div>
          </div>

          <form className="mt-5 space-y-4" onSubmit={handlePasswordSubmit}>
            <div>
              <label className="mb-2 block text-sm text-mist">{copy.currentPassword}</label>
              <Input
                type="password"
                value={passwordForm.currentPassword}
                onChange={(event) => {
                  setPasswordForm((previous) => ({
                    ...previous,
                    currentPassword: event.target.value,
                  }))
                  setPasswordErrors((previous) => ({ ...previous, currentPassword: '' }))
                }}
                required
              />
              {passwordErrors.currentPassword ? (
                <p className="mt-2 text-xs text-rose-200">{passwordErrors.currentPassword}</p>
              ) : null}
            </div>

            <div>
              <label className="mb-2 block text-sm text-mist">{copy.newPassword}</label>
              <Input
                type="password"
                value={passwordForm.password}
                onChange={(event) => {
                  setPasswordForm((previous) => ({
                    ...previous,
                    password: event.target.value,
                  }))
                  setPasswordErrors((previous) => ({ ...previous, password: '' }))
                }}
                minLength={8}
                required
              />
            </div>

            <div>
              <label className="mb-2 block text-sm text-mist">{copy.confirmPassword}</label>
              <Input
                type="password"
                value={passwordForm.passwordConfirmation}
                onChange={(event) =>
                  setPasswordForm((previous) => ({
                    ...previous,
                    passwordConfirmation: event.target.value,
                  }))
                }
                minLength={8}
                required
              />
              {passwordErrors.password ? (
                <p className="mt-2 text-xs text-rose-200">{passwordErrors.password}</p>
              ) : null}
            </div>

            <FeedbackBox feedback={passwordFeedback} />

            <Button type="submit" disabled={passwordSaving}>
              {passwordSaving ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
              {passwordSaving ? copy.saving : copy.savePassword}
            </Button>
          </form>

          <div className="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4">
            <div className="flex items-start gap-3">
              <ShieldCheck className="mt-0.5 h-5 w-5 text-gold-100" />
              <div>
                <h3 className="text-base font-semibold text-white">{copy.helpTitle}</h3>
                <p className="mt-2 text-sm leading-7 text-white/72">{copy.helpText}</p>
                <Button as={Link} to="/xechasa-kodiko" variant="secondary" size="sm" className="mt-3">
                  {copy.forgotPassword}
                </Button>
              </div>
            </div>
          </div>
        </CardSurface>
      </div>
    </div>
  )
}

export default AccountSettingsPage

