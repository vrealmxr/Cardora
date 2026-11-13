import OperatorDetails from '@/components/legal/OperatorDetails'
import { Mail, MessageCircleMore } from 'lucide-react'
import { Link } from 'react-router-dom'
import PageSeo from '@/components/PageSeo'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useSeo } from '@/context/SeoContext'
import { useI18n } from '@/hooks/useI18n'
import { localizePath } from '@/utils/helpers'

function ContactPage() {
  const { locale } = useI18n()
  const seo = useSeo('contact')
  const copy = locale === 'en' ? {
    eyebrow: 'Contact', title: 'Talk to the Cardora team',
    description: 'Need help with an order, your account or selling a collectible? Choose how you would like to contact us.',
    emailTitle: 'Email us', emailText: 'Write to support@cardora.gr. For an order question, include your order number and a short description of the issue.',
    emailAction: 'Open email', emailNote: 'This opens your email app. Send your message from there.',
    supportTitle: 'Keep your support requests in one place',
    supportText: 'Sign in to open a support request and see it in your Support Center.', supportAction: 'Go to Support Center',
    faqTitle: 'Looking for a quick answer?', faqText: 'Our frequently asked questions explain buying, selling and what to do when an order needs attention.', faqAction: 'Read the FAQ',
    companyTitle: 'Company information', companyText: 'The company details and marketplace policies are available in our Terms of Use.', companyAction: 'Terms of Use',
  } : {
    eyebrow: 'Επικοινωνία', title: 'Μίλησε με την ομάδα της Cardora',
    description: 'Χρειάζεσαι βοήθεια με μια παραγγελία, τον λογαριασμό σου ή την πώληση ενός συλλεκτικού; Διάλεξε πώς θέλεις να επικοινωνήσεις μαζί μας.',
    emailTitle: 'Στείλε μας email', emailText: 'Γράψε μας στο support@cardora.gr. Αν το θέμα αφορά παραγγελία, πρόσθεσε τον αριθμό της και μια σύντομη περιγραφή του προβλήματος.',
    emailAction: 'Άνοιγμα email', emailNote: 'Θα ανοίξει η εφαρμογή email σου. Στείλε το μήνυμά σου από εκεί.',
    supportTitle: 'Τα αιτήματα υποστήριξής σου σε ένα σημείο',
    supportText: 'Συνδέσου για να ανοίξεις ένα αίτημα και να το βλέπεις στο Κέντρο Υποστήριξης.', supportAction: 'Κέντρο Υποστήριξης',
    faqTitle: 'Ψάχνεις μια γρήγορη απάντηση;', faqText: 'Στις συχνές ερωτήσεις θα βρεις πληροφορίες για αγορές, πωλήσεις και τι κάνεις αν προκύψει θέμα με μια παραγγελία.', faqAction: 'Συχνές ερωτήσεις',
    companyTitle: 'Στοιχεία εταιρείας', companyText: 'Τα στοιχεία του φορέα της Cardora και οι πολιτικές της πλατφόρμας βρίσκονται στους Όρους Χρήσης.', companyAction: 'Όροι Χρήσης',
  }
  return (
    <div className="container pb-16">
      <PageSeo pageKey="contact" fallbackTitle={copy.title} fallbackDescription={copy.description} />
      <SectionHeader as="h1" eyebrow={copy.eyebrow} title={seo?.h1 || copy.title} description={copy.description} />
      <div className="grid gap-6 lg:grid-cols-2">
        <CardSurface>
          <Mail className="h-6 w-6 text-gold-500" />
          <h2 className="mt-4 font-display text-3xl text-ink">{copy.emailTitle}</h2>
          <p className="mt-4 text-sm leading-7 text-mist">{copy.emailText}</p>
          <a href="mailto:support@cardora.gr" className="mt-4 inline-block font-semibold text-ink underline underline-offset-4">support@cardora.gr</a>
          <div className="mt-6"><Button as="a" href="mailto:support@cardora.gr">{copy.emailAction}</Button></div>
          <p className="mt-3 text-sm text-mist">{copy.emailNote}</p>
        </CardSurface>
        <CardSurface>
          <MessageCircleMore className="h-6 w-6 text-gold-500" />
          <h2 className="mt-4 font-display text-3xl text-ink">{copy.supportTitle}</h2>
          <p className="mt-4 text-sm leading-7 text-mist">{copy.supportText}</p>
          <Button as={Link} to={localizePath('/kentro-ypostiriksis', locale)} className="mt-6">{copy.supportAction}</Button>
        </CardSurface>
        <CardSurface>
          <h2 className="font-display text-3xl text-ink">{copy.faqTitle}</h2>
          <p className="mt-4 text-sm leading-7 text-mist">{copy.faqText}</p>
          <Button as={Link} to={localizePath('/faq', locale)} variant="secondary" className="mt-6">{copy.faqAction}</Button>
        </CardSurface>
        <CardSurface>
          <h2 className="font-display text-3xl text-ink">{copy.companyTitle}</h2>
          <p className="mt-4 text-sm leading-7 text-mist">{copy.companyText}</p>
          <OperatorDetails />
          <Button as={Link} to={localizePath('/oroi-xrisis', locale)} variant="secondary" className="mt-6">{copy.companyAction}</Button>
        </CardSurface>
      </div>
    </div>
  )
}
export default ContactPage
