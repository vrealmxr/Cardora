import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import VerificationSectionCard from '@/components/account/VerificationSectionCard'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { formatShortDateTime } from '@/utils/formatters'
import { getUserDisplayName } from '@/utils/helpers'
import { normalizeTextTree } from '@/utils/textEncoding'

const buildEmptyFieldValue = (field) => {
  if (field.type === 'checkbox') return false
  if (field.type === 'file') return field.multiple ? [] : null
  return ''
}

const hasDraftValue = (field, value) => {
  if (field.type === 'checkbox') return typeof value === 'boolean'
  if (field.type === 'file') {
    if (field.multiple) return Array.isArray(value) && value.length > 0
    return Boolean(value)
  }

  return value != null && String(value).trim() !== ''
}

const buildSectionDraft = (section, existingDraft = {}) => {
  const lastSubmission = section?.lastSubmission ?? {}

  return Object.fromEntries(
    (section?.fields ?? []).map((field) => {
      const existingValue = existingDraft[field.name]

      if (field.type === 'file') {
        if (field.multiple) {
          return [field.name, Array.isArray(existingValue) ? existingValue.filter(Boolean) : []]
        }

        return [field.name, existingValue ?? null]
      }

      if (hasDraftValue(field, existingValue)) {
        return [field.name, existingValue]
      }

      if (field.type === 'checkbox') {
        return [field.name, Boolean(lastSubmission[field.name])]
      }

      return [field.name, lastSubmission[field.name] ?? buildEmptyFieldValue(field)]
    }),
  )
}

const buildInitialDrafts = (accountVerification, existingDrafts = {}) =>
  Object.fromEntries(
    (accountVerification?.sections ?? []).map((section) => [
      section.id,
      buildSectionDraft(section, existingDrafts[section.id] ?? {}),
    ]),
  )

const hasExistingUploadForField = (section, fieldName) => {
  const uploads = Array.isArray(section?.uploads) ? section.uploads : []
  if (!uploads.length) return false

  if (uploads.some((upload) => upload.fieldName === fieldName)) {
    return true
  }

  const fileFields = (section?.fields ?? []).filter((field) => field.type === 'file')
  return fileFields.length === 1 && uploads.length > 0
}

