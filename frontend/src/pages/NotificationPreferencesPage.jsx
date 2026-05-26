import { BellRing, Mail, ShieldCheck, Sparkles } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { Navigate } from 'react-router-dom'
import CardSurface from '@/components/ui/CardSurface'
import Button from '@/components/ui/Button'
import SectionHeader from '@/components/ui/SectionHeader'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { cardoraService } from '@/services/cardoraService'
import { normalizeNotificationPreferences } from '@/utils/notificationPreferences'

const CATEGORY_META = {
  messages: {
    icon: BellRing,
    el: {
      title: 'Μηνύματα',
      description:
        'Νέα μηνύματα, απαντήσεις μέσα σε συνομιλίες και βασικές ενημερώσεις επικοινωνίας μέσα στην Cardora.',
    },
    en: {
      title: 'Messages',
      description:
        'New messages, replies in conversations and key communication updates inside Cardora.',
    },
  },
  orders: {
    icon: Sparkles,
    el: {
      title: 'Παραγγελίες & αγορές',
      description:
        'Πληρωμές, αποστολές, επιβεβαίωση παραλαβής, releases, reviews και σημαντικές αλλαγές κατάστασης.',
    },
    en: {
      title: 'Orders & activity',
      description:
        'Payments, shipping, delivery confirmation, releases, reviews and important order status changes.',
    },
  },
  follows: {
    icon: BellRing,
    el: {
      title: 'Ακολουθείς & νέα listings',
      description:
        'Ενημερώσεις όταν collectors ή sellers που ακολουθείς βγάζουν νέο listing live στην πλατφόρμα.',
    },
    en: {
      title: 'Follows & new listings',
      description:
        'Updates when collectors or sellers you follow publish a new live listing on the marketplace.',
    },
  },
  support: {
    icon: Mail,
    el: {
      title: 'Υποστήριξη',
      description:
        'Απαντήσεις σε support tickets, follow-ups από την ομάδα και πρακτικές ενημερώσεις για ανοιχτά αιτήματα.',
    },
    en: {
      title: 'Support',
      description:
        'Replies to support tickets, team follow-ups and practical updates about open requests.',
    },
  },
  security: {
    icon: ShieldCheck,
    el: {
      title: 'Ασφάλεια & λογαριασμός',
      description:
        'Κρίσιμες ειδοποιήσεις για verification, κωδικούς, email επιβεβαίωση, αλλαγές πρόσβασης και προστασία λογαριασμού.',
      locked: 'Αυτές οι ειδοποιήσεις μένουν πάντα ενεργές για λόγους ασφάλειας.',
    },
    en: {
      title: 'Security & account',
      description:
        'Critical alerts for verification, passwords, email verification, access changes and account protection.',
      locked: 'These notifications stay on at all times for security reasons.',
    },
  },
}

function ToggleRow({ label, checked, disabled, onChange }) {
  return (
    <label
      className={`flex items-center justify-between rounded-2xl border px-4 py-3 text-sm transition ${
        disabled
          ? 'border-white/8 bg-white/5 text-white/45'
          : 'border-white/10 bg-white/5 text-white/82 hover:border-gold-300/30'
      }`}
    >
      <span>{label}</span>
      <button
        type="button"
        disabled={disabled}
        onClick={onChange}
        className={`relative inline-flex h-7 w-14 items-center rounded-full border transition ${
          checked ? 'border-gold-300/45 bg-gold-300/25' : 'border-white/10 bg-white/8'
        } ${disabled ? 'cursor-not-allowed opacity-70' : ''}`}
        aria-pressed={checked}
      >
        <span
          className={`inline-block h-5 w-5 rounded-full transition ${
            checked ? 'translate-x-8 bg-gold-100' : 'translate-x-1 bg-white/80'
          }`}
        />
      </button>
    </label>
  )
}

