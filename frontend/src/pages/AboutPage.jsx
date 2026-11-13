import OperatorDetails from '@/components/legal/OperatorDetails'
import { marketplaceTrust } from '@/data/marketplaceTrust'
import { localizePath } from '@/utils/helpers'
import { ShieldCheck, Sparkles, WalletCards } from 'lucide-react'
import { Link } from 'react-router-dom'
import PageSeo from '@/components/PageSeo'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useSeo } from '@/context/SeoContext'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { useFormatters } from '@/hooks/useFormatters'

const COPY_EN = {
  "badge": "About Cardora",
  "title": "Built by collectors, for collectors.",
  "intro": "Cardora brings collectors together to buy and sell cards, figures, comics, books and other collectibles. Compare listings, communicate with sellers and follow your orders in one place.",
  "ctaRegister": "Create account",
  "ctaSell": "List a collectible",
  "whyEyebrow": "Why Cardora",
  "whyTitle": "Why the platform exists",
  "whyDescription": "Because buying and selling valuable collectibles should not depend on guesswork, rushed messages or blind trust.",
  "cards": [
    {
      "title": "What Cardora is",
      "text": "A marketplace for cards, figures, comics, books and other collectibles with photographs, descriptions and seller profiles."
    },
    {
      "title": "Before buying",
      "text": "Check the edition, condition and total cost. Keep messages on Cardora and report any problem through your order within its review deadline."
    },
    {
      "title": "Before selling",
      "text": "Prepare clear photographs and check your payout account setup, charges and shipping options."
    }
  ],
  "protectedEyebrow": "Your order",
  "protectedTitle": "From listing to delivery",
  "protectedDescription": "The goal is simple: clear expectations before payment, traceable steps after checkout and a proper finish for both sides.",
  "trustTitle": "Trust signals",
  "trustDescription": "Verification, ratings, order tracking and transparent order states help members judge a listing and a seller more easily.",
  "feesTitle": "Fees & commission",
  "feesDescription": "Review the applicable Standard or Pro charges before selling. Shipping is separate from the item value used to calculate commission.",
  "feeCardTitle": "Percentage and fixed charges",
  "feeCardText": "Above €5 of item value, the Standard rate is 6.5% up to €300, 5% up to €2,000 and 4% above €2,000, capped at €400. The selected percentage applies to the whole item value. Up to €5, a €1 fixed fee is added to the buyer’s total instead of being deducted from the seller. Active Pro sellers have different rates; see the full table and examples.",
  "disputeTitle": "How disputes are handled",
  "disputeText": "If a dispute opens, Cardora reviews photos, shipment updates and the conversation history before deciding the next step.",
  "scaleEyebrow": "Marketplace Snapshot",
  "scaleTitle": "A quick look at marketplace activity",
  "scaleDescription": "A current view of listings and marketplace activity."
}
const COPY_EL = {
  "badge": "Σχετικά με την Cardora",
  "title": "Για συλλέκτες, από συλλέκτες.",
  "intro": "Η Cardora φέρνει κοντά συλλέκτες για αγορές και πωλήσεις καρτών, φιγούρων, κόμικς, βιβλίων και άλλων συλλεκτικών. Σύγκρινε αγγελίες, μίλησε με τους πωλητές και παρακολούθησε τις παραγγελίες σου από ένα σημείο.",
  "ctaRegister": "Δημιούργησε λογαριασμό",
  "ctaSell": "Πούλησε συλλεκτικό",
  "whyEyebrow": "Γιατί υπάρχει",
  "whyTitle": "Ο λόγος ύπαρξης της Cardora",
  "whyDescription": "Γιατί οι αγορές πολύτιμων συλλεκτικών δεν πρέπει να βασίζονται στην τύχη, σε υποθέσεις ή σε βιαστικά μηνύματα.",
  "cards": [
    {
      "title": "Τι είναι η Cardora",
      "text": "Μια πλατφόρμα για κάρτες, φιγούρες, κόμικς, βιβλία και άλλα συλλεκτικά, με φωτογραφίες, περιγραφές και προφίλ πωλητών."
    },
    {
      "title": "Πριν αγοράσεις",
      "text": "Έλεγξε έκδοση, κατάσταση και συνολικό κόστος. Κράτησε την επικοινωνία στην Cardora και ανέφερε τυχόν πρόβλημα από την παραγγελία μέσα στην προθεσμία ελέγχου της."
    },
    {
      "title": "Πριν πουλήσεις",
      "text": "Ετοίμασε καθαρές φωτογραφίες και έλεγξε τον λογαριασμό πληρωμών σου, τις χρεώσεις και τις επιλογές αποστολής."
    }
  ],
  "protectedEyebrow": "Η παραγγελία σου",
  "protectedTitle": "Από την αγγελία στην παράδοση",
  "protectedDescription": "Ο στόχος είναι απλός: καθαρή εικόνα πριν την πληρωμή, διαφανή βήματα μετά το checkout και σωστό κλείσιμο και για τις δύο πλευρές.",
  "trustTitle": "Σήματα εμπιστοσύνης",
  "trustDescription": "Επαλήθευση, αξιολογήσεις, tracking και ξεκάθαρες καταστάσεις παραγγελίας βοηθούν κάθε μέλος να κρίνει πιο εύκολα μια αγγελία και έναν πωλητή.",
  "feesTitle": "Χρεώσεις πλατφόρμας",
  "feesDescription": "Δες τις χρεώσεις Standard ή Pro που ισχύουν πριν πουλήσεις. Τα μεταφορικά δεν περιλαμβάνονται στην αξία αντικειμένου πάνω στην οποία υπολογίζεται η προμήθεια.",
  "feeCardTitle": "Ποσοστά και σταθερές χρεώσεις",
  "feeCardText": "Για αξία αντικειμένου πάνω από 5€, η χρέωση Standard είναι 6,5% έως 300€, 5% έως 2.000€ και 4% πάνω από 2.000€, με όριο 400€. Το ποσοστό εφαρμόζεται σε ολόκληρη την αξία του αντικειμένου. Έως 5€, η σταθερή χρέωση 1€ προστίθεται στο σύνολο του αγοραστή και δεν αφαιρείται από τον πωλητή. Οι ενεργοί πωλητές Pro έχουν διαφορετικές χρεώσεις· δες τον πλήρη πίνακα και τα παραδείγματα.",
  "disputeTitle": "Πώς αντιμετωπίζονται οι διαφωνίες",
  "disputeText": "Αν προκύψει διαφωνία, η υπόθεση εξετάζεται χειροκίνητα από την ομάδα της Cardora.",
  "scaleEyebrow": "Στιγμιότυπο αγοράς",
  "scaleTitle": "Μια γρήγορη εικόνα της δραστηριότητας",
  "scaleDescription": "Η τρέχουσα εικόνα των αγγελιών και της δραστηριότητας στην πλατφόρμα."
}
function AboutPage() {
  const { formatCurrency, formatNumber } = useFormatters()
  const { locale } = useI18n()
  const { platformStats } = useMarketplace()
  const trust = marketplaceTrust[locale]
  const howItWorksSteps = trust.steps
  const trustHighlights = trust.highlights

  const copy = locale === 'en' ? COPY_EN : COPY_EL
  const seo = useSeo('about')

  return (
    <div className="container pb-16">
      <PageSeo pageKey="about" fallbackTitle={copy.title} fallbackDescription={copy.intro} />
      <CardSurface className="overflow-hidden p-8 sm:p-10">
        <div className="grid gap-8 xl:grid-cols-[1fr,0.9fr] xl:items-center">
          <div>
            <Badge tone="gold">{copy.badge}</Badge>
            <h1 className="mt-6 font-display text-6xl text-ink">{seo?.h1 || copy.title}</h1>
            {seo?.h2 ? (
              <h2 className="mt-3 font-display text-2xl text-mist">{seo.h2}</h2>
            ) : null}
            <p className="mt-5 max-w-3xl text-lg leading-8 text-mist">{copy.intro}</p>
            <div className="mt-8 flex flex-wrap gap-3">
              <Button as={Link} to={localizePath("/eggrafi", locale)} size="lg">
                {copy.ctaRegister}
              </Button>
              <Button as={Link} to={localizePath("/dimiourgia-aggelias", locale)} variant="secondary" size="lg">
                {copy.ctaSell}
              </Button>
            </div>
          </div>
          <img
            src="/asset.php?f=cardora-banner-20260716-960.jpg"
            alt="Cardora"
            width="960"
            height="535"
            className="banner-image-glow w-full rounded-[28px] border border-[#eadab7] object-cover"
            loading="lazy"
            decoding="async"
          />
        </div>
      </CardSurface>

      <section className="mt-16">
        <SectionHeader
          eyebrow={copy.whyEyebrow}
          title={copy.whyTitle}
          description={copy.whyDescription}
        />
        <div className="grid gap-6 lg:grid-cols-3">
          {copy.cards.map((item) => (
            <CardSurface key={item.title}>
              <h3 className="font-display text-3xl text-ink">{item.title}</h3>
              <p className="mt-3 text-sm leading-7 text-mist">{item.text}</p>
            </CardSurface>
          ))}
        </div>
      </section>

      <section className="mt-16">
        <SectionHeader
          eyebrow={copy.protectedEyebrow}
          title={copy.protectedTitle}
          description={copy.protectedDescription}
        />
        <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
          {howItWorksSteps.map((step, index) => (
            <CardSurface key={step.id}>
              <div className="mb-5 flex h-12 w-12 items-center justify-center rounded-2xl bg-gold-300/12 text-lg font-semibold text-gold-100">
                0{index + 1}
              </div>
              <h3 className="text-xl font-semibold text-ink">{step.title}</h3>
              <p className="mt-3 text-sm leading-7 text-mist">{step.text}</p>
            </CardSurface>
          ))}
        </div>
      </section>

      <section className="mt-16 grid gap-6 xl:grid-cols-[1fr,1fr]">
        <CardSurface>
          <SectionHeader title={copy.trustTitle} description={trust.verification} className="mb-6" />
          <div className="space-y-4">
            {trustHighlights.map((item) => (
              <div key={item} className="flex items-center gap-3 rounded-[22px] border border-gold-200/30 bg-white p-4">
                <ShieldCheck className="h-5 w-5 text-gold-100" />
                <span className="text-ink">{item}</span>
              </div>
            ))}
          </div>
        </CardSurface>
        <CardSurface>
          <SectionHeader title={copy.feesTitle} description={copy.feesDescription} className="mb-6" />
          <div className="space-y-4">
            <div className="rounded-[22px] border border-gold-200/30 bg-white p-4">
              <div className="flex items-center gap-3">
                <WalletCards className="h-5 w-5 text-gold-100" />
                <p className="font-semibold text-ink">{copy.feeCardTitle}</p>
              </div>
              <p className="mt-2 text-sm leading-7 text-mist">{copy.feeCardText}</p>
              <Link to={localizePath('/odigos-polisis', locale)} className="mt-3 inline-block text-sm text-gold-700 underline">{locale === 'en' ? 'Full fee table and selling guide' : 'Πίνακας χρεώσεων και οδηγός πώλησης'}</Link>
            </div>
            <div className="rounded-[22px] border border-gold-200/30 bg-white p-4">
              <div className="flex items-center gap-3">
                <Sparkles className="h-5 w-5 text-gold-100" />
                <p className="font-semibold text-ink">{copy.disputeTitle}</p>
              </div>
              <p className="mt-2 text-sm leading-7 text-mist">{copy.disputeText}</p>
            </div>
          </div>
        </CardSurface>
      </section>

      <section className="mt-16">
        <CardSurface><SectionHeader title={locale === 'en' ? 'Who operates Cardora' : 'Ποιος διαχειρίζεται την Cardora'} />
          <OperatorDetails />
          <Link to={localizePath('/epikoinonia', locale)} className="mt-4 inline-block text-gold-700 underline">{locale === 'en' ? 'Contact the team' : 'Επικοινωνία με την ομάδα'}</Link>
        </CardSurface>
      </section>
      <section className="mt-16">
        <SectionHeader eyebrow={copy.scaleEyebrow} title={copy.scaleTitle} description={copy.scaleDescription} />
        <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
          {platformStats.filter(stat => stat.format !== 'currency').map((stat) => (
            <CardSurface key={stat.label}>
              <p className="text-xs uppercase tracking-[0.35em] text-gold-100">{stat.label}</p>
              <p className="mt-3 text-4xl font-semibold text-ink">
                {stat.format === 'currency'
                  ? formatCurrency(stat.value ?? 0)
                  : typeof stat.value === 'number'
                    ? formatNumber(stat.value)
                    : stat.value}
              </p>
            </CardSurface>
          ))}
        </div>
      </section>
    </div>
  )
}

export default AboutPage
