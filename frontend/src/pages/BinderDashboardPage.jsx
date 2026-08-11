import { Album, ArrowRight, Layers, ScanLine, Sparkles, Wallet } from 'lucide-react'
import { useEffect } from 'react'
import { Link } from 'react-router-dom'
import CardSurface from '@/components/ui/CardSurface'
import PlaceholderCardArt from '@/components/binder/PlaceholderCardArt'
import BinderShell from '@/components/binder/BinderShell'
import CompletionBar from '@/components/binder/CompletionBar'
import StatTile from '@/components/binder/StatTile'
import { getMyRegisteredSets, getPortfolioTotals, getTopValueOwnedCards } from '@/data/binderMockData'
import { useI18n } from '@/hooks/useI18n'
import { localizePath } from '@/utils/helpers'

function LaunchCard({ to, icon: Icon, eyebrow, title, description, cta }) {
  return (
    <Link to={to} className="group block">
      <CardSurface className="flex h-full flex-col justify-between">
        <div>
          <div className="mb-4 flex h-11 w-11 items-center justify-center rounded-2xl border border-[#d7b57b]/70 bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_48%,#c79d62_100%)]">
            <Icon className="h-5 w-5 text-[#5a3a13]" />
          </div>
          <p className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#9d6a17]">{eyebrow}</p>
          <h3 className="mt-1.5 font-display text-2xl font-semibold text-ink">{title}</h3>
          <p className="mt-2 max-w-sm text-sm leading-6 text-mist">{description}</p>
        </div>
        <div className="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-[#9d6a17]">
          {cta}
          <ArrowRight className="h-4 w-4 transition group-hover:translate-x-1" />
        </div>
      </CardSurface>
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
        <p className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#9d6a17]">{copy.eyebrow}</p>
        <h2 className="mt-2 font-display text-3xl font-semibold leading-tight text-ink sm:text-4xl">{copy.title}</h2>
        <p className="mt-3 text-sm leading-6 text-mist sm:text-[15px]">{copy.description}</p>
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
        <StatTile icon={Sparkles} label={copy.statCompletion} value={`${totals.avgCompletion}%`} hint={copy.statCompletionHint} />
        <StatTile icon={Album} label={copy.statCards} value={`${totals.totalOwned}/${totals.totalCards}`} hint={copy.statCardsHint} />
      </div>

      <div className="mt-10 grid gap-8 lg:grid-cols-[1.1fr_1fr]">
        <div>
          <h3 className="mb-4 font-display text-xl font-semibold text-ink">{copy.topValueTitle}</h3>
          {topCards.length ? (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-4">
              {topCards.map((card) => (
                <div key={card.id} className="rounded-[16px] border border-[#ead9b1] bg-white p-2 shadow-glass">
                  <PlaceholderCardArt category={card.category} name={card.name} number={card.number} rarity={card.rarity} compact />
                  <p className="mt-2 truncate text-[11px] text-slate-500">{card.setName}</p>
                  <p className="text-[11px] font-bold text-[#9d6a17]">€{card.estimatedPrice.toFixed(2)}</p>
                </div>
              ))}
            </div>
          ) : (
            <p className="rounded-[16px] border border-dashed border-[#eadab7] p-6 text-center text-sm text-slate-500">
              {copy.topValueEmpty}
            </p>
          )}
        </div>

        <div>
          <div className="mb-4 flex items-center justify-between">
            <h3 className="font-display text-xl font-semibold text-ink">{copy.setsTitle}</h3>
            <Link to={localized('/cardora-binder/sets')} className="text-xs font-semibold text-[#9d6a17] hover:underline">
              {copy.registerCta}
            </Link>
          </div>
          {mySets.length ? (
            <div className="space-y-3">
              {mySets.map(({ set, completion }) => (
                <Link
                  key={set.id}
                  to={localized(`/cardora-binder/sets/${set.id}`)}
                  className="block rounded-[16px] border border-[#ead9b1] bg-white p-4 shadow-glass transition hover:border-[#d8b06a]"
                >
                  <div className="mb-2 flex items-center justify-between">
                    <p className="text-sm font-semibold text-ink">{set.name}</p>
                    <p className="text-xs text-slate-500">
                      {completion.owned}/{completion.total}
                    </p>
                  </div>
                  <CompletionBar percent={completion.percent} size="sm" />
                </Link>
              ))}
            </div>
          ) : (
            <p className="rounded-[16px] border border-dashed border-[#eadab7] p-6 text-center text-sm text-slate-500">
              {copy.setsEmpty}
            </p>
          )}
        </div>
      </div>
    </BinderShell>
  )
}

export default BinderDashboardPage
