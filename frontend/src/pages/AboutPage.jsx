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
import { formatCurrency, formatNumber } from '@/utils/formatters'

const COPY_EN = {
  "badge": "About Cardora",
  "title": "Built by collectors, for collectors.",
  "intro": "Cardora exists to make collectible transactions feel safer, clearer and more serious for both sides. It brings payment protection, account trust signals and support into one marketplace designed for people who care about what they buy and sell.",
  "ctaRegister": "Create account",
  "ctaSell": "List a collectible",
  "whyEyebrow": "Why Cardora",
  "whyTitle": "Why the platform exists",
  "whyDescription": "Because buying and selling valuable collectibles should not depend on guesswork, rushed messages or blind trust.",
  "cards": [
    {
      "title": "What Cardora is",
      "text": "A marketplace for trading cards, figures, comics, books and other collectibles where presentation and transaction safety matter equally."
    },
    {
      "title": "Why buyers feel safer",
      "text": "The payment stays protected, there is room to confirm delivery and support can step in if the order does not match the listing."
    },
    {
      "title": "Why sellers feel safer",
      "text": "Orders are paid before shipping and the release process follows a clear path instead of vague back-and-forth messages."
    }
  ],
  "protectedEyebrow": "Protected Transactions",
  "protectedTitle": "How Cardora handles a protected order",
  "protectedDescription": "The goal is simple: clear expectations before payment, traceable steps after checkout and a proper finish for both sides.",
  "trustTitle": "Trust signals",
  "trustDescription": "Verification, ratings, order tracking and transparent order states help members judge a listing and a seller more easily.",
  "feesTitle": "Fees & commission",
  "feesDescription": "Cardora charges a platform fee that supports checkout protection, moderation and support when an order needs review.",
  "feeCardTitle": "Seller fee tiers: €1 (up to €5), 6.5% (€5-€300), 5% (€300-€2,000), 4% (€2,001+)",
  "feeCardText": "Seller fee is calculated on clean item value, capped at €400. Shipping and extra costs are excluded from the fee base.",
  "disputeTitle": "How disputes are handled",
  "disputeText": "If a dispute opens, Cardora reviews photos, shipment updates and the conversation history before deciding the next step.",
  "scaleEyebrow": "Marketplace Snapshot",
  "scaleTitle": "A quick look at marketplace activity",
  "scaleDescription": "These numbers show the kind of visibility and scale Cardora is built to present clearly as the marketplace grows."
}
const COPY_EL = {
  "badge": "\u03a3\u03c7\u03b5\u03c4\u03b9\u03ba\u03ac \u03bc\u03b5 \u03c4\u03b7\u03bd Cardora",
  "title": "\u0393\u03b9\u03b1 \u03c3\u03c5\u03bb\u03bb\u03ad\u03ba\u03c4\u03b5\u03c2, \u03b1\u03c0\u03cc \u03c3\u03c5\u03bb\u03bb\u03ad\u03ba\u03c4\u03b5\u03c2.",
  "intro": "\u0397 Cardora \u03ba\u03ac\u03bd\u03b5\u03b9 \u03c4\u03b9\u03c2 \u03b1\u03b3\u03bf\u03c1\u03b1\u03c0\u03c9\u03bb\u03b7\u03c3\u03af\u03b5\u03c2 \u03c3\u03c5\u03bb\u03bb\u03b5\u03ba\u03c4\u03b9\u03ba\u03ce\u03bd \u03c0\u03b9\u03bf \u03b1\u03c3\u03c6\u03b1\u03bb\u03b5\u03af\u03c2, \u03c0\u03b9\u03bf \u03ba\u03b1\u03b8\u03b1\u03c1\u03ad\u03c2 \u03ba\u03b1\u03b9 \u03c0\u03b9\u03bf \u03b1\u03be\u03b9\u03cc\u03c0\u03b9\u03c3\u03c4\u03b5\u03c2 \u03ba\u03b1\u03b9 \u03b3\u03b9\u03b1 \u03c4\u03b9\u03c2 \u03b4\u03cd\u03bf \u03c0\u03bb\u03b5\u03c5\u03c1\u03ad\u03c2.",
  "ctaRegister": "\u0394\u03b7\u03bc\u03b9\u03bf\u03cd\u03c1\u03b3\u03b7\u03c3\u03b5 \u03bb\u03bf\u03b3\u03b1\u03c1\u03b9\u03b1\u03c3\u03bc\u03cc",
  "ctaSell": "\u03a0\u03bf\u03cd\u03bb\u03b7\u03c3\u03b5 \u03c3\u03c5\u03bb\u03bb\u03b5\u03ba\u03c4\u03b9\u03ba\u03cc",
  "whyEyebrow": "\u0393\u03b9\u03b1\u03c4\u03af \u03c5\u03c0\u03ac\u03c1\u03c7\u03b5\u03b9",
  "whyTitle": "\u039f \u03bb\u03cc\u03b3\u03bf\u03c2 \u03cd\u03c0\u03b1\u03c1\u03be\u03b7\u03c2 \u03c4\u03b7\u03c2 Cardora",
  "whyDescription": "\u0393\u03b9\u03b1\u03c4\u03af \u03bf\u03b9 \u03b1\u03b3\u03bf\u03c1\u03ad\u03c2 \u03c0\u03bf\u03bb\u03cd\u03c4\u03b9\u03bc\u03c9\u03bd \u03c3\u03c5\u03bb\u03bb\u03b5\u03ba\u03c4\u03b9\u03ba\u03ce\u03bd \u03b4\u03b5\u03bd \u03c0\u03c1\u03ad\u03c0\u03b5\u03b9 \u03bd\u03b1 \u03b2\u03b1\u03c3\u03af\u03b6\u03bf\u03bd\u03c4\u03b1\u03b9 \u03c3\u03c4\u03b7\u03bd \u03c4\u03cd\u03c7\u03b7, \u03c3\u03b5 \u03c5\u03c0\u03bf\u03b8\u03ad\u03c3\u03b5\u03b9\u03c2 \u03ae \u03c3\u03b5 \u03b2\u03b9\u03b1\u03c3\u03c4\u03b9\u03ba\u03ac \u03bc\u03b7\u03bd\u03cd\u03bc\u03b1\u03c4\u03b1.",
  "cards": [
    {
      "title": "\u03a4\u03b9 \u03b5\u03af\u03bd\u03b1\u03b9 \u03b7 Cardora",
      "text": "\u0388\u03bd\u03b1 marketplace \u03b3\u03b9\u03b1 \u03ba\u03ac\u03c1\u03c4\u03b5\u03c2, \u03c6\u03b9\u03b3\u03bf\u03cd\u03c1\u03b5\u03c2, \u03ba\u03cc\u03bc\u03b9\u03ba\u03c2, \u03b2\u03b9\u03b2\u03bb\u03af\u03b1 \u03ba\u03b1\u03b9 \u03ac\u03bb\u03bb\u03b1 \u03c3\u03c5\u03bb\u03bb\u03b5\u03ba\u03c4\u03b9\u03ba\u03ac \u03bc\u03b5 \u03ba\u03b1\u03b8\u03b1\u03c1\u03ae \u03c0\u03b1\u03c1\u03bf\u03c5\u03c3\u03af\u03b1\u03c3\u03b7 \u03ba\u03b1\u03b9 \u03c0\u03c1\u03bf\u03c3\u03c4\u03b1\u03c4\u03b5\u03c5\u03bc\u03ad\u03bd\u03b7 \u03c3\u03c5\u03bd\u03b1\u03bb\u03bb\u03b1\u03b3\u03ae."
    },
    {
      "title": "\u0393\u03b9\u03b1\u03c4\u03af \u03bf \u03b1\u03b3\u03bf\u03c1\u03b1\u03c3\u03c4\u03ae\u03c2 \u03bd\u03b9\u03ce\u03b8\u03b5\u03b9 \u03c0\u03b9\u03bf \u03b1\u03c3\u03c6\u03b1\u03bb\u03ae\u03c2",
      "text": "\u0397 \u03c0\u03bb\u03b7\u03c1\u03c9\u03bc\u03ae \u03bc\u03ad\u03bd\u03b5\u03b9 \u03c0\u03c1\u03bf\u03c3\u03c4\u03b1\u03c4\u03b5\u03c5\u03bc\u03ad\u03bd\u03b7 \u03bc\u03ad\u03c7\u03c1\u03b9 \u03bd\u03b1 \u03b5\u03c0\u03b9\u03b2\u03b5\u03b2\u03b1\u03b9\u03c9\u03b8\u03b5\u03af \u03b7 \u03c0\u03b1\u03c1\u03b1\u03bb\u03b1\u03b2\u03ae \u03ba\u03b1\u03b9 \u03b7 \u03c5\u03c0\u03bf\u03c3\u03c4\u03ae\u03c1\u03b9\u03be\u03b7 \u03bc\u03c0\u03bf\u03c1\u03b5\u03af \u03bd\u03b1 \u03c0\u03b1\u03c1\u03ad\u03bc\u03b2\u03b5\u03b9 \u03cc\u03c4\u03b1\u03bd \u03c7\u03c1\u03b5\u03b9\u03ac\u03b6\u03b5\u03c4\u03b1\u03b9."
    },
    {
      "title": "\u0393\u03b9\u03b1\u03c4\u03af \u03bf \u03c0\u03c9\u03bb\u03b7\u03c4\u03ae\u03c2 \u03b4\u03bf\u03c5\u03bb\u03b5\u03cd\u03b5\u03b9 \u03c0\u03b9\u03bf \u03ba\u03b1\u03b8\u03b1\u03c1\u03ac",
      "text": "\u0397 \u03c0\u03b1\u03c1\u03b1\u03b3\u03b3\u03b5\u03bb\u03af\u03b1 \u03ad\u03c7\u03b5\u03b9 \u03c0\u03bb\u03b7\u03c1\u03c9\u03b8\u03b5\u03af \u03c0\u03c1\u03b9\u03bd \u03c4\u03b7\u03bd \u03b1\u03c0\u03bf\u03c3\u03c4\u03bf\u03bb\u03ae \u03ba\u03b1\u03b9 \u03b7 \u03b1\u03c0\u03bf\u03b4\u03ad\u03c3\u03bc\u03b5\u03c5\u03c3\u03b7 \u03b1\u03ba\u03bf\u03bb\u03bf\u03c5\u03b8\u03b5\u03af \u03be\u03b5\u03ba\u03ac\u03b8\u03b1\u03c1\u03b7 \u03b4\u03b9\u03b1\u03b4\u03b9\u03ba\u03b1\u03c3\u03af\u03b1."
    }
  ],
  "protectedEyebrow": "\u03a0\u03c1\u03bf\u03c3\u03c4\u03b1\u03c4\u03b5\u03c5\u03bc\u03ad\u03bd\u03b5\u03c2 \u03c3\u03c5\u03bd\u03b1\u03bb\u03bb\u03b1\u03b3\u03ad\u03c2",
  "protectedTitle": "\u03a0\u03ce\u03c2 \u03bf\u03bb\u03bf\u03ba\u03bb\u03b7\u03c1\u03ce\u03bd\u03b5\u03c4\u03b1\u03b9 \u03bc\u03b9\u03b1 \u03c0\u03c1\u03bf\u03c3\u03c4\u03b1\u03c4\u03b5\u03c5\u03bc\u03ad\u03bd\u03b7 \u03c0\u03b1\u03c1\u03b1\u03b3\u03b3\u03b5\u03bb\u03af\u03b1",
  "protectedDescription": "\u039f \u03c3\u03c4\u03cc\u03c7\u03bf\u03c2 \u03b5\u03af\u03bd\u03b1\u03b9 \u03b1\u03c0\u03bb\u03cc\u03c2: \u03ba\u03b1\u03b8\u03b1\u03c1\u03ae \u03b5\u03b9\u03ba\u03cc\u03bd\u03b1 \u03c0\u03c1\u03b9\u03bd \u03c4\u03b7\u03bd \u03c0\u03bb\u03b7\u03c1\u03c9\u03bc\u03ae, \u03b4\u03b9\u03b1\u03c6\u03b1\u03bd\u03ae \u03b2\u03ae\u03bc\u03b1\u03c4\u03b1 \u03bc\u03b5\u03c4\u03ac \u03c4\u03bf checkout \u03ba\u03b1\u03b9 \u03c3\u03c9\u03c3\u03c4\u03cc \u03ba\u03bb\u03b5\u03af\u03c3\u03b9\u03bc\u03bf \u03ba\u03b1\u03b9 \u03b3\u03b9\u03b1 \u03c4\u03b9\u03c2 \u03b4\u03cd\u03bf \u03c0\u03bb\u03b5\u03c5\u03c1\u03ad\u03c2.",
  "trustTitle": "\u03a3\u03ae\u03bc\u03b1\u03c4\u03b1 \u03b5\u03bc\u03c0\u03b9\u03c3\u03c4\u03bf\u03c3\u03cd\u03bd\u03b7\u03c2",
  "trustDescription": "\u0395\u03c0\u03b1\u03bb\u03ae\u03b8\u03b5\u03c5\u03c3\u03b7, \u03b1\u03be\u03b9\u03bf\u03bb\u03bf\u03b3\u03ae\u03c3\u03b5\u03b9\u03c2, tracking \u03ba\u03b1\u03b9 \u03be\u03b5\u03ba\u03ac\u03b8\u03b1\u03c1\u03b5\u03c2 \u03ba\u03b1\u03c4\u03b1\u03c3\u03c4\u03ac\u03c3\u03b5\u03b9\u03c2 \u03c0\u03b1\u03c1\u03b1\u03b3\u03b3\u03b5\u03bb\u03af\u03b1\u03c2 \u03b2\u03bf\u03b7\u03b8\u03bf\u03cd\u03bd \u03ba\u03ac\u03b8\u03b5 \u03bc\u03ad\u03bb\u03bf\u03c2 \u03bd\u03b1 \u03ba\u03c1\u03af\u03bd\u03b5\u03b9 \u03c0\u03b9\u03bf \u03b5\u03cd\u03ba\u03bf\u03bb\u03b1 \u03bc\u03b9\u03b1 \u03b1\u03b3\u03b3\u03b5\u03bb\u03af\u03b1 \u03ba\u03b1\u03b9 \u03ad\u03bd\u03b1\u03bd \u03c0\u03c9\u03bb\u03b7\u03c4\u03ae.",
  "feesTitle": "\u03a7\u03c1\u03b5\u03ce\u03c3\u03b5\u03b9\u03c2 \u03c0\u03bb\u03b1\u03c4\u03c6\u03cc\u03c1\u03bc\u03b1\u03c2",
  "feesDescription": "\u0397 Cardora \u03ba\u03c1\u03b1\u03c4\u03ac \u03c0\u03c1\u03bf\u03bc\u03ae\u03b8\u03b5\u03b9\u03b1 \u03c0\u03bf\u03c5 \u03c3\u03c4\u03b7\u03c1\u03af\u03b6\u03b5\u03b9 \u03c4\u03b7\u03bd \u03c0\u03c1\u03bf\u03c3\u03c4\u03b1\u03c3\u03af\u03b1 \u03c4\u03b7\u03c2 \u03c3\u03c5\u03bd\u03b1\u03bb\u03bb\u03b1\u03b3\u03ae\u03c2, \u03c4\u03b7 \u03b4\u03b9\u03b1\u03c7\u03b5\u03af\u03c1\u03b9\u03c3\u03b7 \u03c4\u03c9\u03bd \u03b1\u03b3\u03b3\u03b5\u03bb\u03b9\u03ce\u03bd \u03ba\u03b1\u03b9 \u03c4\u03b7\u03bd \u03c5\u03c0\u03bf\u03c3\u03c4\u03ae\u03c1\u03b9\u03be\u03b7 \u03cc\u03c4\u03b1\u03bd \u03bc\u03b9\u03b1 \u03c0\u03b1\u03c1\u03b1\u03b3\u03b3\u03b5\u03bb\u03af\u03b1 \u03c7\u03c1\u03b5\u03b9\u03ac\u03b6\u03b5\u03c4\u03b1\u03b9 \u03ad\u03bb\u03b5\u03b3\u03c7\u03bf.",
  "feeCardTitle": "Κλιμακωτή χρέωση πωλητή: 1€ (έως 5€), 6,5% (5€-300€), 5% (300€-2.000€), 4% (2.001€+)",
  "feeCardText": "Η χρέωση πωλητή υπολογίζεται στην καθαρή αξία αντικειμένου, με ανώτατο πλαφόν 400€. Μεταφορικά και έξτρα κόστη μένουν εκτός βάσης υπολογισμού.",
  "disputeTitle": "\u03a0\u03ce\u03c2 \u03b1\u03bd\u03c4\u03b9\u03bc\u03b5\u03c4\u03c9\u03c0\u03af\u03b6\u03bf\u03bd\u03c4\u03b1\u03b9 \u03bf\u03b9 \u03b4\u03b9\u03b1\u03c6\u03c9\u03bd\u03af\u03b5\u03c2",
  "disputeText": "\u0391\u03bd \u03c0\u03c1\u03bf\u03ba\u03cd\u03c8\u03b5\u03b9 \u03b4\u03b9\u03b1\u03c6\u03c9\u03bd\u03af\u03b1, \u03b7 \u03c5\u03c0\u03cc\u03b8\u03b5\u03c3\u03b7 \u03b5\u03be\u03b5\u03c4\u03ac\u03b6\u03b5\u03c4\u03b1\u03b9 \u03c7\u03b5\u03b9\u03c1\u03bf\u03ba\u03af\u03bd\u03b7\u03c4\u03b1 \u03b1\u03c0\u03cc \u03c4\u03b7\u03bd \u03bf\u03bc\u03ac\u03b4\u03b1 \u03c4\u03b7\u03c2 Cardora.",
  "scaleEyebrow": "\u03a3\u03c4\u03b9\u03b3\u03bc\u03b9\u03cc\u03c4\u03c5\u03c0\u03bf \u03b1\u03b3\u03bf\u03c1\u03ac\u03c2",
  "scaleTitle": "\u039c\u03b9\u03b1 \u03b3\u03c1\u03ae\u03b3\u03bf\u03c1\u03b7 \u03b5\u03b9\u03ba\u03cc\u03bd\u03b1 \u03c4\u03b7\u03c2 \u03b4\u03c1\u03b1\u03c3\u03c4\u03b7\u03c1\u03b9\u03cc\u03c4\u03b7\u03c4\u03b1\u03c2",
  "scaleDescription": "\u039f\u03b9 \u03b1\u03c1\u03b9\u03b8\u03bc\u03bf\u03af \u03b1\u03c5\u03c4\u03bf\u03af \u03b4\u03b5\u03af\u03c7\u03bd\u03bf\u03c5\u03bd \u03c4\u03bf \u03b5\u03af\u03b4\u03bf\u03c2 \u03c0\u03b1\u03c1\u03bf\u03c5\u03c3\u03af\u03b1\u03c2 \u03ba\u03b1\u03b9 \u03ba\u03bb\u03af\u03bc\u03b1\u03ba\u03b1\u03c2 \u03c0\u03bf\u03c5 \u03b7 Cardora \u03b5\u03af\u03bd\u03b1\u03b9 \u03c6\u03c4\u03b9\u03b1\u03b3\u03bc\u03ad\u03bd\u03b7 \u03bd\u03b1 \u03c0\u03b1\u03c1\u03bf\u03c5\u03c3\u03b9\u03ac\u03b6\u03b5\u03b9 \u03ba\u03b1\u03b8\u03b1\u03c1\u03ac \u03cc\u03c3\u03bf \u03bc\u03b5\u03b3\u03b1\u03bb\u03ce\u03bd\u03b5\u03b9."
}
function AboutPage() {
  const { locale } = useI18n()
  const { howItWorksSteps, platformStats, trustHighlights } = useMarketplace()

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
            <p className="mt-5 max-w-3xl text-lg leading-8 text-mist">{copy.intro}</p>
            <div className="mt-8 flex flex-wrap gap-3">
              <Button as={Link} to="/eggrafi" size="lg">
                {copy.ctaRegister}
              </Button>
              <Button as={Link} to="/dimiourgia-aggelias" variant="secondary" size="lg">
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
          <SectionHeader title={copy.trustTitle} description={copy.trustDescription} className="mb-6" />
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
        <SectionHeader eyebrow={copy.scaleEyebrow} title={copy.scaleTitle} description={copy.scaleDescription} />
        <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
          {platformStats.map((stat) => (
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
