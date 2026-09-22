import { ChevronLeft, ChevronRight, Search, Wallet } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import BinderShell from '@/components/binder/BinderShell'
import RealCardTile from '@/components/binder/RealCardTile'
import PriceEntryModal from '@/components/binder/PriceEntryModal'
import CompletionBar from '@/components/binder/CompletionBar'
import { Input, Select } from '@/components/ui/Input'
import { fetchBinderCards, toggleBinderCardOwned, updateBinderCardPrice } from '@/services/binderService'
import { cardoraService } from '@/services/cardoraService'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { cn, localizePath } from '@/utils/helpers'

function useDebouncedValue(value, delay) {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(timer)
  }, [value, delay])

  return debounced
}

function BinderSetDetailPage() {
  const { setId } = useParams()
  const { locale } = useI18n()
  const { isAuthenticated, currentUser } = useAuth()
  const isPro = Boolean(currentUser?.isPro)
  const navigate = useNavigate()
  const isEnglish = locale === 'en'
  const localized = (path) => localizePath(path, locale)

  const [query, setQuery] = useState('')
  const debouncedQuery = useDebouncedValue(query, 300)
  const [rarityFilter, setRarityFilter] = useState('')
  const [productType, setProductType] = useState('cards')
  const [page, setPage] = useState(1)
  const [state, setState] = useState({
    loading: true,
    error: null,
    set: null,
    rarities: [],
    cards: [],
    meta: null,
  })
  const [ownedIds, setOwnedIds] = useState(() => new Set())
  const [prices, setPrices] = useState(() => new Map())
  const [quantities, setQuantities] = useState(() => new Map())
  const [pendingCard, setPendingCard] = useState(null)

  useEffect(() => {
    setPage(1)
  }, [setId, debouncedQuery, rarityFilter, productType])

  useEffect(() => {
    let cancelled = false
    setState((prev) => ({ ...prev, loading: true, error: null }))

    fetchBinderCards(setId, { search: debouncedQuery, rarity: rarityFilter, type: productType, page })
      .then((payload) => {
        if (cancelled) return
        const cards = payload?.data ?? []
        setState({
          loading: false,
          error: null,
          set: payload?.set ?? null,
          rarities: payload?.rarities ?? [],
          cards,
          meta: payload?.meta ?? null,
        })
        setOwnedIds((prev) => {
          const next = new Set(prev)
          cards.forEach((card) => {
            if (card.ownedQuantity > 0) next.add(card.id)
          })
          return next
        })
        setPrices((prev) => {
          const next = new Map(prev)
          cards.forEach((card) => {
            if (card.ownedPrice != null) next.set(card.id, card.ownedPrice)
          })
          return next
        })
        setQuantities((prev) => {
          const next = new Map(prev)
          cards.forEach((card) => {
            if (card.ownedQuantity > 0) next.set(card.id, card.ownedQuantity)
          })
          return next
        })
      })
      .catch((err) => {
        if (!cancelled) setState((prev) => ({ ...prev, loading: false, error: err.message }))
      })

    return () => {
      cancelled = true
    }
  }, [setId, debouncedQuery, rarityFilter, productType, page])

  useEffect(() => {
    if (state.set) document.title = `Cardora Binder — ${state.set.name}`
  }, [state.set])

  const copy = isEnglish
    ? {
        searchPlaceholder: 'Search a card…',
        allRarities: 'All rarities',
        cardsOnly: 'Cards',
        sealedOnly: 'Sealed products',
        results: 'cards',
        owned: 'owned',
        loadError: 'Could not load cards. Try again in a moment.',
        empty: 'No cards match your filters.',
        signInToTrack: 'Sign in to start checking off cards.',
        setValue: 'Value on this page',
      }
    : {
        searchPlaceholder: 'Αναζήτησε μια κάρτα…',
        allRarities: 'Όλες οι σπανιότητες',
        cardsOnly: 'Κάρτες',
        sealedOnly: 'Σφραγισμένα προϊόντα',
        results: 'κάρτες',
        owned: 'έχεις',
        loadError: 'Δεν φορτώθηκαν οι κάρτες. Δοκίμασε ξανά σε λίγο.',
        empty: 'Καμία κάρτα δεν ταιριάζει με τα φίλτρα σου.',
        signInToTrack: 'Συνδέσου για να ξεκινήσεις να τσεκάρεις κάρτες.',
        setValue: 'Αξία σε αυτή τη σελίδα',
      }

  const commitToggleOn = async (cardId, price) => {
    setOwnedIds((prev) => new Set(prev).add(cardId))
    setPrices((prev) => {
      const next = new Map(prev)
      if (price != null) next.set(cardId, price)
      else next.delete(cardId)
      return next
    })

    try {
      await toggleBinderCardOwned(cardId, price)
    } catch {
      setOwnedIds((prev) => {
        const next = new Set(prev)
        next.delete(cardId)
        return next
      })
      setPrices((prev) => {
        const next = new Map(prev)
        next.delete(cardId)
        return next
      })
    }
  }

  const handleToggle = async (cardId) => {
    if (!isAuthenticated) {
      navigate(localized('/eisodos'))
      return
    }

    if (ownedIds.has(cardId)) {
      // Un-checking needs no price prompt — just remove it.
      setOwnedIds((prev) => {
        const next = new Set(prev)
        next.delete(cardId)
        return next
      })
      setPrices((prev) => {
        const next = new Map(prev)
        next.delete(cardId)
        return next
      })

      try {
        await toggleBinderCardOwned(cardId)
      } catch {
        setOwnedIds((prev) => new Set(prev).add(cardId))
      }
      return
    }

    const card = state.cards.find((c) => c.id === cardId)
    setPendingCard(card ?? { id: cardId, name: '' })
  }

  const handlePriceChange = async (cardId, price) => {
    const previous = prices.get(cardId) ?? null
    setPrices((prev) => {
      const next = new Map(prev)
      if (price != null) next.set(cardId, price)
      else next.delete(cardId)
      return next
    })

    try {
      await updateBinderCardPrice(cardId, price ?? 0)
    } catch {
      setPrices((prev) => {
        const next = new Map(prev)
        if (previous != null) next.set(cardId, previous)
        else next.delete(cardId)
        return next
      })
    }
  }

  const handleQuantityChange = async (cardId, quantity) => {
    const previous = quantities.get(cardId) ?? 1
    setQuantities((prev) => new Map(prev).set(cardId, quantity))
    try {
      await cardoraService.updateBinderCardQuantity(cardId, quantity)
    } catch {
      setQuantities((prev) => new Map(prev).set(cardId, previous))
    }
  }

  const ownedInSet = state.cards.filter((card) => ownedIds.has(card.id)).length
  const valueOnPage = state.cards.reduce((sum, card) => (ownedIds.has(card.id) ? sum + (prices.get(card.id) ?? 0) : sum), 0)

  return (
    <BinderShell>
      <PriceEntryModal
        open={Boolean(pendingCard)}
        card={pendingCard}
        isEnglish={isEnglish}
        onCancel={() => setPendingCard(null)}
        onConfirm={(price) => {
          const cardId = pendingCard?.id
          setPendingCard(null)
          if (cardId != null) commitToggleOn(cardId, price)
        }}
      />

      <div className="mb-6">
        <Link
          to={state.set ? localized(`/cardora-binder/games/${state.set.game.slug}`) : localized('/cardora-binder/games')}
          className="mb-2 inline-block text-[11px] font-semibold text-slate-400 hover:text-[#6b4718]"
        >
          ← {state.set?.game?.name ?? '…'}
        </Link>
        <h2 className="font-display text-3xl font-semibold text-ink sm:text-4xl">{state.set?.name ?? '…'}</h2>

        {state.set ? (
          <div className="mt-4 flex flex-wrap items-center gap-x-6 gap-y-3">
            <div className="min-w-[220px] max-w-sm flex-1">
              <CompletionBar
                percent={state.cards.length ? Math.round((ownedInSet / state.cards.length) * 100) : 0}
                label={
                  isAuthenticated
                    ? `${ownedInSet} / ${state.cards.length} ${copy.owned} (${isEnglish ? 'this page' : 'σε αυτή τη σελίδα'})`
                    : copy.signInToTrack
                }
              />
            </div>
            {isAuthenticated && ownedInSet > 0 ? (
              <div className="flex items-baseline gap-2">
                <Wallet className="h-3.5 w-3.5 text-slate-400" />
                <span className="text-[11px] uppercase tracking-wide text-slate-400">{copy.setValue}</span>
                <span className="font-display text-lg font-semibold text-[#6b4718]">€{valueOnPage.toFixed(2)}</span>
              </div>
            ) : null}
          </div>
        ) : null}
      </div>

      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
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
          <option value="">{copy.allRarities}</option>
          {state.rarities.map((r) => (
            <option key={r} value={r}>
              {r}
            </option>
          ))}
        </Select>

        <div className="flex gap-1.5">
          {[
            { value: 'cards', label: copy.cardsOnly },
            { value: 'sealed', label: copy.sealedOnly },
          ].map((opt) => (
            <button
              key={opt.value}
              type="button"
              onClick={() => setProductType(opt.value)}
              className={cn(
                'rounded-md border px-3 py-1.5 text-xs font-semibold transition',
                productType === opt.value
                  ? 'border-[#c79d62] text-[#6b4718]'
                  : 'border-[#eee2c4] bg-white text-slate-600 hover:border-[#c79d62] hover:text-[#6b4718]',
              )}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </div>

      {state.error ? (
        <div className="rounded-xl border border-dashed border-[#eadab7] p-12 text-center">
          <p className="mx-auto max-w-md text-sm text-mist">{copy.loadError}</p>
        </div>
      ) : (
        <>
          {state.meta ? (
            <p className="mb-4 text-xs font-semibold text-slate-400">
              {state.meta.total} {copy.results}
            </p>
          ) : null}

          {state.loading ? (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
              {Array.from({ length: 12 }).map((_, i) => (
                <div key={i} className="aspect-[5/7] animate-pulse rounded-lg border border-[#eee2c4] bg-[#fdf8ee]" />
              ))}
            </div>
          ) : state.cards.length ? (
            <>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                {state.cards.map((card) => (
                  <RealCardTile
                    key={card.id}
                    card={card}
                    owned={ownedIds.has(card.id)}
                    price={prices.get(card.id) ?? null}
                    quantity={quantities.get(card.id) ?? 1}
                    isPro={isPro}
                    onToggleOwned={handleToggle}
                    onPriceChange={handlePriceChange}
                    onQuantityChange={handleQuantityChange}
                    isEnglish={isEnglish}
                  />
                ))}
              </div>

              {state.meta && state.meta.lastPage > 1 ? (
                <div className="mt-6 flex items-center justify-center gap-3">
                  <button
                    type="button"
                    disabled={page <= 1}
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                    className="flex h-9 w-9 items-center justify-center rounded-full border border-[#eadab7] bg-white text-slate-600 transition hover:border-[#d8b06a] hover:text-[#6b4718] disabled:opacity-30"
                  >
                    <ChevronLeft className="h-4 w-4" />
                  </button>
                  <span className="text-xs font-semibold text-slate-500">
                    {state.meta.currentPage} / {state.meta.lastPage}
                  </span>
                  <button
                    type="button"
                    disabled={page >= state.meta.lastPage}
                    onClick={() => setPage((p) => Math.min(state.meta.lastPage, p + 1))}
                    className="flex h-9 w-9 items-center justify-center rounded-full border border-[#eadab7] bg-white text-slate-600 transition hover:border-[#d8b06a] hover:text-[#6b4718] disabled:opacity-30"
                  >
                    <ChevronRight className="h-4 w-4" />
                  </button>
                </div>
              ) : null}
            </>
          ) : (
            <div className="rounded-xl border border-dashed border-[#eadab7] p-12 text-center">
              <p className="mx-auto max-w-md text-sm text-mist">{copy.empty}</p>
            </div>
          )}
        </>
      )}
    </BinderShell>
  )
}

export default BinderSetDetailPage
