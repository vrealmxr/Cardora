import { Link } from 'react-router-dom'
import { useMemo, useState } from 'react'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input, Select, Textarea } from '@/components/ui/Input'
import SectionHeader from '@/components/ui/SectionHeader'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'

const CONTENT_TYPE_OPTIONS = {
  el: [
    { value: 'listing', label: 'Αγγελία / listing' },
    { value: 'profile', label: 'Προφίλ χρήστη' },
    { value: 'message', label: 'Μήνυμα' },
    { value: 'draw', label: 'Κλήρωση / draw' },
    { value: 'review', label: 'Αξιολόγηση' },
    { value: 'blog', label: 'Άρθρο / περιεχόμενο' },
    { value: 'other', label: 'Άλλο' },
  ],
  en: [
    { value: 'listing', label: 'Listing' },
    { value: 'profile', label: 'User profile' },
    { value: 'message', label: 'Message' },
    { value: 'draw', label: 'Draw' },
    { value: 'review', label: 'Review' },
    { value: 'blog', label: 'Article / content' },
    { value: 'other', label: 'Other' },
  ],
}

const REASON_OPTIONS = {
  el: [
    { value: 'illegal_goods', label: 'Παράνομα ή απαγορευμένα αγαθά' },
    { value: 'counterfeit', label: 'Counterfeit / παραποίηση' },
    { value: 'fraud_or_impersonation', label: 'Απάτη / πλαστοπροσωπία' },
    { value: 'ip_infringement', label: 'Προσβολή δικαιωμάτων IP' },
    { value: 'harassment_or_threats', label: 'Παρενοχλητικό ή απειλητικό περιεχόμενο' },
    { value: 'unsafe_content', label: 'Επικίνδυνο ή μη ασφαλές περιεχόμενο' },
    { value: 'privacy_violation', label: 'Παραβίαση προσωπικών δεδομένων / ιδιωτικότητας' },
    { value: 'other', label: 'Άλλος σοβαρός λόγος' },
  ],
  en: [
    { value: 'illegal_goods', label: 'Illegal or prohibited goods' },
    { value: 'counterfeit', label: 'Counterfeit / forgery' },
    { value: 'fraud_or_impersonation', label: 'Fraud / impersonation' },
    { value: 'ip_infringement', label: 'IP infringement' },
    { value: 'harassment_or_threats', label: 'Harassment or threats' },
    { value: 'unsafe_content', label: 'Unsafe content' },
    { value: 'privacy_violation', label: 'Privacy violation' },
    { value: 'other', label: 'Other serious issue' },
  ],
}

const ACTION_OPTIONS = {
  el: [
    { value: 'review_and_remove', label: 'Έλεγχος και πιθανή αφαίρεση' },
    { value: 'temporary_restriction', label: 'Προσωρινός περιορισμός ορατότητας' },
    { value: 'seller_review', label: 'Έλεγχος λογαριασμού / seller review' },
    { value: 'urgent_escalation', label: 'Άμεση κλιμάκωση' },
  ],
  en: [
    { value: 'review_and_remove', label: 'Review and possible removal' },
    { value: 'temporary_restriction', label: 'Temporary visibility restriction' },
    { value: 'seller_review', label: 'Account / seller review' },
    { value: 'urgent_escalation', label: 'Urgent escalation' },
  ],
}