function NotificationPreferencesPage() {
  const { locale } = useI18n()
  const { currentUser, refreshCurrentUser } = useAuth()
  const [preferences, setPreferences] = useState(() =>
    normalizeNotificationPreferences(currentUser?.notificationPreferences),
  )
  const [isSaving, setIsSaving] = useState(false)
  const [feedback, setFeedback] = useState(null)

  useEffect(() => {
    setPreferences(normalizeNotificationPreferences(currentUser?.notificationPreferences))
  }, [currentUser?.notificationPreferences])

  const copy = useMemo(
    () =>
      locale === 'en'
        ? {
            eyebrow: 'Notification settings',
            title: 'Choose how Cardora keeps you updated',
            description:
              'Control which marketplace events reach you inside the app and which ones also come to your inbox.',
            intro:
              'You can fine-tune in-app and email notifications for the main flows of the marketplace. Security alerts remain active because they protect account access, verification and payout readiness.',
            inApp: 'In-app',
            email: 'Email',
            save: 'Save preferences',
            saving: 'Saving...',
            saved: 'Your notification preferences were updated.',
            failed: 'The preferences could not be saved. Please try again.',
          }
        : {
            eyebrow: 'Ρυθμίσεις ειδοποιήσεων',
            title: 'Διάλεξε πώς θέλεις να σε ενημερώνει η Cardora',
            description:
              'Ρύθμισε ποια events του marketplace θα βλέπεις μέσα στην εφαρμογή και ποια θέλεις να έρχονται και στο email σου.',
            intro:
              'Μπορείς να ελέγχεις ξεχωριστά τις ειδοποιήσεις μέσα στην πλατφόρμα και τα email updates για τα βασικά flows της Cardora. Οι κρίσιμες ειδοποιήσεις ασφάλειας μένουν ενεργές, γιατί σχετίζονται με πρόσβαση, verification και payout readiness.',
            inApp: 'Μέσα στην εφαρμογή',
            email: 'Στο email',
            save: 'Αποθήκευση ρυθμίσεων',
            saving: 'Αποθήκευση...',
            saved: 'Οι ρυθμίσεις ειδοποιήσεων αποθηκεύτηκαν.',
            failed: 'Δεν έγινε αποθήκευση των ρυθμίσεων. Δοκίμασε ξανά.',
          },
    [locale],
  )

  if (!currentUser) {
    return <Navigate to="/eisodos" replace state={{ from: '/rythmiseis-eidopoiiseon' }} />
  }

  const handleToggle = (category, channel) => {
    setFeedback(null)
    setPreferences((previous) => ({
      ...previous,
      [category]: {
        ...previous[category],
        [channel]: !previous[category][channel],
      },
    }))
  }

  const handleSave = async () => {
    setIsSaving(true)
    setFeedback(null)

    try {
      await cardoraService.updateProfile({
        notification_preferences: preferences,
      })
      await refreshCurrentUser()
      setFeedback({ tone: 'success', text: copy.saved })
    } catch (error) {
      setFeedback({ tone: 'danger', text: error?.message || copy.failed })
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />

      <CardSurface className="mt-6">
        <p className="text-sm leading-8 text-white/78">{copy.intro}</p>
      </CardSurface>

      {feedback ? (
        <div
          className={`mt-5 rounded-2xl border px-4 py-3 text-sm ${
            feedback.tone === 'success'
              ? 'border-emerald-400/25 bg-emerald-500/10 text-emerald-100'
              : 'border-rose-400/25 bg-rose-500/10 text-rose-100'
          }`}
        >
          {feedback.text}
        </div>
      ) : null}

      <div className="mt-6 grid gap-4">
        {Object.entries(CATEGORY_META).map(([key, meta]) => {
          const Icon = meta.icon
          const sectionCopy = meta[locale] ?? meta.el
          const current = preferences[key]
          const isLocked = Boolean(current?.locked)

          return (
            <CardSurface key={key}>
              <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div className="max-w-2xl">
                  <div className="flex items-center gap-3">
                    <div className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-gold-300/18 bg-gold-300/10 text-gold-100">
                      <Icon className="h-5 w-5" />
                    </div>
                    <div>
                      <h2 className="text-2xl font-semibold text-white">{sectionCopy.title}</h2>
                      <p className="mt-1 text-sm leading-7 text-white/72">{sectionCopy.description}</p>
                    </div>
                  </div>
                  {isLocked ? (
                    <div className="mt-4 rounded-2xl border border-gold-300/20 bg-gold-300/10 px-4 py-3 text-sm text-gold-50">
                      {sectionCopy.locked}
                    </div>
                  ) : null}
                </div>

                <div className="grid min-w-[320px] gap-3 md:grid-cols-2">
                  <ToggleRow
                    label={copy.inApp}
                    checked={Boolean(current?.in_app)}
                    disabled={isLocked}
                    onChange={() => handleToggle(key, 'in_app')}
                  />
                  <ToggleRow
                    label={copy.email}
                    checked={Boolean(current?.email)}
                    disabled={isLocked}
                    onChange={() => handleToggle(key, 'email')}
                  />
                </div>
              </div>
            </CardSurface>
          )
        })}
      </div>

      <div className="mt-8 flex flex-wrap gap-3">
        <Button onClick={handleSave} disabled={isSaving}>
          {isSaving ? copy.saving : copy.save}
        </Button>
      </div>
    </div>
  )
}

export default NotificationPreferencesPage
