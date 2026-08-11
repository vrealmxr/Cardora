import { Check, Plus, Search, Wallet } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { Navigate, useParams } from 'react-router-dom'
import BinderShell from '@/components/binder/BinderShell'
import CardTile from '@/components/binder/CardTile'
import CompletionBar from '@/components/binder/CompletionBar'
import PlaceholderSetArt from '@/components/binder/PlaceholderSetArt'
import Button from '@/components/ui/Button'
import { Input, Select } from '@/components/ui/Input'
import { RARITY_OPTIONS, getCardsForSet, getSetById, isCardOwned, MY_BINDER } from '@/data/binderMockData'
import { useI18n } from '@/hooks/useI18n'
import { cn, localizePath } from '@/utils/helpers'

function BinderSetDetailPage() {
  const { setId } = useParams()
  const { locale } = useI18n()
  const isEnglish = locale === 'en'
  const localized = (path) => localizePath(path, locale)

  const set = getSetById(setId)
  const cards = useMemo(() => getCardsForSet(setId), [setId])

  const [registered, setRegistered] = useState(() => MY_BINDER.registeredSetIds.includes(setId))
  const [owned, setOwned] = useState(() => new Set(cards.filter((c) => isCardOwned(c.id)).map((c) => c.id)))
  const [prices, setPrices] = useState(() => Object.fromEntries(cards.map((c) => [c.id, c.estimatedPrice])))
  const [query, setQuery] = useState('')
  const [rarityFilter, setRarityFilter] = useState('all')
  const [statusFilter, setStatusFilter] = useState('all')

  useEffect(() => {
    if (set) document.title = `Cardora Binder — ${set.name}`
  }, [set])

  if (!set) {
    return <Navigate to={localized('/cardora-binder/sets')} replace />
  }

  const copy = isEnglish
    ? {
        register: 'Register this set',
        registered: 'In your binder',
        searchPlaceholder: 'Search a card…',
        allRarities: 'All rarities',
        all: 'All',
        ownedOnly: 'Owned',
        missingOnly: 'Missing',
        totalValue: 'Total value',
        completion: 'Completion',
        results: 'cards',
      }
    : {
        register: 'Καταχώρισε αυτό το σετ',
        registered: 'Στο binder σου',
        searchPlaceholder: 'Αναζήτησε μια κάρτα…',
        allRarities: 'Όλες οι σπανιότητες',
        all: 'Όλες',
        ownedOnly: 'Έχεις',
        missingOnly: 'Λείπουν',
        totalValue: 'Συνολική αξία',
        completion: 'Ολοκλήρωση',
        results: 'κάρτες',
      }

  const filteredCards = cards.filter((card) => {
    if (query.trim() && !card.name.toLowerCase().includes(query.trim().toLowerCase())) return false
    if (rarityFilter !== 'all' && card.rarity !== rarityFilter) return false
    if (statusFilter === 'owned' && !owned.has(card.id)) return false
    if (statusFilter === 'missing' && owned.has(card.id)) return false
    return true
  })

  const ownedCount = cards.filter((c) => owned.has(c.id)).length
  const completionPercent = cards.length ? Math.round((ownedCount / cards.length) * 100) : 0
  const totalValue = cards.reduce((sum, c) => (owned.has(c.id) ? sum + (prices[c.id] ?? 0) : sum), 0)

  const toggleOwned = (cardId) => {
    setOwned((prev) => {
      const next = new Set(prev)
      if (next.has(cardId)) next.delete(cardId)
      else next.add(cardId)
      return next
    })
  }

  const changePrice = (cardId, value) => {
    setPrices((prev) => ({ ...prev, [cardId]: value }))
  }

  return (
    <BinderShell>
      <div className="mb-6 grid gap-5 lg:grid-cols-[220px_1fr]">
        <PlaceholderSetArt category={set.theme} name={set.name} className="aspect-[4/3] w-full lg:aspect-square" />

        <div className="flex flex-col justify-between">
          <div>
            <p className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#9d6a17]">
              {set.brandLabel} · {set.year}
            </p>
            <h2 className="mt-1 font-display text-3xl font-semibold text-ink sm:text-4xl">{set.name}</h2>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-mist">{set.description[locale] ?? set.description.el}</p>
          </div>

          <div className="mt-5 flex flex-wrap items-center gap-4">
            <div className="min-w-[180px] flex-1">
              <CompletionBar percent={completionPercent} label={`${copy.completion} · ${ownedCount}/${cards.length}`} />
            </div>
            <div className="flex items-center gap-2 rounded-xl border border-[#eadab7] bg-[#fff8ec] px-4 py-2.5">
              <Wallet className="h-4 w-4 text-[#9d6a17]" />
              <div>
                <p className="text-[10px] font-semibold uppercase tracking-wide text-[#9d6a17]/80">{copy.totalValue}</p>
                <p className="font-display text-lg font-bold text-[#6b4718]">€{totalValue.toFixed(2)}</p>
              </div>
            </div>
            {!registered ? (
              <Button onClick={() => setRegistered(true)}>
                <Plus className="h-4 w-4" />
                {copy.register}
              </Button>
            ) : (
              <span className="inline-flex items-center gap-1.5 rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-3.5 py-2.5 text-xs font-semibold text-emerald-700">
                <Check className="h-3.5 w-3.5" />
                {copy.registered}
              </span>
            )}
          </div>
        </div>
      </div>

      <div className="mb-5 flex flex-col gap-3 rounded-[20px] border border-[#eadab7] bg-white p-3 shadow-glass sm:flex-row sm:items-center">
        <div className="relative flex-1">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <Input
            type="text"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder={copy.searchPlaceholder}
            className="pl-9"
          />
        </div>

        <Select value={rarityFilter} onChange={(event) => setRarityFilter(event.target.value)} className="sm:w-56">
          <option value="all">{copy.allRarities}</option>
          {RARITY_OPTIONS.map((r) => (
            <option key={r.value} value={r.value}>
              {r.label[locale] ?? r.label.el}
            </option>
          ))}
        </Select>

        <div className="flex gap-1.5">
          {[
            { value: 'all', label: copy.all },
            { value: 'owned', label: copy.ownedOnly },
            { value: 'missing', label: copy.missingOnly },
          ].map((opt) => (
            <button
              key={opt.value}
              type="button"
              onClick={() => setStatusFilter(opt.value)}
              className={cn(
                'rounded-full border px-3 py-1.5 text-xs font-semibold transition',
                statusFilter === opt.value
                  ? 'border-[#d8b06a] bg-[linear-gradient(145deg,rgba(255,247,229,0.98)_0%,rgba(243,229,193,0.96)_100%)] text-[#6b4718]'
                  : 'border-[#eadab7] bg-white text-slate-700 hover:border-[#d8b06a] hover:bg-[#fff8ec] hover:text-[#6b4718]',
              )}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </div>

      <p className="mb-4 text-xs font-semibold text-slate-400">
        {filteredCards.length} {copy.results}
      </p>

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
        {filteredCards.map((card) => (
          <CardTile
            key={card.id}
            card={card}
            owned={owned.has(card.id)}
            onToggleOwned={toggleOwned}
            price={prices[card.id]}
            onPriceChange={changePrice}
            isEnglish={isEnglish}
          />
        ))}
      </div>
    </BinderShell>
  )
}

export default BinderSetDetailPage
