import { Mail, MapPinned, MessageCircleMore } from 'lucide-react'
import { useState } from 'react'
import PageSeo from '@/components/PageSeo'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input, Select, Textarea } from '@/components/ui/Input'
import SectionHeader from '@/components/ui/SectionHeader'
import { useSeo } from '@/context/SeoContext'
import { useI18n } from '@/hooks/useI18n'

function ContactPage() {
  const { locale } = useI18n()
  const seo = useSeo('contact')
  const [submitted, setSubmitted] = useState(false)

  const copy =
    locale === 'en'
      ? {
          eyebrow: 'Contact',
          title: 'Talk to the Cardora team',
          description:
            'Whether you need help with an order, a verification question or a business request, you can reach us here.',
          successBadge: 'Message sent',
          successTitle: 'Thanks for reaching out.',
          successText: 'Our team will review your message and come back to you as soon as possible.',
          name: 'Name',
          email: 'Email',
          subject: 'Subject',
          reason: 'Reason for contact',
          message: 'Message',
          namePlaceholder: 'Your name',
          emailPlaceholder: 'you@example.com',
          subjectPlaceholder: 'For example: Question about a protected order',
          messagePlaceholder: 'Tell us a little more about what you need help with...',
          reasons: [
            'Order support',
            'Payment issue',
            'Dispute',
            'Seller support',
            'Business inquiry',
          ],
          send: 'Send message',
          faq: 'Go to FAQ',
          supportEmail: 'Support email',
          responseTime: 'Typical response time',
          responseTimeText: 'Usually within 12 hours for general requests',
          supportCategories: 'Support categories',
          categories: ['Orders', 'Selling', 'Escrow', 'Disputes', 'Payouts', 'Account safety'],
          officeTitle: 'Cardora office',
          officeText:
            'Our support and operations team is based in Athens, and this is the main point of contact for marketplace issues and partnerships.',
          location: 'Athens, Greece',
        }
      : {
          eyebrow: 'Επικοινωνία',
          title: 'Μίλησε με την ομάδα της Cardora',
          description:
            'Είτε χρειάζεσαι βοήθεια με μια παραγγελία, είτε έχεις απορία για την επαλήθευση ή ενδιαφέρεσαι για συνεργασία, μπορείς να μας βρεις εδώ.',
          successBadge: 'Το μήνυμα στάλθηκε',
          successTitle: 'Σε ευχαριστούμε για την επικοινωνία.',
          successText: 'Η ομάδα μας θα εξετάσει το μήνυμά σου και θα απαντήσει το συντομότερο δυνατό.',
          name: 'Όνομα',
          email: 'Email',
          subject: 'Θέμα',
          reason: 'Λόγος επικοινωνίας',
          message: 'Μήνυμα',
          namePlaceholder: 'Το όνομά σου',
          emailPlaceholder: 'you@example.com',
          subjectPlaceholder: 'Π.χ. Ερώτηση για προστατευμένη παραγγελία',
          messagePlaceholder: 'Πες μας λίγα περισσότερα για αυτό που χρειάζεσαι...',
          reasons: [
            'Υποστήριξη παραγγελίας',
            'Πρόβλημα πληρωμής',
            'Διαφωνία',
            'Υποστήριξη πωλητή',
            'Επιχειρηματική επικοινωνία',
          ],
          send: 'Αποστολή μηνύματος',
          faq: 'Μετάβαση στο FAQ',
          supportEmail: 'Email υποστήριξης',
          responseTime: 'Συνήθης χρόνος απάντησης',
          responseTimeText: 'Συνήθως εντός 12 ωρών για γενικά αιτήματα',
          supportCategories: 'Κατηγορίες υποστήριξης',
          categories: ['Αγορές', 'Πωλήσεις', 'Escrow', 'Disputes', 'Payouts', 'Ασφάλεια λογαριασμού'],
          officeTitle: 'Γραφεία Cardora',
          officeText:
            'Η ομάδα υποστήριξης και λειτουργίας της Cardora βρίσκεται στην Αθήνα και από εδώ διαχειρίζεται θέματα marketplace και συνεργασιών.',
          location: 'Αθήνα, Ελλάδα',
        }

  const handleSubmit = (event) => {
    event.preventDefault()
    setSubmitted(true)
  }

  return (
    <div className="container pb-16">
      <PageSeo pageKey="contact" fallbackTitle={copy.title} fallbackDescription={copy.description} />
      <SectionHeader
        eyebrow={copy.eyebrow}
        title={seo?.h1 || copy.title}
        description={copy.description}
      />
      <div className="grid gap-8 xl:grid-cols-[1fr,0.9fr]">
        <CardSurface>
          {submitted ? (
            <div className="space-y-4 py-8">
              <Badge tone="success">{copy.successBadge}</Badge>
              <h2 className="font-display text-4xl text-white">{copy.successTitle}</h2>
              <p className="text-mist">{copy.successText}</p>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="grid gap-4 md:grid-cols-2">
              <div className="md:col-span-1">
                <label className="mb-2 block text-sm text-mist">{copy.name}</label>
                <Input placeholder={copy.namePlaceholder} />
              </div>
              <div className="md:col-span-1">
                <label className="mb-2 block text-sm text-mist">{copy.email}</label>
                <Input type="email" placeholder={copy.emailPlaceholder} />
              </div>
              <div className="md:col-span-1">
                <label className="mb-2 block text-sm text-mist">{copy.subject}</label>
                <Input placeholder={copy.subjectPlaceholder} />
              </div>
              <div className="md:col-span-1">
                <label className="mb-2 block text-sm text-mist">{copy.reason}</label>
                <Select defaultValue={copy.reasons[0]}>
                  {copy.reasons.map((reason) => (
                    <option key={reason}>{reason}</option>
                  ))}
                </Select>
              </div>
              <div className="md:col-span-2">
                <label className="mb-2 block text-sm text-mist">{copy.message}</label>
                <Textarea placeholder={copy.messagePlaceholder} />
              </div>
              <div className="md:col-span-2 flex flex-wrap gap-3">
                <Button type="submit">{copy.send}</Button>
                <Button variant="secondary" type="button">
                  {copy.faq}
                </Button>
              </div>
            </form>
          )}
        </CardSurface>

        <div className="space-y-6">
          <CardSurface>
            <div className="space-y-4">
              <div className="flex items-center gap-3">
                <Mail className="h-5 w-5 text-gold-100" />
                <div>
                  <p className="font-semibold text-white">{copy.supportEmail}</p>
                  <p className="text-sm text-mist">support@cardora.gr</p>
                </div>
              </div>
              <div className="flex items-center gap-3">
                <MessageCircleMore className="h-5 w-5 text-gold-100" />
                <div>
                  <p className="font-semibold text-white">{copy.responseTime}</p>
                  <p className="text-sm text-mist">{copy.responseTimeText}</p>
                </div>
              </div>
            </div>
          </CardSurface>

          <CardSurface>
            <p className="text-xs uppercase tracking-[0.35em] text-gold-100">{copy.supportCategories}</p>
            <div className="mt-4 flex flex-wrap gap-2">
              {copy.categories.map((item) => (
                <Badge key={item} tone="muted">
                  {item}
                </Badge>
              ))}
            </div>
          </CardSurface>

          <CardSurface className="min-h-[260px]">
            <div className="flex h-full flex-col justify-between rounded-[24px] border border-dashed border-white/15 bg-white/5 p-5">
              <div>
                <MapPinned className="h-6 w-6 text-gold-100" />
                <h3 className="mt-4 font-display text-3xl text-white">{copy.officeTitle}</h3>
                <p className="mt-2 text-sm leading-7 text-mist">{copy.officeText}</p>
              </div>
              <p className="text-sm text-white/60">{copy.location}</p>
            </div>
          </CardSurface>
        </div>
      </div>
    </div>
  )
}

export default ContactPage