function DsaNoticeActionPage() {
  const { locale } = useI18n()
  const { currentUser } = useAuth()
  const { createSupportTicket } = useMarketplace()
  const [submitError, setSubmitError] = useState('')
  const [createdTicket, setCreatedTicket] = useState(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const copy = useMemo(
    () =>
      locale === 'en'
        ? {
            eyebrow: 'DSA Notice & Action',
            title: 'Report illegal or prohibited content on Cardora',
            description:
              'Use this form when you believe a listing, profile, message or other content on Cardora is illegal, unsafe or clearly incompatible with the platform rules.',
            introTitle: 'When to use this form',
            introText:
              'This route is for serious, structured notices. For everyday order help, shipping questions or account support, use the regular support center instead.',
            authTitle: 'You need to be signed in',
            authText:
              'Cardora keeps notices tied to a real account so the moderation team can follow up, request more details and keep an evidence trail.',
            authPrimary: 'Sign in',
            authSecondary: 'Open support center',
            formTitle: 'Submit a structured notice',
            formDescription:
              'Please be specific. A precise notice with a URL, reason and supporting context is much easier to assess fairly and quickly.',
            subject: 'Short subject',
            contentType: 'Reported content type',
            reportedUrl: 'Reported URL',
            reportedUser: 'Reported user / profile reference',
            reason: 'Reason for notice',
            requestedAction: 'Requested action',
            descriptionLabel: 'Supporting context',
            descriptionPlaceholder:
              'Explain what you found, why it appears unlawful or prohibited, and include timestamps, screenshots or order context if relevant.',
            subjectPlaceholder: 'For example: Counterfeit graded card listing',
            reportedUrlPlaceholder: 'https://cardora.example/listings/...',
            reportedUserPlaceholder: '@username or profile handle if no direct URL is available',
            goodFaith: 'I confirm that I am submitting this notice in good faith.',
            accuracy: 'I confirm that the information I provided is accurate to the best of my knowledge.',
            submit: 'Submit notice',
            submitting: 'Submitting...',
            successTitle: 'Your notice was submitted',
            successText: (id) => `Cardora created moderation ticket ${id}. The team can now review it from the DSA queue.`,
            helperTitle: 'What happens next',
            helperBullets: [
              'The notice is stored in a dedicated moderation queue with high priority.',
              'The team reviews the reported target, evidence and related marketplace context.',
              'Cardora may remove content, restrict visibility, freeze flows or ask for more information.',
            ],
            termsCta: 'Terms',
            policyCta: 'Policy',
          }
        : {
            eyebrow: 'DSA Notice & Action',
            title: 'Αναφορά παράνομου ή απαγορευμένου περιεχομένου στην Cardora',
            description:
              'Χρησιμοποίησε αυτή τη φόρμα όταν πιστεύεις ότι μια αγγελία, ένα προφίλ, ένα μήνυμα ή άλλο περιεχόμενο στην Cardora είναι παράνομο, επικίνδυνο ή σαφώς αντίθετο με τους κανόνες της πλατφόρμας.',
            introTitle: 'Πότε να χρησιμοποιήσεις αυτή τη φόρμα',
            introText:
              'Αυτή η διαδρομή είναι για σοβαρές, τεκμηριωμένες αναφορές. Για καθημερινή βοήθεια σε παραγγελίες, αποστολές ή θέματα λογαριασμού, χρησιμοποίησε το κανονικό support center.',
            authTitle: 'Χρειάζεται να είσαι συνδεδεμένος',
            authText:
              'Η Cardora κρατά τις αναφορές δεμένες με πραγματικό λογαριασμό, ώστε η ομάδα moderation να μπορεί να ζητήσει διευκρινίσεις και να κρατήσει σωστό αποδεικτικό ίχνος.',
            authPrimary: 'Σύνδεση',
            authSecondary: 'Άνοιγμα support center',
            formTitle: 'Υποβολή δομημένης αναφοράς',
            formDescription:
              'Γίνε όσο πιο συγκεκριμένος μπορείς. Μια καθαρή αναφορά με URL, λόγο και supporting context αξιολογείται πιο γρήγορα και πιο δίκαια.',
            subject: 'Σύντομο θέμα',
            contentType: 'Τύπος αναφερόμενου περιεχομένου',
            reportedUrl: 'URL περιεχομένου',
            reportedUser: 'Αναφερόμενος χρήστης / profile reference',
            reason: 'Λόγος αναφοράς',
            requestedAction: 'Επιθυμητή ενέργεια',
            descriptionLabel: 'Supporting context',
            descriptionPlaceholder:
              'Εξήγησε τι εντόπισες, γιατί θεωρείς ότι είναι παράνομο ή απαγορευμένο, και πρόσθεσε timestamps, screenshots ή order context όπου βοηθά.',
            subjectPlaceholder: 'Π.χ. Αγγελία counterfeit graded card',
            reportedUrlPlaceholder: 'https://cardora.example/listings/...',
            reportedUserPlaceholder: '@username ή profile handle αν δεν υπάρχει άμεσο URL',
            goodFaith: 'Επιβεβαιώνω ότι υποβάλλω την αναφορά καλόπιστα.',
            accuracy: 'Επιβεβαιώνω ότι οι πληροφορίες που δίνω είναι ακριβείς κατά το καλύτερο της γνώσης μου.',
            submit: 'Υποβολή αναφοράς',
            submitting: 'Υποβολή...',
            successTitle: 'Η αναφορά σου καταχωρίστηκε',
            successText: (id) => `Η Cardora δημιούργησε ticket moderation ${id}. Η ομάδα μπορεί πλέον να το αξιολογήσει από το DSA queue.`,
            helperTitle: 'Τι γίνεται μετά',
            helperBullets: [
              'Η αναφορά μπαίνει σε ξεχωριστό moderation queue με υψηλή προτεραιότητα.',
              'Η ομάδα ελέγχει τον στόχο της αναφοράς, τα αποδεικτικά και το σχετικό marketplace context.',
              'Η Cardora μπορεί να αφαιρέσει περιεχόμενο, να περιορίσει ορατότητα, να παγώσει ροές ή να ζητήσει επιπλέον στοιχεία.',
            ],
            termsCta: 'Όροι χρήσης',
            policyCta: 'Απαγορευμένα αντικείμενα',
          },
    [locale],
  )

  const contentTypes = CONTENT_TYPE_OPTIONS[locale] ?? CONTENT_TYPE_OPTIONS.el
  const reasons = REASON_OPTIONS[locale] ?? REASON_OPTIONS.el
  const actions = ACTION_OPTIONS[locale] ?? ACTION_OPTIONS.el

  const handleSubmit = async (event) => {
    event.preventDefault()
    const form = new FormData(event.currentTarget)

    setIsSubmitting(true)
    setSubmitError('')

    try {
      const ticket = await createSupportTicket({
        category: 'dsa_notice',
        priority: 'high',
        subject: String(form.get('subject') || '').trim(),
        description: String(form.get('description') || '').trim(),
        metadata: {
          reported_content_type: String(form.get('reportedContentType') || '').trim(),
          reported_url: String(form.get('reportedUrl') || '').trim(),
          reported_user_reference: String(form.get('reportedUserReference') || '').trim(),
          notice_reason: String(form.get('noticeReason') || '').trim(),
          requested_action: String(form.get('requestedAction') || '').trim(),
          supporting_context: String(form.get('description') || '').trim(),
          good_faith_confirmed: form.get('goodFaithConfirmed') === 'on',
          accuracy_confirmed: form.get('accuracyConfirmed') === 'on',
          reporter_email: currentUser?.email || null,
        },
      })

      setCreatedTicket(ticket)
      event.currentTarget.reset()
    } catch (error) {
      setSubmitError(error.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />

      <div className="grid gap-8 xl:grid-cols-[1.05fr,0.95fr]">
        <div className="space-y-6">
          <CardSurface>
            <Badge tone="muted">Notice intake</Badge>
            <h3 className="mt-4 font-display text-3xl text-white">{copy.introTitle}</h3>
            <p className="mt-4 text-sm leading-7 text-mist">{copy.introText}</p>
          </CardSurface>

          <CardSurface>
            <h3 className="font-display text-3xl text-white">{copy.helperTitle}</h3>
            <div className="mt-5 space-y-3">
              {copy.helperBullets.map((item) => (
                <div key={item} className="rounded-[22px] border border-white/8 bg-white/5 px-4 py-3 text-sm leading-7 text-white/88">
                  {item}
                </div>
              ))}
            </div>
          </CardSurface>
        </div>

        {!currentUser ? (
          <CardSurface>
            <Badge tone="warning">Account required</Badge>
            <h3 className="mt-4 font-display text-3xl text-white">{copy.authTitle}</h3>
            <p className="mt-4 text-sm leading-7 text-mist">{copy.authText}</p>
            <div className="mt-6 flex flex-wrap gap-3">
              <Button as={Link} to="/auth">{copy.authPrimary}</Button>
              <Button as={Link} to="/kentro-ypostiriksis" variant="secondary">{copy.authSecondary}</Button>
            </div>
          </CardSurface>
        ) : (
          <CardSurface>
            <Badge tone="gold">Moderation queue</Badge>
            <h3 className="mt-4 font-display text-3xl text-white">{copy.formTitle}</h3>
            <p className="mt-4 text-sm leading-7 text-mist">{copy.formDescription}</p>

            {createdTicket ? (
              <div className="mt-5 rounded-[24px] border border-emerald-400/20 bg-emerald-500/10 p-4 text-sm text-emerald-800">
                <p className="font-semibold">{copy.successTitle}</p>
                <p className="mt-2">{copy.successText(createdTicket.id)}</p>
              </div>
            ) : null}

            {submitError ? (
              <div className="mt-5 rounded-[24px] border border-amber-400/20 bg-amber-500/10 p-4 text-sm text-amber-800">
                {submitError}
              </div>
            ) : null}

            <form onSubmit={handleSubmit} className="mt-5 space-y-4">
              <div>
                <label className="mb-2 block text-sm text-mist">{copy.subject}</label>
                <Input name="subject" placeholder={copy.subjectPlaceholder} required />
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <div>
                  <label className="mb-2 block text-sm text-mist">{copy.contentType}</label>
                  <Select name="reportedContentType" defaultValue={contentTypes[0]?.value}>
                    {contentTypes.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </Select>
                </div>
                <div>
                  <label className="mb-2 block text-sm text-mist">{copy.reason}</label>
                  <Select name="noticeReason" defaultValue={reasons[0]?.value}>
                    {reasons.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </Select>
                </div>
              </div>

              <div>
                <label className="mb-2 block text-sm text-mist">{copy.reportedUrl}</label>
                <Input name="reportedUrl" placeholder={copy.reportedUrlPlaceholder} />
              </div>

              <div>
                <label className="mb-2 block text-sm text-mist">{copy.reportedUser}</label>
                <Input name="reportedUserReference" placeholder={copy.reportedUserPlaceholder} />
              </div>

              <div>
                <label className="mb-2 block text-sm text-mist">{copy.requestedAction}</label>
                <Select name="requestedAction" defaultValue={actions[0]?.value}>
                  {actions.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </Select>
              </div>

              <div>
                <label className="mb-2 block text-sm text-mist">{copy.descriptionLabel}</label>
                <Textarea name="description" placeholder={copy.descriptionPlaceholder} required />
              </div>

              <label className="flex items-start gap-3 rounded-[20px] border border-white/8 bg-white/5 px-4 py-3 text-sm text-white/88">
                <input name="goodFaithConfirmed" type="checkbox" className="mt-1" required />
                <span>{copy.goodFaith}</span>
              </label>

              <label className="flex items-start gap-3 rounded-[20px] border border-white/8 bg-white/5 px-4 py-3 text-sm text-white/88">
                <input name="accuracyConfirmed" type="checkbox" className="mt-1" required />
                <span>{copy.accuracy}</span>
              </label>

              <div className="flex flex-wrap gap-3 pt-2">
                <Button type="submit" disabled={isSubmitting}>{isSubmitting ? copy.submitting : copy.submit}</Button>
                <Button as={Link} to="/oroi-xrisis" variant="secondary">{copy.termsCta}</Button>
                <Button as={Link} to="/apagorevmena-antikeimena" variant="secondary">{copy.policyCta}</Button>
              </div>
            </form>
          </CardSurface>
        )}
      </div>
    </div>
  )
}

export default DsaNoticeActionPage