function VerificationPage() {
  const { locale } = useI18n()
  const { currentUser, isAuthenticated } = useAuth()
  const { accountVerification, submitVerificationSection } = useMarketplace()
  const [drafts, setDrafts] = useState(() => buildInitialDrafts(accountVerification))
  const [errors, setErrors] = useState({})
  const [submittingSectionId, setSubmittingSectionId] = useState(null)

  const copy = normalizeTextTree(
    locale === 'en'
      ? {
          loginBadge: 'Sign in required',
          loginTitle: 'Sign in to complete account verification',
          loginText:
            'Identity, address and payout verification are available only to signed-in members.',
          login: 'Login',
          register: 'Register',
          eyebrow: 'Verification',
          title: 'Complete your account verification',
          description:
            'Add the documents Cardora needs for safer selling, stronger trust signals and access to payout features.',
          centerTitle: 'Verification center',
          centerText: (name) =>
            `${name} is currently working through the account verification steps used for safer listings, verified selling and payout access.`,
          progress: 'Progress',
          sectionsDone: 'sections fully approved',
          reviewSla: 'Review time',
          currentLimit: 'Current limit',
          afterApproval: 'After approval',
          unlocks: 'What this unlocks',
          checklist: 'Before you submit',
          stepsEyebrow: 'Verification Steps',
          stepsTitle: 'Upload the right documents for each section',
          stepsDescription:
            'Each section can be submitted separately and reviewed on its own.',
          activity: 'Activity log',
          faq: 'Verification FAQ',
          submitting: 'Your files and details are being submitted...',
          missingFields: (fields) => `Please complete the required fields: ${fields}`,
        }
      : {
          loginBadge: 'Απαιτείται σύνδεση',
          loginTitle: 'Συνδέσου για να ολοκληρώσεις την επαλήθευση λογαριασμού',
          loginText:
            'Η επαλήθευση ταυτότητας, διεύθυνσης και στοιχείων πληρωμών είναι διαθέσιμη μόνο σε συνδεδεμένα μέλη.',
          login: 'Είσοδος',
          register: 'Εγγραφή',
          eyebrow: 'Επαλήθευση',
          title: 'Ολοκλήρωσε την επαλήθευση του λογαριασμού σου',
          description:
            'Ανέβασε τα έγγραφα που χρειάζεται η Cardora για πιο ασφαλείς πωλήσεις, ισχυρότερα trust signals και πρόσβαση στα payout flows.',
          centerTitle: 'Κέντρο επαλήθευσης',
          centerText: (name) =>
            `Ο λογαριασμός του ${name} περνά τα βήματα επαλήθευσης που χρησιμοποιεί η Cardora για ασφαλέστερες αγγελίες, επιβεβαιωμένες πωλήσεις και πρόσβαση στα payouts.`,
          progress: 'Πρόοδος',
          sectionsDone: 'ενότητες πλήρως εγκεκριμένες',
          reviewSla: 'Χρόνος ελέγχου',
          currentLimit: 'Τρέχον όριο',
          afterApproval: 'Μετά την έγκριση',
          unlocks: 'Τι ξεκλειδώνει',
          checklist: 'Τι να έχεις έτοιμο',
          stepsEyebrow: 'Βήματα επαλήθευσης',
          stepsTitle: 'Ανέβασε τα σωστά έγγραφα σε κάθε ενότητα',
          stepsDescription:
            'Κάθε ενότητα μπορεί να υποβληθεί ξεχωριστά και να ελεγχθεί αυτόνομα.',
          activity: 'Ιστορικό ενεργειών',
          faq: 'Συχνές ερωτήσεις επαλήθευσης',
          submitting: 'Γίνεται υποβολή των αρχείων και των στοιχείων σου...',
          missingFields: (fields) => `Συμπλήρωσε τα υποχρεωτικά πεδία: ${fields}`,
        }
  )

  useEffect(() => {
    setDrafts((previous) => buildInitialDrafts(accountVerification, previous))
  }, [accountVerification])

  if (!isAuthenticated) {
    return (
      <div className="container pb-16">
        <CardSurface className="mx-auto max-w-3xl text-center">
          <Badge tone="gold">{copy.loginBadge}</Badge>
          <h1 className="mt-4 font-display text-4xl text-white">{copy.loginTitle}</h1>
          <p className="mt-4 text-sm leading-7 text-mist">{copy.loginText}</p>
          <div className="mt-6 flex justify-center gap-3">
            <Button as={Link} to="/eisodos">
              {copy.login}
            </Button>
            <Button as={Link} to="/eggrafi" variant="secondary">
              {copy.register}
            </Button>
          </div>
        </CardSurface>
      </div>
    )
  }

  if (!accountVerification) return null

  const handleDraftChange = (sectionId, fieldName, value) => {
    setDrafts((previous) => ({
      ...previous,
      [sectionId]: {
        ...previous[sectionId],
        [fieldName]: value,
      },
    }))
  }

  const handleSubmit = async (sectionId) => {
    const section = accountVerification.sections.find((item) => item.id === sectionId)
    if (!section) return

    const draft = drafts[sectionId] ?? {}
    const missingFields = section.fields.filter((field) => {
      if (!field.required) return false

      const value = draft[field.name]

      if (field.type === 'checkbox') return !value
      if (field.type === 'file') {
        const files = Array.isArray(value) ? value : value ? [value] : []
        return files.length === 0 && !hasExistingUploadForField(section, field.name)
      }

      return !String(value ?? '').trim()
    })

    if (missingFields.length) {
      setErrors((previous) => ({
        ...previous,
        [sectionId]: copy.missingFields(missingFields.map((field) => field.label).join(', ')),
      }))
      return
    }

    setErrors((previous) => ({ ...previous, [sectionId]: '' }))
    setSubmittingSectionId(sectionId)

    try {
      await submitVerificationSection(sectionId, draft)

      setDrafts((previous) => ({
        ...previous,
        [sectionId]: {
          ...previous[sectionId],
          ...Object.fromEntries(
            section.fields
              .filter((field) => field.type === 'file')
              .map((field) => [field.name, field.multiple ? [] : null]),
          ),
        },
      }))
    } catch (error) {
      setErrors((previous) => ({
        ...previous,
        [sectionId]: error.message,
      }))
    } finally {
      setSubmittingSectionId(null)
    }
  }

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />

      <div className="grid gap-6 xl:grid-cols-[1.15fr,0.85fr]">
        <CardSurface className="overflow-hidden p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <Badge tone="gold">{accountVerification.overallStatus}</Badge>
                <Badge tone="info">{accountVerification.trustTier}</Badge>
              </div>
              <h1 className="mt-4 font-display text-5xl text-white">{copy.centerTitle}</h1>
              <p className="mt-3 max-w-3xl text-sm leading-7 text-mist">
                {copy.centerText(getUserDisplayName(currentUser))}
              </p>
            </div>
            <div className="min-w-[220px] rounded-[22px] border border-gold-300/18 bg-gold-300/10 px-4 py-4">
              <p className="text-[11px] uppercase tracking-[0.32em] text-gold-100">{copy.progress}</p>
              <p className="mt-2 text-3xl font-semibold text-white">{accountVerification.progressPercentage}%</p>
              <p className="mt-2 text-sm text-gold-50">
                {accountVerification.completedLabel} {copy.sectionsDone}
              </p>
            </div>
          </div>

          <div className="mt-6 h-2 rounded-full bg-white/8">
            <div
              className="h-2 rounded-full bg-gradient-to-r from-gold-300 to-gold-500"
              style={{ width: `${accountVerification.progressPercentage}%` }}
            />
          </div>

          <div className="mt-6 grid gap-3 md:grid-cols-3">
            <div className="rounded-[20px] border border-white/8 bg-white/5 p-4">
              <p className="text-[11px] uppercase tracking-[0.28em] text-white/45">{copy.reviewSla}</p>
              <p className="mt-2 text-lg font-semibold text-white">{accountVerification.estimatedReviewTime}</p>
            </div>
            <div className="rounded-[20px] border border-white/8 bg-white/5 p-4">
              <p className="text-[11px] uppercase tracking-[0.28em] text-white/45">{copy.currentLimit}</p>
              <p className="mt-2 text-lg font-semibold text-white">{accountVerification.limits.current}</p>
            </div>
            <div className="rounded-[20px] border border-white/8 bg-white/5 p-4">
              <p className="text-[11px] uppercase tracking-[0.28em] text-white/45">{copy.afterApproval}</p>
              <p className="mt-2 text-lg font-semibold text-white">{accountVerification.limits.unlocked}</p>
            </div>
          </div>
        </CardSurface>

        <div className="space-y-6">
          <CardSurface>
            <h2 className="font-display text-3xl text-white">{copy.unlocks}</h2>
            <div className="mt-4 space-y-3">
              {accountVerification.benefits.map((benefit) => (
                <div key={benefit} className="rounded-2xl border border-white/8 bg-white/5 px-4 py-3 text-sm leading-7 text-white/80">
                  {benefit}
                </div>
              ))}
            </div>
          </CardSurface>

          <CardSurface>
            <h2 className="font-display text-3xl text-white">{copy.checklist}</h2>
            <div className="mt-4 space-y-3">
              {accountVerification.globalChecklist.map((item) => (
                <div key={item} className="rounded-2xl border border-white/8 bg-white/5 px-4 py-3 text-sm leading-7 text-white/80">
                  {item}
                </div>
              ))}
            </div>
          </CardSurface>
        </div>
      </div>

      <section className="mt-16 space-y-6">
        <SectionHeader
          eyebrow={copy.stepsEyebrow}
          title={copy.stepsTitle}
          description={copy.stepsDescription}
        />

        {accountVerification.sections.map((section) => (
          <VerificationSectionCard
            key={section.id}
            section={section}
            draft={drafts[section.id] ?? {}}
            error={section.id === submittingSectionId ? copy.submitting : errors[section.id]}
            onChange={(fieldName, value) => handleDraftChange(section.id, fieldName, value)}
            onSubmit={handleSubmit}
          />
        ))}
      </section>

      <section className="mt-16 grid gap-6 xl:grid-cols-[0.9fr,1.1fr]">
        <CardSurface>
          <h2 className="font-display text-3xl text-white">{copy.activity}</h2>
          <div className="mt-4 space-y-3">
            {accountVerification.activity.map((item) => (
              <div key={item.id} className="rounded-2xl border border-white/8 bg-white/5 px-4 py-3.5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <p className="font-semibold text-white">{item.title}</p>
                  <Badge tone={item.tone ?? 'gold'}>{formatShortDateTime(item.time)}</Badge>
                </div>
                <p className="mt-2 text-sm leading-7 text-mist">{item.text}</p>
              </div>
            ))}
          </div>
        </CardSurface>

        <CardSurface>
          <h2 className="font-display text-3xl text-white">{copy.faq}</h2>
          <div className="mt-4 space-y-4">
            {accountVerification.faq.map((item) => (
              <div key={item.question} className="rounded-2xl border border-white/8 bg-white/5 px-4 py-4">
                <p className="font-semibold text-white">{item.question}</p>
                <p className="mt-2 text-sm leading-7 text-mist">{item.answer}</p>
              </div>
            ))}
          </div>
        </CardSurface>
      </section>
    </div>
  )
}

export default VerificationPage


