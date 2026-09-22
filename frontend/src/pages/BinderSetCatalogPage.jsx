import { ChevronLeft, ChevronRight, Search } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import BinderShell from '@/components/binder/BinderShell'
import RealSetCard from '@/components/binder/RealSetCard'
import GAME_LOGOS, { MONOCHROME_LOGO_SLUGS } from '@/assets/binder-logos'
import { Input } from '@/components/ui/Input'
import { fetchBinderSets } from '@/services/binderService'
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

function BinderSetCatalogPage() {
  const { gameSlug } = useParams()
  const { locale } = useI18n()
  const isEnglish = locale === 'en'
  const localized = (path) => localizePath(path, locale)

  const [query, setQuery] = useState('')
  const debouncedQuery = useDebouncedValue(query, 300)
  const [page, setPage] = useState(1)
  const [state, setState] = useState({ loading: true, error: null, gameName: '', sets: [], meta: null })

  useEffect(() => {
    setPage(1)
  }, [gameSlug, debouncedQuery])

  useEffect(() => {
    let cancelled = false
    setState((prev) => ({ ...prev, loading: true, error: null }))

    fetchBinderSets(gameSlug, { search: debouncedQuery, page })
      .then((payload) => {
        if (cancelled) return
        setState({
          loading: false,
          error: null,
          gameName: payload?.game?.name ?? '',
          sets: payload?.data ?? [],
          meta: payload?.meta ?? null,
        })
      })
      .catch((err) => {
        if (!cancelled) setState((prev) => ({ ...prev, loading: false, error: err.message }))
      })

    return () => {
      cancelled = true
    }
  }, [gameSlug, debouncedQuery, page])

  useEffect(() => {
    document.title = state.gameName ? `Cardora Binder — ${state.gameName}` : 'Cardora Binder'
  }, [state.gameName])

  const copy = isEnglish
    ? {
        searchPlaceholder: 'Search a set…',
        results: 'sets found',
        empty: 'No sets match your search.',
        cards: 'cards',
        loadError: 'Could not load sets. Try again in a moment.',
        allGames: 'All games',
      }
    : {
        searchPlaceholder: 'Αναζήτησε ένα σετ…',
        results: 'σετ βρέθηκαν',
        empty: 'Κανένα σετ δεν ταιριάζει με την αναζήτησή σου.',
        cards: 'κάρτες',
        loadError: 'Δεν φορτώθηκαν τα σετ. Δοκίμασε ξανά σε λίγο.',
        allGames: 'Όλα τα παιχνίδια',
      }

  return (
    <BinderShell>
      <div className="mb-6 max-w-2xl">
        <Link
          to={localized('/cardora-binder/games')}
          className="mb-2 inline-block text-[11px] font-semibold text-slate-400 hover:text-[#6b4718]"
        >
          ← {copy.allGames}
        </Link>
        {GAME_LOGOS[gameSlug] ? (
          <img
            src={GAME_LOGOS[gameSlug]}
            alt={state.gameName}
            className={cn(
              'max-h-10 max-w-[220px] object-contain object-left',
              MONOCHROME_LOGO_SLUGS.has(gameSlug) && 'brightness-0',
            )}
          />
        ) : (
          <h2 className="font-display text-3xl font-semibold text-ink sm:text-4xl">{state.gameName || '…'}</h2>
        )}
      </div>

      <div className="relative mb-6 max-w-sm">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <Input
          type="text"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder={copy.searchPlaceholder}
          className="pl-9"
        />
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
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
              {Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="aspect-[4/3] animate-pulse rounded-xl border border-[#eee2c4] bg-[#fdf8ee]" />
              ))}
            </div>
          ) : state.sets.length ? (
            <>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                {state.sets.map((set) => (
                  <RealSetCard
                    key={set.id}
                    set={set}
                    to={localized(`/cardora-binder/sets/${set.id}`)}
                    locale={locale}
                    cardsLabel={copy.cards}
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

export default BinderSetCatalogPage
