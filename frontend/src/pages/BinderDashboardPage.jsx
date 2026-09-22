import { ArrowRight, Crown, Download, Layers } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import BinderShell from '@/components/binder/BinderShell'
import CompletionBar from '@/components/binder/CompletionBar'
import StatTile from '@/components/binder/StatTile'
import { fetchBinderPortfolio, fetchMyBinderCollection } from '@/services/binderService'
import { cardoraService } from '@/services/cardoraService'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { localizePath } from '@/utils/helpers'

function LaunchLink({ to, title, description, cta }) {
  return (
    <Link
      to={to}
      className="group flex items-center justify-between gap-4 border-b border-[#eee2c4] py-5 first:pt-0 last:border-0 last:pb-0"
    >
      <div>
        <h3 className="font-display text-xl font-semibold text-ink">{title}</h3>
        <p className="mt-1 max-w-md text-sm leading-6 text-mist">{description}</p>
      </div>
      <span className="flex shrink-0 items-center gap-1.5 text-sm font-semibold text-[#9d6a17]">
        {cta}
        <ArrowRight className="h-4 w-4 transition group-hover:translate-x-1" />
      </span>
    </Link>
  )
}

function BinderDashboardPage() {
  const { locale } = useI18n()
  const { isAuthenticated } = useAuth()
  const isEnglish = locale === 'en'
  const localized = (path) => localizePath(path, locale)

  const [loading, setLoading] = useState(isAuthenticated)
  const [portfolio, setPortfolio] = useState(null)
  const [mySets, setMySets] = useState([])

  useEffect(() => {
    document.title = 'Cardora Binder — Dashboard'
  }, [])

  useEffect(() => {
    if (!isAuthenticated) {
      setLoading(false)
      return undefined
    }

    let cancelled = false
    setLoading(true)

    Promise.all([fetchBinderPortfolio(), fetchMyBinderCollection()])
      .then(([portfolioRes, collectionRes]) => {
        if (cancelled) return
        setPortfolio(portfolioRes?.data ?? null)
        setMySets(collectionRes?.data ?? [])
      })
      .catch(() => {
        if (!cancelled) {
          setPortfolio(null)
          setMySets([])
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [isAuthenticated])

  const copy = isEnglish
    ? {
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
        statCompletion: 'Cards owned',
        statCompletionHint: 'Total tracked',
        statCards: 'Top game',
        statCardsHint: 'By value',
        topValueTitle: 'Your most valuable cards',
        topValueEmpty: 'Check off some cards with a price to see your top cards here.',
        setsTitle: 'Your sets',
        setsEmpty: 'No sets registered yet.',
        registerCta: 'Register a set',
        signInTitle: 'Sign in to see your stats',
        signInDesc: 'Your portfolio value, sets and top cards show up here once you sign in and start checking off cards.',
        signInCta: 'Sign in',
        statPl: 'Profit / Loss',
        statPlHint: 'vs. purchase price',
        proToolsTitle: 'Cardora PRO tools',
        exportCta: 'Export my collection',
        exportDesc: 'Download a CSV of everything you own.',
        duplicatesCta: 'Bulk-list my duplicates',
        duplicatesDesc: 'List every card you own 2+ copies of in one go.',
        upsellTitle: 'Unlock advanced analytics',
        upsellDesc: 'Profit/loss, rarity breakdowns, collection export and bulk-listing are Cardora PRO features.',
        upsellCta: 'Upgrade to PRO',
      }
    : {
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
        statCompletion: 'Κάρτες που έχεις',
        statCompletionHint: 'Συνολικά καταγεγραμμένες',
        statCards: 'Κορυφαίο παιχνίδι',
        statCardsHint: 'Με βάση την αξία',
        topValueTitle: 'Οι πιο ακριβές σου κάρτες',
        topValueEmpty: 'Τσέκαρε κάρτες με τιμή για να τις δεις εδώ.',
        setsTitle: 'Τα σετ σου',
        setsEmpty: 'Δεν έχεις καταχωρήσει σετ ακόμα.',
        registerCta: 'Καταχώρησε σετ',
        signInTitle: 'Συνδέσου για να δεις τα στατιστικά σου',
        signInDesc: 'Η αξία της συλλογής σου, τα σετ και οι πιο ακριβές κάρτες σου εμφανίζονται εδώ μόλις συνδεθείς και ξεκινήσεις να τσεκάρεις κάρτες.',
        signInCta: 'Σύνδεση',
        statPl: 'Profit / Loss',
        statPlHint: 'vs. τιμή αγοράς',
        proToolsTitle: 'Εργαλεία Cardora PRO',
        exportCta: 'Export της συλλογής μου',
        exportDesc: 'Κατέβασε CSV με ό,τι κατέχεις.',
        duplicatesCta: 'Bulk-listing των duplicates μου',
        duplicatesDesc: 'Καταχώρησε μαζικά κάθε κάρτα που έχεις σε 2+ αντίτυπα.',
        upsellTitle: 'Ξεκλείδωσε προηγμένα analytics',
        upsellDesc: 'Profit/loss, ανάλυση ανά rarity, export συλλογής και bulk-listing είναι λειτουργίες Cardora PRO.',
        upsellCta: 'Αναβάθμιση σε PRO',
      }

  const topGame = portfolio?.byGame?.[0]
  const isPro = Boolean(portfolio?.pro)

  const handleExport = async () => {
    try {
      const result = await cardoraService.exportBinderCollection()
      const blob = new Blob([result.csv], { type: 'text/csv;charset=utf-8;' })
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = result.filename || 'cardora-binder-export.csv'
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(url)
    } catch {
      // Silently ignore — export is a nice-to-have action from the dashboard.
    }
  }

  return (
    <BinderShell>
      <div className="mb-10 max-w-2xl">
        <h2 className="font-display text-3xl font-semibold leading-tight text-ink sm:text-4xl">{copy.title}</h2>
        <p className="mt-3 text-sm leading-6 text-mist sm:text-[15px]">{copy.description}</p>
      </div>

      <div className="mb-10">
        <LaunchLink
          to={localized('/cardora-binder/games')}
          title={copy.binderTitle}
          description={copy.binderDesc}
          cta={copy.binderCta}
        />
        <LaunchLink
          to={localized('/cardora-scanner')}
          title={copy.scannerTitle}
          description={copy.scannerDesc}
          cta={copy.scannerCta}
        />
      </div>

      {!isAuthenticated ? (
        <div className="rounded-xl border border-dashed border-[#eadab7] p-8 text-center">
          <p className="font-display text-xl font-semibold text-ink">{copy.signInTitle}</p>
          <p className="mx-auto mt-2 max-w-md text-sm text-mist">{copy.signInDesc}</p>
          <Link
            to={localized('/eisodos')}
            className="mt-4 inline-flex items-center gap-1.5 border-b-2 border-[#c79d62] pb-0.5 text-sm font-semibold text-[#6b4718]"
          >
            {copy.signInCta}
          </Link>
        </div>
      ) : loading ? (
        <div className="h-24 animate-pulse rounded-xl border border-[#eee2c4] bg-[#fdf8ee]" />
      ) : (
        <>
          <div
            className={`grid grid-cols-2 divide-x divide-y divide-[#eee2c4] rounded-xl border border-[#eee2c4] sm:divide-y-0 ${
              isPro ? 'sm:grid-cols-5' : 'sm:grid-cols-4'
            }`}
          >
            <StatTile value={`€${(portfolio?.totalValue ?? 0).toFixed(2)}`} label={copy.statValue} hint={copy.statValueHint} />
            <StatTile value={portfolio?.setsRegistered ?? 0} label={copy.statSets} hint={copy.statSetsHint} />
            <StatTile value={portfolio?.totalOwned ?? 0} label={copy.statCompletion} hint={copy.statCompletionHint} />
            <StatTile
              value={topGame ? topGame.name : '—'}
              label={copy.statCards}
              hint={topGame ? `€${topGame.value.toFixed(2)}` : copy.statCardsHint}
            />
            {isPro ? (
              <StatTile
                value={`${portfolio.unrealizedProfitLoss >= 0 ? '+' : ''}€${portfolio.unrealizedProfitLoss.toFixed(2)}`}
                label={copy.statPl}
                hint={copy.statPlHint}
              />
            ) : null}
          </div>

          {isPro ? (
            <div className="mt-6">
              <h3 className="mb-3 font-display text-lg font-semibold text-ink">{copy.proToolsTitle}</h3>
              <div className="grid gap-3 sm:grid-cols-2">
                <button
                  type="button"
                  onClick={handleExport}
                  className="flex items-center gap-3 rounded-xl border border-[#eee2c4] bg-white px-4 py-3.5 text-left transition hover:border-[#c79d62]"
                >
                  <Download className="h-4.5 w-4.5 shrink-0 text-[#9d6a17]" />
                  <div>
                    <p className="text-sm font-semibold text-ink">{copy.exportCta}</p>
                    <p className="text-xs text-slate-400">{copy.exportDesc}</p>
                  </div>
                </button>
                <Link
                  to={localized('/cardora-binder/duplicates')}
                  className="flex items-center gap-3 rounded-xl border border-[#eee2c4] bg-white px-4 py-3.5 transition hover:border-[#c79d62]"
                >
                  <Layers className="h-4.5 w-4.5 shrink-0 text-[#9d6a17]" />
                  <div>
                    <p className="text-sm font-semibold text-ink">{copy.duplicatesCta}</p>
                    <p className="text-xs text-slate-400">{copy.duplicatesDesc}</p>
                  </div>
                </Link>
              </div>
            </div>
          ) : (
            <div className="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#eadab7] bg-[#fff8ec] px-5 py-4">
              <div className="flex items-center gap-3">
                <Crown className="h-5 w-5 shrink-0 text-[#9d6a17]" />
                <div>
                  <p className="text-sm font-semibold text-[#6b4718]">{copy.upsellTitle}</p>
                  <p className="text-xs text-[#9d6a17]/80">{copy.upsellDesc}</p>
                </div>
              </div>
              <Link
                to={localized('/cardora-pro')}
                className="shrink-0 rounded-lg border border-[#c79d62] px-3.5 py-2 text-xs font-semibold text-[#6b4718] hover:bg-white"
              >
                {copy.upsellCta}
              </Link>
            </div>
          )}

          <div className="mt-10 grid gap-10 lg:grid-cols-[1.1fr_1fr]">
            <div>
              <h3 className="mb-4 font-display text-xl font-semibold text-ink">{copy.topValueTitle}</h3>
              {portfolio?.topCards?.length ? (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-4">
                  {portfolio.topCards.map((card) => (
                    <div key={card.id} className="rounded-lg border border-[#eee2c4] bg-white p-2">
                      <div className="flex aspect-[5/7] w-full items-center justify-center overflow-hidden rounded-md bg-[#f3e9d2]">
                        {card.imageUrl ? (
                          <img src={card.imageUrl} alt={card.name} loading="lazy" className="h-full w-full object-contain" />
                        ) : (
                          <span className="px-2 text-center text-[10px] text-slate-400">{card.name}</span>
                        )}
                      </div>
                      <p className="mt-2 truncate text-[11px] text-slate-500">{card.setName}</p>
                      <p className="text-[11px] font-bold text-[#9d6a17]">€{Number(card.price).toFixed(2)}</p>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="rounded-lg border border-dashed border-[#eadab7] p-6 text-center text-sm text-slate-500">
                  {copy.topValueEmpty}
                </p>
              )}
            </div>

            <div>
              <div className="mb-4 flex items-center justify-between">
                <h3 className="font-display text-xl font-semibold text-ink">{copy.setsTitle}</h3>
                <Link to={localized('/cardora-binder/games')} className="text-xs font-semibold text-[#9d6a17] hover:underline">
                  {copy.registerCta}
                </Link>
              </div>
              {mySets.length ? (
                <div>
                  {mySets.map((set) => (
                    <Link
                      key={set.id}
                      to={localized(`/cardora-binder/sets/${set.id}`)}
                      className="block border-b border-[#eee2c4] py-3.5 first:pt-0 last:border-0 last:pb-0"
                    >
                      <div className="mb-2 flex items-center justify-between gap-3">
                        <div className="min-w-0">
                          <p className="truncate text-sm font-semibold text-ink">{set.name}</p>
                          <p className="text-[11px] text-slate-400">{set.game.name}</p>
                        </div>
                        <div className="shrink-0 text-right">
                          <p className="text-xs text-slate-500">
                            {set.ownedCount}/{set.cardCount}
                          </p>
                          <p className="text-[11px] font-bold text-[#9d6a17]">€{set.value.toFixed(2)}</p>
                        </div>
                      </div>
                      <CompletionBar percent={set.percent} size="sm" />
                    </Link>
                  ))}
                </div>
              ) : (
                <p className="rounded-lg border border-dashed border-[#eadab7] p-6 text-center text-sm text-slate-500">
                  {copy.setsEmpty}
                </p>
              )}
            </div>
          </div>
        </>
      )}
    </BinderShell>
  )
}

export default BinderDashboardPage
