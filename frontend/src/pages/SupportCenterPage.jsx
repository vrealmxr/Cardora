import { Search } from 'lucide-react'
import { useState } from 'react'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import StripeTransparencyCard from '@/components/trust/StripeTransparencyCard'
import { Input, Select, Textarea } from '@/components/ui/Input'
import SectionHeader from '@/components/ui/SectionHeader'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'

const SUPPORT_CATEGORIES = {
  en: [
    { value: 'dispute', label: 'Dispute' },
    { value: 'payment_issue', label: 'Payment issue' },
    { value: 'order_issue', label: 'Order issue' },
    { value: 'seller_issue', label: 'Seller issue' },
    { value: 'account_safety', label: 'Account safety' },
    { value: 'general', label: 'General question' },
  ],
  el: [
    { value: 'dispute', label: 'Διαφωνία' },
    { value: 'payment_issue', label: 'Πρόβλημα πληρωμής' },
    { value: 'order_issue', label: 'Θέμα παραγγελίας' },
    { value: 'seller_issue', label: 'Θέμα πωλητή' },
    { value: 'account_safety', label: 'Ασφάλεια λογαριασμού' },
    { value: 'general', label: 'Γενική ερώτηση' },
  ],
}

function SupportCenterPage() {
  const { locale } = useI18n()
  const { createSupportTicket, supportArticles, supportTickets } = useMarketplace()
  const [query, setQuery] = useState('')
  const [createdTicket, setCreatedTicket] = useState(null)
  const [submitError, setSubmitError] = useState('')

  const copy =
    locale === 'en'
      ? {
          eyebrow: 'Support Center',
          title: 'Help with orders, Stripe payments and account issues',
          description:
            'Search help articles, open a support request and keep track of any active cases in one place.',
          searchPlaceholder: 'Search help articles, Stripe, shipping or disputes...',
          articles: 'Help articles',
          activeTickets: 'Open support requests',
          emptyTickets: 'You do not have any open support requests right now.',
          ticketTitle: 'Open a support request',
          ticketSuccess: (id, subject) => `Support request ${id} was created for "${subject}".`,
          category: 'Category',
          subject: 'Subject',
          details: 'Details',
          subjectPlaceholder: 'For example: Order arrived in a different condition',
          detailsPlaceholder:
            'Share the order details, shipping updates, photos and the outcome you expect.',
          submit: 'Submit request',
          statusLabel: 'Status',
        }
      : {
          eyebrow: 'Κέντρο Υποστήριξης',
          title: 'Βοήθεια για παραγγελίες, πληρωμές Stripe και θέματα λογαριασμού',
          description:
            'Αναζήτησε άρθρα βοήθειας, άνοιξε νέο αίτημα και παρακολούθησε όλα τα ενεργά cases σου σε ένα σημείο.',
          searchPlaceholder: 'Αναζήτησε άρθρα βοήθειας, Stripe, αποστολές ή disputes...',
          articles: 'Άρθρα βοήθειας',
          activeTickets: 'Ανοιχτά αιτήματα υποστήριξης',
          emptyTickets: 'Δεν έχεις ανοιχτά αιτήματα υποστήριξης αυτή τη στιγμή.',
          ticketTitle: 'Άνοιγμα νέου αιτήματος',
          ticketSuccess: (id, subject) => `Δημιουργήθηκε αίτημα ${id} με θέμα "${subject}".`,
          category: 'Κατηγορία',
          subject: 'Θέμα',
          details: 'Περιγραφή',
          subjectPlaceholder: 'Π.χ. Η παραγγελία έφτασε σε διαφορετική κατάσταση',
          detailsPlaceholder:
            'Ανέφερε στοιχεία παραγγελίας, ενημερώσεις αποστολής, φωτογραφίες και το αποτέλεσμα που περιμένεις.',
          submit: 'Υποβολή αιτήματος',
          statusLabel: 'Κατάσταση',
        }

  const categoryOptions = SUPPORT_CATEGORIES[locale] ?? SUPPORT_CATEGORIES.el
  const categoryLabelByValue = Object.fromEntries(
    categoryOptions.map((item) => [item.value, item.label]),
  )

  const filteredArticles = supportArticles.filter((article) =>
    [article.title, article.excerpt, article.category].join(' ').toLowerCase().includes(query.toLowerCase()),
  )

  const submitTicket = async (event) => {
    event.preventDefault()
    const form = new FormData(event.currentTarget)

    try {
      const ticket = await createSupportTicket({
        category: form.get('category'),
        subject: form.get('subject'),
        description: form.get('description'),
      })
      setCreatedTicket(ticket)
      setSubmitError('')
      event.currentTarget.reset()
    } catch (error) {
      setSubmitError(error.message)
    }
  }

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />

      <div className="mb-8">
        <StripeTransparencyCard />
      </div>

      <CardSurface>
        <div className="relative">
          <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-gold-100" />
          <Input
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            className="pl-10"
            placeholder={copy.searchPlaceholder}
          />
        </div>
      </CardSurface>

      <div className="mt-10 grid gap-8 xl:grid-cols-[1fr,0.95fr]">
        <div className="space-y-6">
          <CardSurface>
            <h3 className="font-display text-3xl text-white">{copy.articles}</h3>
            <div className="mt-5 grid gap-4 md:grid-cols-2">
              {filteredArticles.map((article) => (
                <div key={article.id} className="rounded-[24px] border border-white/8 bg-white/5 p-4">
                  <Badge tone="muted">{article.category}</Badge>
                  <p className="mt-3 text-lg font-semibold text-white">{article.title}</p>
                  <p className="mt-2 text-sm leading-7 text-mist">{article.excerpt}</p>
                </div>
              ))}
            </div>
          </CardSurface>

          <CardSurface>
            <h3 className="font-display text-3xl text-white">{copy.activeTickets}</h3>
            <div className="mt-5 space-y-3">
              {supportTickets.length ? (
                supportTickets.map((ticket) => (
                  <div key={ticket.id} className="rounded-[24px] border border-white/8 bg-white/5 p-4">
                    <div className="flex items-center justify-between gap-3">
                      <p className="font-semibold text-white">{ticket.subject}</p>
                      <Badge tone="gold">{ticket.status}</Badge>
                    </div>
                    <p className="mt-2 text-sm text-mist">
                      {ticket.id} · {categoryLabelByValue[ticket.category] ?? ticket.category}
                    </p>
                    <p className="mt-1 text-xs text-white/45">
                      {copy.statusLabel}: {ticket.status}
                    </p>
                  </div>
                ))
              ) : (
                <div className="rounded-[24px] border border-dashed border-white/10 bg-white/4 px-4 py-5 text-sm leading-7 text-mist">
                  {copy.emptyTickets}
                </div>
              )}
            </div>
          </CardSurface>
        </div>

        <CardSurface>
          <h3 className="font-display text-3xl text-white">{copy.ticketTitle}</h3>
          {createdTicket ? (
            <div className="mt-5 rounded-[24px] border border-emerald-400/20 bg-emerald-400/10 p-4 text-sm text-emerald-800">
              {copy.ticketSuccess(createdTicket.id, createdTicket.subject)}
            </div>
          ) : null}
          {submitError ? (
            <div className="mt-5 rounded-[24px] border border-amber-400/20 bg-amber-500/10 p-4 text-sm text-amber-800">
              {submitError}
            </div>
          ) : null}
          <form onSubmit={submitTicket} className="mt-5 space-y-4">
            <div>
              <label className="mb-2 block text-sm text-mist">{copy.category}</label>
              <Select name="category" defaultValue={categoryOptions[0]?.value}>
                {categoryOptions.map((item) => (
                  <option key={item.value} value={item.value}>
                    {item.label}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <label className="mb-2 block text-sm text-mist">{copy.subject}</label>
              <Input name="subject" placeholder={copy.subjectPlaceholder} />
            </div>
            <div>
              <label className="mb-2 block text-sm text-mist">{copy.details}</label>
              <Textarea name="description" placeholder={copy.detailsPlaceholder} />
            </div>
            <Button type="submit">{copy.submit}</Button>
          </form>
        </CardSurface>
      </div>
    </div>
  )
}

export default SupportCenterPage
