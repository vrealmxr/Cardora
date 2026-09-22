import { Check, Search, X } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Input, Select } from '@/components/ui/Input'
import { fetchBinderCards, fetchBinderGames, fetchBinderSets } from '@/services/binderService'
import { cn } from '@/utils/helpers'

function useDebouncedValue(value, delay) {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay)
    return () => clearTimeout(timer)
  }, [value, delay])

  return debounced
}

// Optional step in the listing form: tie a "Cards" listing to a specific
// catalog card in Cardora Binder, so collectors who own/watch that set can
// get pinged the moment this listing goes live. Purely additive — skipping
// it just means the listing doesn't trigger Binder alerts.
function BinderCardMatchField({ cardId, snapshot, onChange, isEnglish }) {
  const [expanded, setExpanded] = useState(!cardId)
  const [games, setGames] = useState([])
  const [gameSlug, setGameSlug] = useState('')
  const [sets, setSets] = useState([])
  const [setId, setSetId] = useState('')
  const [cardQuery, setCardQuery] = useState('')
  const debouncedCardQuery = useDebouncedValue(cardQuery, 300)
  const [cardResults, setCardResults] = useState([])
  const [loadingCards, setLoadingCards] = useState(false)

  useEffect(() => {
    fetchBinderGames()
      .then((res) => {
        const flat = Object.values(res?.data ?? {}).flat()
        setGames(flat)
        if (flat.length && !gameSlug) setGameSlug(flat[0].slug)
      })
      .catch(() => {})
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    if (!gameSlug) return undefined
    let cancelled = false
    fetchBinderSets(gameSlug, { page: 1, per_page: 100 })
      .then((res) => {
        if (!cancelled) {
          const list = res?.data ?? []
          setSets(list)
          setSetId(list[0]?.id ?? '')
        }
      })
      .catch(() => {
        if (!cancelled) setSets([])
      })
    return () => {
      cancelled = true
    }
  }, [gameSlug])

  useEffect(() => {
    if (!setId) {
      setCardResults([])
      return undefined
    }
    let cancelled = false
    setLoadingCards(true)
    fetchBinderCards(setId, { search: debouncedCardQuery, type: 'cards', page: 1 })
      .then((res) => {
        if (!cancelled) setCardResults((res?.data ?? []).slice(0, 12))
      })
      .catch(() => {
        if (!cancelled) setCardResults([])
      })
      .finally(() => {
        if (!cancelled) setLoadingCards(false)
      })
    return () => {
      cancelled = true
    }
  }, [setId, debouncedCardQuery])

  const copy = isEnglish
    ? {
        label: 'Match to a Cardora Binder card',
        hint: 'Optional — collectors tracking this set get an alert with a link to this listing the moment it goes live.',
        game: 'Game',
        set: 'Set',
        searchPlaceholder: 'Search the card by name…',
        noResults: 'No cards match your search.',
        matched: 'Matched to',
        change: 'Change',
        clear: 'Remove match',
        loading: 'Loading…',
      }
    : {
        label: 'Ταύτισε με κάρτα του Cardora Binder',
        hint: 'Προαιρετικό — οι collectors που παρακολουθούν αυτό το σετ παίρνουν ειδοποίηση με σύνδεσμο σε αυτή την αγγελία μόλις βγει live.',
        game: 'Παιχνίδι',
        set: 'Σετ',
        searchPlaceholder: 'Αναζήτησε την κάρτα με το όνομά της…',
        noResults: 'Καμία κάρτα δεν ταιριάζει με την αναζήτησή σου.',
        matched: 'Ταυτίστηκε με',
        change: 'Αλλαγή',
        clear: 'Αφαίρεση ταύτισης',
        loading: 'Φόρτωση…',
      }

  const selectCard = (card) => {
    const set = sets.find((item) => item.id === Number(setId))
    const game = games.find((item) => item.slug === gameSlug)
    onChange(card.id, {
      id: card.id,
      name: card.name,
      number: card.number,
      imageUrl: card.imageUrl,
      set: { name: set?.name ?? '' },
      game: { name: game?.name ?? '' },
    })
    setExpanded(false)
  }

  const clearMatch = () => {
    onChange(null, null)
    setExpanded(true)
  }

  if (!expanded && (cardId || snapshot)) {
    return (
      <div className="md:col-span-2">
        <label className="mb-2 block text-sm text-mist">{copy.label}</label>
        <div className="flex items-center justify-between gap-3 rounded-[20px] border border-[#c79d62] bg-[#fff8ec] px-4 py-3">
          <div className="flex min-w-0 items-center gap-3">
            {snapshot?.imageUrl ? (
              <img src={snapshot.imageUrl} alt={snapshot.name} className="h-12 w-9 shrink-0 rounded-md object-cover" />
            ) : (
              <Check className="h-5 w-5 shrink-0 text-[#9d6a17]" />
            )}
            <div className="min-w-0">
              <p className="text-[11px] font-semibold uppercase tracking-wide text-[#9d6a17]">{copy.matched}</p>
              <p className="truncate text-sm font-semibold text-ink">
                {snapshot?.name}
                {snapshot?.number ? ` · ${snapshot.number}` : ''}
              </p>
              <p className="truncate text-xs text-slate-500">
                {snapshot?.set?.name} {snapshot?.game?.name ? `· ${snapshot.game.name}` : ''}
              </p>
            </div>
          </div>
          <div className="flex shrink-0 items-center gap-2">
            <button
              type="button"
              onClick={() => setExpanded(true)}
              className="rounded-md border border-[#d8b06a] px-2.5 py-1.5 text-xs font-semibold text-[#6b4718] hover:bg-white"
            >
              {copy.change}
            </button>
            <button
              type="button"
              onClick={clearMatch}
              className="text-slate-400 transition hover:text-rose-500"
              aria-label={copy.clear}
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="md:col-span-2">
      <label className="mb-2 block text-sm text-mist">{copy.label}</label>
      <p className="mb-3 text-xs leading-5 text-slate-400">{copy.hint}</p>

      <div className="grid gap-3 sm:grid-cols-2">
        <Select value={gameSlug} onChange={(event) => setGameSlug(event.target.value)}>
          {games.map((game) => (
            <option key={game.slug} value={game.slug}>
              {game.name}
            </option>
          ))}
        </Select>
        <Select value={setId} onChange={(event) => setSetId(event.target.value)}>
          {sets.map((set) => (
            <option key={set.id} value={set.id}>
              {set.name}
            </option>
          ))}
        </Select>
      </div>

      <div className="relative mt-3">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <Input
          type="text"
          value={cardQuery}
          onChange={(event) => setCardQuery(event.target.value)}
          placeholder={copy.searchPlaceholder}
          className="pl-9"
        />
      </div>

      <div className="mt-3 max-h-64 divide-y divide-[#eee2c4] overflow-y-auto rounded-[16px] border border-[#eee2c4]">
        {loadingCards ? (
          <div className="p-4 text-center text-sm text-slate-400">{copy.loading}</div>
        ) : cardResults.length ? (
          cardResults.map((card) => (
            <button
              key={card.id}
              type="button"
              onClick={() => selectCard(card)}
              className={cn(
                'flex w-full items-center gap-3 px-4 py-2.5 text-left transition hover:bg-[#fff8ec]',
                cardId === card.id && 'bg-[#fff8ec]',
              )}
            >
              {card.imageUrl ? (
                <img src={card.imageUrl} alt={card.name} className="h-10 w-7 shrink-0 rounded object-cover" />
              ) : null}
              <span className="min-w-0 flex-1 truncate text-sm text-ink">{card.name}</span>
              {card.number ? <span className="shrink-0 text-xs text-slate-400">{card.number}</span> : null}
            </button>
          ))
        ) : (
          <div className="p-4 text-center text-sm text-slate-400">{copy.noResults}</div>
        )}
      </div>
    </div>
  )
}

export default BinderCardMatchField
