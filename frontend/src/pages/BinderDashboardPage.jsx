import { Album, ArrowRight, Layers, ScanLine, Sparkles, Wallet } from 'lucide-react'
import { Link } from 'react-router-dom'
import BinderShell from '@/components/binder/BinderShell'
import CompletionBar from '@/components/binder/CompletionBar'
import PlaceholderCardArt from '@/components/binder/PlaceholderCardArt'
import StatTile from '@/components/binder/StatTile'
import { useEffect } from 'react'
import { useI18n } from '@/hooks/useI18n'
import { getMyRegisteredSets, getPortfolioTotals, getTopValueOwnedCards } from '@/data/binderMockData'
import { localizePath } from '@/utils/helpers'

function LaunchCard({ to, icon: Icon, eyebrow, title, description, cta }) {
  return (
    <Link
      to={to}
      className="group relative flex flex-col justify-between overflow-hidden rounded-[24px] border border-white/10 bg-white/[0.04] p-6 transition duration-200 hover:-translate-y-1 hover:border-[#f3d385]/35 hover:bg-white/[0.06] hover:shadow-[0_20px_44px_rgba(0,0,0,0.35)]"
    >
      <span className="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-[#f3d385]/10 blur-3xl transition group-hover:bg-[#f3d385]/16" />
      <div className="relative">
        <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl border border-[#f3d385]/30 bg-[linear-gradient(135deg,#1c1204_0%,#3a2708_45%,#6b4a15_100%)] shadow-[0_10px_24px_rgba(120,80,20,0.35)]">
          <Icon className="h-5.5 w-5.5 text-[#f3d385]" />
        </div>
        <p className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#f3d385]/80">{eyebrow}</p>
        <h3 className="mt-1.5 font-display text-2xl font-semibold text-white">{title}</h3>
        <p className="mt-2 max-w-sm text-sm leading-6 text-white/55">{description}</p>
      </div>
      <div className="relative mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-[#f3d385]">
        {cta}
        <ArrowRight className="h-4 w-4 transition group-hover:translate-x-1" />
      </div>
    </Link>
  )
}

function BinderDashboardPage() {
  const { locale } = useI18n()
  const isEnglish = locale === 'en'
  const localized = (path) => localizePath(path, locale)

  const totals = getPortfolioTotals()
  const topCards = getTopValueOwnedCards(4)
  const mySets = getMyRegisteredSets()

  useEffect(() => {
    document.title = 'Cardora Binder — Dashboard'
  }, [])

  const copy = isEnglish
    ? {
        eyebrow: 'Your collection, organized',
        title: 'Track every set. Never buy a duplicate again.',
        description:
          'Register the sets you collect, check off the cards you already own, and keep a private value on each one — all in one place.',
        binderTitle: 'Cardora Binder',
        binderDesc: 'Register sets, tick off the cards you own, and see exactly what you still need.',
        binderCta: 'Open my binder',
        scannerTitle: 'Cardora Scanner',
        scannerDesc: 'Search a card by name or upload a photo and get an estimated market price.',
        scannerCta: 'Open scanner',
        statValue: 'Portfolio value',
        statValueHint: 'Sum of your private prices',
        statSets: 'Sets registered',
        statSetsHint: 'Across all franchises',
        statCompletion: 'Avg. completion',
        statCompletionHint: 'Across registered sets',
        statCards: 'Cards owned',
        statCardsHint: 'Checked off so far',
        topValueTitle: 'Your most valuable cards',
        topValueEmpty: 'Register a set and check off some cards to see your top cards here.',
        setsTitle: 'Your sets',
        setsEmpty: 'No sets registered yet.',
        registerCta: 'Register a set',
      }
    : {
        eyebrow: 'Η συλλογή σου, οργανωμένη',
        title: 'Παρακολούθησε κάθε σετ. Μην ξαναπάρεις ποτέ διπλή κάρτα.',
        description:
          'Καταχώρησε τα σετ που συλλέγεις, τσέκαρε τις κάρτες που ήδη έχεις και κράτα ιδιωτική τιμή σε κάθε μία — όλα σε ένα μέρος.',
        binderTitle: 'Cardora Binder',
        binderDesc: 'Καταχώρησε σετ, τσέκαρε τις κάρτες που έχεις και δες ακριβώς ποιες σου λείπουν.',
        binderCta: 'Άνοιγμα του binder μου',
        scannerTitle: 'Cardora Scanner',
        scannerDesc: 'Αναζήτησε μια κάρτα με το όνομά της ή ανέβασε φωτογραφία για εκτίμηση τιμής.',
        scannerCta: 'Άνοιγμα scanner',
        statValue: 'Αξία συλλογής',
        statValueHint: 'Άθροισμα ιδιωτικών τιμών σου',
        statSets: 'Καταχωρημένα σετ',
        statSetsHint: 'Σε όλα τα franchise',
        statCompletion: 'Μέση ολοκλήρωση',
        statCompletionHint: 'Στα καταχωρημένα σετ',
        statCards: 'Κάρτες που έχεις',
        statCardsHint: 'Τσεκαρισμένες μέχρι τώρα',
        topValueTitle: 'Οι πιο ακριβές σου κάρτες',
        topValueEmpty: 'Καταχώρησε ένα σετ και τσέκαρε κάρτες για να τις δεις εδώ.',
        setsTitle: 'Τα σετ σου',
        setsEmpty: 'Δεν έχεις καταχωρήσει σετ ακόμα.',
        registerCta: 'Καταχώρησε σετ',
      }

  return (
    <BinderShell>
      <div className="mb-8 max-w-2xl">
        <p className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#f3d385]/80">{copy.eyebrow}</p>
        <h2 className="mt-2 font-display text-3xl font-semibold leading-tight text-white sm:text-4xl">{copy.title}</h2>
        <p className="mt-3 text-sm leading-6 text-white/55 sm:text-[15px]">{copy.description}</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <LaunchCard
          to={localized('/cardora-binder/library')}
          icon={Album}
          eyebrow="01"
          title={copy.binderTitle}
          description={copy.binderDesc}
          cta={copy.binderCta}
        />
        <LaunchCard
          to={localized('/cardora-scanner')}
          icon={ScanLine}
          eyebrow="02"
          title={copy.scannerTitle}
          description={copy.scannerDesc}
          cta={copy.scannerCta}
        />
      </div>

      <div className="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <StatTile icon={Wallet} label={copy.statValue} value={`€${totals.totalValue.toFixed(2)}`} hint={copy.statValueHint} />
        <StatTile icon={Layers} label={copy.statSets} value={totals.setsRegistered} hint={copy.statSetsHint} />
        <StatTile
          icon={Sparkles}
          label={copy.statCompletion}
          value={`${totals.avgCompletion}%`}
          hint={copy.statCompletionHint}
          accent="emerald"
        />
        <StatTile icon={Album} label={copy.statCards} value={`${totals.totalOwned}/${totals.totalCards}`} hint={copy.statCardsHint} />
      </div>

      <div className="mt-10 grid gap-8 lg:grid-cols-[1.1fr_1fr]">
        <div>
          <h3 className="mb-4 font-display text-xl font-semibold text-white">{copy.topValueTitle}</h3>
          {topCards.length ? (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-4">
              {topCards.map((card) => (
                <div
                  key={card.id}
                  className="rounded-[16px] border border-white/10 bg-white/[0.04] p-2"
                >
                  <PlaceholderCardArt category={card.category} name={card.name} number={card.number} rarity={card.rarity} compact />
                  <p className="mt-2 truncate text-[11px] text-white/45">{card.setName}</p>
                  <p className="text-[11px] font-bold text-[#f3d385]">€{card.estimatedPrice.toFixed(2)}</p>
                </div>
              ))}
            </div>
          ) : (
            <p className="rounded-[16px] border border-dashed border-white/15 p-6 text-center text-sm text-white/45">
              {copy.topValueEmpty}
            </p>
          )}
        </div>

        <div>
          <div className="mb-4 flex items-center justify-between">
            <h3 className="font-display text-xl font-semibold text-white">{copy.setsTitle}</h3>
            <Link
              to={localized('/cardora-binder/sets')}
              className="text-xs font-semibold text-[#f3d385] hover:underline"
            >
              {copy.registerCta}
            </Link>
          </div>
          {mySets.length ? (
            <div className="space-y-3">
              {mySets.map(({ set, completion }) => (
                <Link
                  key={set.id}
                  to={localized(`/cardora-binder/sets/${set.id}`)}
                  className="block rounded-[16px] border border-white/10 bg-white/[0.04] p-4 transition hover:border-[#f3d385]/25 hover:bg-white/[0.06]"
                >
                  <div className="mb-2 flex items-center justify-between">
                    <p className="text-sm font-semibold text-white">{set.name}</p>
                    <p className="text-xs text-white/45">
                      {completion.owned}/{completion.total}
                    </p>
                  </div>
                  <CompletionBar percent={completion.percent} size="sm" />
                </Link>
              ))}
            </div>
          ) : (
            <p className="rounded-[16px] border border-dashed border-white/15 p-6 text-center text-sm text-white/45">
              {copy.setsEmpty}
            </p>
          )}
        </div>
      </div>
    </BinderShell>
  )
}

export default BinderDashboardPage
