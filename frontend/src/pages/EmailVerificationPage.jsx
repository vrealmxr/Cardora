import { CheckCircle2, Mail, RefreshCcw, ShieldCheck, TriangleAlert } from 'lucide-react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { cardoraService } from '@/services/cardoraService'
import { normalizeTextTree } from '@/utils/textEncoding'

function EmailVerificationPage() {
  const [searchParams] = useSearchParams()
  const { locale } = useI18n()
  const { currentUser, isAuthenticated, refreshCurrentUser } = useAuth()
  const [isSending, setIsSending] = useState(false)
  const [feedback, setFeedback] = useState('')
  const hasSyncedVerifiedStateRef = useRef(false)

  const copy = normalizeTextTree(
    locale === 'en'
      ? {
          eyebrow: 'Email verification',
          title: 'Confirm your email to fully activate your Cardora account',
          description:
            'We have sent a verification email so Cardora can safely unlock messages, selling access and protected account actions.',
          sentBadge: 'Email sent',
          verifiedBadge: 'Verified',
          invalidBadge: 'Link issue',
          sentTitle: 'Check your inbox',
          sentText:
            'Open the verification email from Cardora and click the button inside. If it does not appear right away, check spam or promotions as well.',
          verifiedTitle: 'Your email is now verified',
          verifiedText:
            'Everything is ready on the email side. You can continue to your profile and finish any remaining account requirements there.',
          alreadyVerifiedTitle: 'This email was already verified',
          alreadyVerifiedText:
            'Your account already has a confirmed email, so there is nothing else to do here.',
          invalidTitle: 'This verification link is no longer valid',
          invalidText:
            'The link may have expired or may have already been used. You can send yourself a fresh verification email below.',
          benefitsTitle: 'Why this matters',
          benefits: [
            'Safer account recovery and protected access changes',
            'Clearer trust signal across your Cardora profile',
            'A smoother path into selling, messages and account verification',
          ],
          resend: 'Send verification email again',
          sending: 'Sending...',
          profile: 'Go to profile',
          login: 'Login',
          register: 'Register',
          backHome: 'Return to home',
          helper:
            'Use the same email address you registered with. If you changed email later, your newest verification email is the one that counts.',
        }
      : {
          eyebrow: 'Î•Ï€Î¹Î²ÎµÎ²Î±Î¯Ï‰ÏƒÎ· email',
          title: 'Î•Ï€Î¹Î²ÎµÎ²Î±Î¯Ï‰ÏƒÎµ Ï„Î¿ email ÏƒÎ¿Ï… Î³Î¹Î± Î½Î± ÎµÎ½ÎµÏÎ³Î¿Ï€Î¿Î¹Î·Î¸ÎµÎ¯ Ï€Î»Î®ÏÏ‰Ï‚ Î¿ Î»Î¿Î³Î±ÏÎ¹Î±ÏƒÎ¼ÏŒÏ‚ ÏƒÎ¿Ï…',
          description:
            'Î£Ï„ÎµÎ¯Î»Î±Î¼Îµ email ÎµÏ€Î¹Î²ÎµÎ²Î±Î¯Ï‰ÏƒÎ·Ï‚ ÏŽÏƒÏ„Îµ Î· Cardora Î½Î± Î¾ÎµÎºÎ»ÎµÎ¹Î´ÏŽÏƒÎµÎ¹ Î¼Îµ Î±ÏƒÏ†Î¬Î»ÎµÎ¹Î± Î¼Î·Î½ÏÎ¼Î±Ï„Î±, Ï€Ï‰Î»Î®ÏƒÎµÎ¹Ï‚ ÎºÎ±Î¹ Ï€ÏÎ¿ÏƒÏ„Î±Ï„ÎµÏ…Î¼Î­Î½ÎµÏ‚ ÎµÎ½Î­ÏÎ³ÎµÎ¹ÎµÏ‚ Î»Î¿Î³Î±ÏÎ¹Î±ÏƒÎ¼Î¿Ï.',
          sentBadge: 'Î£Ï„Î¬Î»Î¸Î·ÎºÎµ email',
          verifiedBadge: 'Î•Ï€Î¹Î²ÎµÎ²Î±Î¹Ï‰Î¼Î­Î½Î¿',
          invalidBadge: 'Î ÏÏŒÎ²Î»Î·Î¼Î± ÏƒÏ…Î½Î´Î­ÏƒÎ¼Î¿Ï…',
          sentTitle: 'ÎˆÎ»ÎµÎ³Î¾Îµ Ï„Î± ÎµÎ¹ÏƒÎµÏÏ‡ÏŒÎ¼ÎµÎ½Î¬ ÏƒÎ¿Ï…',
          sentText:
            'Î†Î½Î¿Î¹Î¾Îµ Ï„Î¿ email ÎµÏ€Î¹Î²ÎµÎ²Î±Î¯Ï‰ÏƒÎ·Ï‚ Î±Ï€ÏŒ Ï„Î·Î½ Cardora ÎºÎ±Î¹ Ï€Î¬Ï„Î·ÏƒÎµ Ï„Î¿ ÎºÎ¿Ï…Î¼Ï€Î¯ Î¼Î­ÏƒÎ± ÏƒÏ„Î¿ Î¼Î®Î½Ï…Î¼Î±. Î‘Î½ Î´ÎµÎ½ Ï„Î¿ Î´ÎµÎ¹Ï‚ Î±Î¼Î­ÏƒÏ‰Ï‚, Î­Î»ÎµÎ³Î¾Îµ ÎºÎ±Î¹ Î±Î½ÎµÏ€Î¹Î¸ÏÎ¼Î·Ï„Î± Î® promotions.',
          verifiedTitle: 'Î¤Î¿ email ÏƒÎ¿Ï… ÎµÏ€Î¹Î²ÎµÎ²Î±Î¹ÏŽÎ¸Î·ÎºÎµ',
          verifiedText:
            'Î£Ï„Î¿ ÎºÎ¿Î¼Î¼Î¬Ï„Î¹ Ï„Î¿Ï… email ÎµÎ¯Î¼Î±ÏƒÏ„Îµ Î­Ï„Î¿Î¹Î¼Î¿Î¹. ÎœÏ€Î¿ÏÎµÎ¯Ï‚ Î½Î± ÏƒÏ…Î½ÎµÏ‡Î¯ÏƒÎµÎ¹Ï‚ ÏƒÏ„Î¿ Ï€ÏÎ¿Ï†Î¯Î» ÏƒÎ¿Ï… ÎºÎ±Î¹ Î½Î± Î¿Î»Î¿ÎºÎ»Î·ÏÏŽÏƒÎµÎ¹Ï‚ ÏŒ,Ï„Î¹ Î¬Î»Î»Î¿ Î±Ï€Î¿Î¼Î­Î½ÎµÎ¹ ÏƒÏ„Î¿Î½ Î»Î¿Î³Î±ÏÎ¹Î±ÏƒÎ¼ÏŒ.',
          alreadyVerifiedTitle: 'Î‘Ï…Ï„ÏŒ Ï„Î¿ email Î®Ï„Î±Î½ Î®Î´Î· ÎµÏ€Î¹Î²ÎµÎ²Î±Î¹Ï‰Î¼Î­Î½Î¿',
          alreadyVerifiedText:
            'ÎŸ Î»Î¿Î³Î±ÏÎ¹Î±ÏƒÎ¼ÏŒÏ‚ ÏƒÎ¿Ï… Î­Ï‡ÎµÎ¹ Î®Î´Î· ÎµÏ€Î¹Î²ÎµÎ²Î±Î¹Ï‰Î¼Î­Î½Î¿ email, Î¬ÏÎ± ÎµÎ´ÏŽ Î´ÎµÎ½ Ï‡ÏÎµÎ¹Î¬Î¶ÎµÏ„Î±Î¹ Î½Î± ÎºÎ¬Î½ÎµÎ¹Ï‚ ÎºÎ¬Ï„Î¹ Î¬Î»Î»Î¿.',
          invalidTitle: 'ÎŸ ÏƒÏÎ½Î´ÎµÏƒÎ¼Î¿Ï‚ ÎµÏ€Î¹Î²ÎµÎ²Î±Î¯Ï‰ÏƒÎ·Ï‚ Î´ÎµÎ½ ÎµÎ¯Î½Î±Î¹ Ï€Î»Î­Î¿Î½ Î­Î³ÎºÏ…ÏÎ¿Ï‚',
          invalidText:
            'ÎŸ ÏƒÏÎ½Î´ÎµÏƒÎ¼Î¿Ï‚ Î¼Ï€Î¿ÏÎµÎ¯ Î½Î± Î­Ï‡ÎµÎ¹ Î»Î®Î¾ÎµÎ¹ Î® Î½Î± Î­Ï‡ÎµÎ¹ Î®Î´Î· Ï‡ÏÎ·ÏƒÎ¹Î¼Î¿Ï€Î¿Î¹Î·Î¸ÎµÎ¯. ÎœÏ€Î¿ÏÎµÎ¯Ï‚ Î½Î± ÏƒÏ„ÎµÎ¯Î»ÎµÎ¹Ï‚ Î½Î­Î¿ email ÎµÏ€Î¹Î²ÎµÎ²Î±Î¯Ï‰ÏƒÎ·Ï‚ Ï€Î¹Î¿ ÎºÎ¬Ï„Ï‰.',
          benefitsTitle: 'Î¤Î¹ Î¾ÎµÎºÎ»ÎµÎ¹Î´ÏŽÎ½ÎµÎ¹',
          benefits: [
            'Î Î¹Î¿ Î±ÏƒÏ†Î±Î»Î®Ï‚ Î±Î½Î¬ÎºÏ„Î·ÏƒÎ· Î»Î¿Î³Î±ÏÎ¹Î±ÏƒÎ¼Î¿Ï ÎºÎ±Î¹ Ï€ÏÎ¿ÏƒÏ„Î±Ï„ÎµÏ…Î¼Î­Î½ÎµÏ‚ Î±Î»Î»Î±Î³Î­Ï‚ Ï€ÏÏŒÏƒÎ²Î±ÏƒÎ·Ï‚',
            'ÎšÎ±Î¸Î±ÏÏŒÏ„ÎµÏÎ¿ trust signal Î¼Î­ÏƒÎ± ÏƒÏ„Î¿ Ï€ÏÎ¿Ï†Î¯Î» ÏƒÎ¿Ï… ÏƒÏ„Î·Î½ Cardora',
            'Î Î¹Î¿ Î¿Î¼Î±Î»Î® Ï€ÏÏŒÏƒÎ²Î±ÏƒÎ· ÏƒÎµ Ï€Ï‰Î»Î®ÏƒÎµÎ¹Ï‚, Î¼Î·Î½ÏÎ¼Î±Ï„Î± ÎºÎ±Î¹ ÎµÏ€Î±Î»Î®Î¸ÎµÏ…ÏƒÎ· Î»Î¿Î³Î±ÏÎ¹Î±ÏƒÎ¼Î¿Ï',
          ],
          resend: 'ÎÎ­Î¿ email ÎµÏ€Î¹Î²ÎµÎ²Î±Î¯Ï‰ÏƒÎ·Ï‚',
          sending: 'Î‘Ï€Î¿ÏƒÏ„Î¿Î»Î®...',
          profile: 'ÎœÎµÏ„Î¬Î²Î±ÏƒÎ· ÏƒÏ„Î¿ Ï€ÏÎ¿Ï†Î¯Î»',
          login: 'Î•Î¯ÏƒÎ¿Î´Î¿Ï‚',
          register: 'Î•Î³Î³ÏÎ±Ï†Î®',
          backHome: 'Î•Ï€Î¹ÏƒÏ„ÏÎ¿Ï†Î® ÏƒÏ„Î·Î½ Î±ÏÏ‡Î¹ÎºÎ®',
          helper:
            'Î§ÏÎ·ÏƒÎ¹Î¼Î¿Ï€Î¿Î¯Î·ÏƒÎµ Ï„Î¿ Î¯Î´Î¹Î¿ email Î¼Îµ Ï„Î¿ Î¿Ï€Î¿Î¯Î¿ Î³ÏÎ¬Ï†Ï„Î·ÎºÎµÏ‚. Î‘Î½ Î­Ï‡ÎµÎ¹Ï‚ Î±Î»Î»Î¬Î¾ÎµÎ¹ email Î±ÏÎ³ÏŒÏ„ÎµÏÎ±, Î¼ÎµÏ„ÏÎ¬ÎµÎ¹ Ï€Î¬Î½Ï„Î± Ï„Î¿ Ï€Î¹Î¿ Ï€ÏÏŒÏƒÏ†Î±Ï„Î¿ email ÎµÏ€Î¹Î²ÎµÎ²Î±Î¯Ï‰ÏƒÎ·Ï‚.',
        },
  )

  const status = searchParams.get('status')
  const sent = searchParams.get('sent') === '1'
  const welcome = searchParams.get('welcome') === '1'

  useEffect(() => {
    const shouldSyncStatus = status === 'verified' || status === 'already-verified'

    if (!shouldSyncStatus) {
      hasSyncedVerifiedStateRef.current = false
      return
    }

    if (!isAuthenticated || hasSyncedVerifiedStateRef.current) return

    hasSyncedVerifiedStateRef.current = true
    refreshCurrentUser().catch(() => {})
  }, [isAuthenticated, refreshCurrentUser, status])

  const state = useMemo(() => {
    if (status === 'verified') {
      return {
        badge: copy.verifiedBadge,
        tone: 'success',
        title: copy.verifiedTitle,
        text: copy.verifiedText,
        icon: CheckCircle2,
      }
    }

    if (status === 'already-verified') {
      return {
        badge: copy.verifiedBadge,
        tone: 'info',
        title: copy.alreadyVerifiedTitle,
        text: copy.alreadyVerifiedText,
        icon: ShieldCheck,
      }
    }

    if (status === 'expired' || status === 'invalid') {
      return {
        badge: copy.invalidBadge,
        tone: 'danger',
        title: copy.invalidTitle,
        text: copy.invalidText,
        icon: TriangleAlert,
      }
    }

    return {
      badge: copy.sentBadge,
      tone: 'gold',
      title: welcome
        ? (locale === 'en' ? 'Your account was created successfully' : 'Η εγγραφή σου ολοκληρώθηκε επιτυχώς')
        : copy.sentTitle,
      text: welcome
        ? (sent
          ? 'Your Cardora account is now active. We have already sent a verification email, so your next step is simply to confirm your inbox.'
          : (locale === 'en'
            ? 'Your account was created successfully, but we could not send the verification email automatically. Use the button below to send a fresh verification email now.'
            : 'Ο λογαριασμός δημιουργήθηκε κανονικά, αλλά δεν στάλθηκε αυτόματα email επιβεβαίωσης. Πάτησε παρακάτω για νέα αποστολή.'))
        : sent
          ? copy.sentText
          : copy.helper,
      icon: Mail,
    }
  }, [copy, locale, sent, status, welcome])

  const handleResend = async () => {
    setIsSending(true)
    setFeedback('')

    try {
      const response = await cardoraService.resendVerificationEmail()
      setFeedback(response?.message ?? '')
    } catch (error) {
      setFeedback(error.message)
    } finally {
      setIsSending(false)
    }
  }

  const StateIcon = state.icon

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />

      <div className="grid gap-6 xl:grid-cols-[1.1fr,0.9fr]">
        <CardSurface className="p-6 sm:p-8">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="max-w-2xl">
              <Badge tone={state.tone}>{state.badge}</Badge>
              <div className="mt-5 flex items-start gap-4">
                <div className="rounded-2xl border border-gold-300/20 bg-gold-300/10 p-3 text-gold-100">
                  <StateIcon className="h-6 w-6" />
                </div>
                <div>
                  <h1 className="font-display text-4xl text-white">{state.title}</h1>
                  <p className="mt-3 text-sm leading-7 text-mist">{state.text}</p>
                </div>
              </div>
            </div>

            {currentUser?.email ? (
              <div className="min-w-[240px] rounded-[22px] border border-white/8 bg-white/5 px-4 py-4">
                <p className="text-[11px] uppercase tracking-[0.32em] text-white/45">Email</p>
                <p className="mt-2 break-all text-sm font-semibold text-white">{currentUser.email}</p>
                <p className="mt-2 text-xs leading-6 text-mist">
                  {currentUser.emailVerified ? copy.verifiedText : copy.helper}
                </p>
              </div>
            ) : null}
          </div>

          <div className="mt-8 flex flex-wrap gap-3">
            {isAuthenticated && !currentUser?.emailVerified ? (
              <Button onClick={handleResend} disabled={isSending}>
                <RefreshCcw className="h-4 w-4" />
                {isSending ? copy.sending : copy.resend}
              </Button>
            ) : null}

            {isAuthenticated ? (
              <Button as={Link} to="/profil" variant="secondary">
                {copy.profile}
              </Button>
            ) : (
              <>
                <Button as={Link} to="/eisodos">
                  {copy.login}
                </Button>
                <Button as={Link} to="/eggrafi" variant="secondary">
                  {copy.register}
                </Button>
              </>
            )}

            <Button as={Link} to="/" variant="ghost">
              {copy.backHome}
            </Button>
          </div>

          {feedback ? (
            <div className="mt-4 rounded-2xl border border-gold-300/20 bg-gold-300/10 px-4 py-3 text-sm text-gold-50">
              {feedback}
            </div>
          ) : null}
        </CardSurface>

        <CardSurface className="p-6 sm:p-8">
          <h2 className="font-display text-3xl text-white">{copy.benefitsTitle}</h2>
          <div className="mt-4 space-y-3">
            {copy.benefits.map((benefit) => (
              <div
                key={benefit}
                className="rounded-2xl border border-white/8 bg-white/5 px-4 py-3 text-sm leading-7 text-white/80"
              >
                {benefit}
              </div>
            ))}
          </div>
        </CardSurface>
      </div>
    </div>
  )
}

export default EmailVerificationPage
