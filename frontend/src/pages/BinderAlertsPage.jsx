import { BellRing, Crown, Search, X } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import BinderShell from '@/components/binder/BinderShell'
import GAME_LOGOS, { MONOCHROME_LOGO_SLUGS } from '@/assets/binder-logos'
import { Input, Select } from '@/components/ui/Input'
import Button from '@/components/ui/Button'
import {
  fetchBinderAlertSettings,
  fetchBinderGames,
  fetchBinderSets,
  unwatchBinderSet,
  updateBinderAlertSettings,
  watchBinderSet,
} from '@/services/binderService'
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

function ToggleSwitch({ checked, onChange, disabled }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      disabled={disabled}
      onClick={onChange}
      className={cn(
        'relative inline-flex h-7 w-12 shrink-0 items-center rounded-full border transition',
        checked ? 'border-[#c79d62] bg-[#c79d62]' : 'border-[#dcd0b6] bg-white',
        disabled && 'opacity-50',
      )}
    >
      <span
        className={cn(
          'inline-block h-5 w-5 rounded-full bg-white shadow transition',
          checked ? 'translate-x-6' : 'translate-x-1',
          !checked && 'bg-[#c9bd9f]',
        )}
      />
    </button>
  )
}

function BinderAlertsPage() {
  const { locale } = useI18n()
  const { isAuthenticated } = useAuth()
  const isEnglish = locale === 'en'
  const localized = (path) => localizePath(path, locale)

  const [loading, setLoading] = useState(true)
  const [enabled, setEnabled] = useState(false)
  const [sets, setSets] = useState([])
  const [savingToggle, setSavingToggle] = useState(false)
  const [pendingSetId, setPendingSetId] = useState(null)
  const [error, setError] = useState(null)
  const [isPro, setIsPro] = useState(false)
  const [watchedCount, setWatchedCount] = useState(0)
  const [watchedLimit, setWatchedLimit] = useState(null)
  const [watchError, setWatchError] = useState(null)

  const [games, setGames] = useState([])
  const [gameSlug, setGameSlug] = useState('')
  const [query, setQuery] = useState('')
  const debouncedQuery = useDebouncedValue(query, 300)
  const [searchResults, setSearchResults] = useState([])
  const [searching, setSearching] = useState(false)

  useEffect(() => {
    document.title = 'Cardora Binder — Ειδοποιήσεις'
  }, [])

  const loadAlerts = () => {
    setLoading(true)
    fetchBinderAlertSettings()
      .then((res) => {
        setEnabled(Boolean(res?.data?.enabled))
        setSets(res?.data?.sets ?? [])
        setIsPro(Boolean(res?.data?.isPro))
        setWatchedCount(res?.data?.watchedCount ?? 0)
        setWatchedLimit(res?.data?.watchedLimit ?? null)
        setError(null)
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false))
  }

  useEffect(() => {
    if (!isAuthenticated) {
      setLoading(false)
      return
    }
    loadAlerts()
    fetchBinderGames()
      .then((res) => {
        const grouped = res?.data ?? {}
        const flat = Object.values(grouped).flat()
        setGames(flat)
        if (flat.length) setGameSlug(flat[0].slug)
      })
      .catch(() => {})
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isAuthenticated])

  useEffect(() => {
    if (!gameSlug) return undefined
    let cancelled = false
    setSearching(true)

    fetchBinderSets(gameSlug, { search: debouncedQuery, page: 1 })
      .then((res) => {
        if (!cancelled) setSearchResults((res?.data ?? []).slice(0, 8))
      })
      .catch(() => {
        if (!cancelled) setSearchResults([])
      })
      .finally(() => {
        if (!cancelled) setSearching(false)
      })

    return () => {
      cancelled = true
    }
  }, [gameSlug, debouncedQuery])

  const copy = isEnglish
    ? {
        title: 'Listing alerts',
        description:
          'Get notified the moment someone lists a card from a set you own or watch — with a direct link to the listing.',
        toggleLabel: 'Notify me about new listings from my sets',
        toggleHint: 'Covers every set you own at least one card from, plus any set you explicitly watch below.',
        trackedTitle: 'Sets you get alerts for',
        trackedEmpty: 'You are not tracking any set yet. Own a card in Cardora Binder, or watch a set below, to start getting alerts.',
        owned: 'Owned',
        watched: 'Watching',
        remove: 'Stop watching',
        addTitle: 'Watch a set before you own anything',
        addDescription: 'Pick a game and a set — you will get alerts for it even with zero cards owned yet.',
        gameLabel: 'Game',
        searchPlaceholder: 'Search a set…',
        watchCta: 'Watch',
        alreadyWatching: 'Already tracked',
        noResults: 'No sets match your search.',
        signInTitle: 'Sign in to manage listing alerts',
        signInCta: 'Sign in',
        loadError: 'Could not load your alert settings. Try again in a moment.',
        watchedProgress: 'sets watched',
        upgradeCta: 'Upgrade to PRO for unlimited',
        limitReached: 'Free accounts can watch up to 10 sets — upgrade to Cardora PRO for unlimited missing-card alerts.',
      }
    : {
        title: 'Ειδοποιήσεις καταχωρίσεων',
        description:
          'Μάθε αμέσως όταν κάποιος καταχωρήσει κάρτα από ένα σετ που έχεις ή παρακολουθείς — με απευθείας σύνδεσμο στην αγγελία.',
        toggleLabel: 'Ειδοποίησέ με για νέες καταχωρίσεις από τα σετ μου',
        toggleHint: 'Καλύπτει κάθε σετ από το οποίο έχεις έστω μία κάρτα, καθώς και κάθε σετ που παρακολουθείς παρακάτω.',
        trackedTitle: 'Σετ για τα οποία παίρνεις ειδοποιήσεις',
        trackedEmpty: 'Δεν παρακολουθείς κανένα σετ ακόμα. Απόκτησε μια κάρτα στο Cardora Binder, ή παρακολούθησε ένα σετ παρακάτω, για να ξεκινήσεις.',
        owned: 'Κατέχεις',
        watched: 'Παρακολούθηση',
        remove: 'Διακοπή παρακολούθησης',
        addTitle: 'Παρακολούθησε ένα σετ πριν αποκτήσεις κάρτες',
        addDescription: 'Διάλεξε παιχνίδι και σετ — θα παίρνεις ειδοποιήσεις ακόμα κι αν δεν έχεις καμία κάρτα του ακόμα.',
        gameLabel: 'Παιχνίδι',
        searchPlaceholder: 'Αναζήτησε ένα σετ…',
        watchCta: 'Παρακολούθησε',
        alreadyWatching: 'Ήδη παρακολουθείς',
        noResults: 'Κανένα σετ δεν ταιριάζει με την αναζήτησή σου.',
        signInTitle: 'Συνδέσου για να διαχειριστείς τις ειδοποιήσεις',
        signInCta: 'Σύνδεση',
        loadError: 'Δεν φορτώθηκαν οι ρυθμίσεις ειδοποιήσεων. Δοκίμασε ξανά σε λίγο.',
        watchedProgress: 'σετ σε παρακολούθηση',
        upgradeCta: 'Αναβάθμιση σε PRO για απεριόριστα',
        limitReached: 'Τα δωρεάν accounts παρακολουθούν έως 10 σετ — αναβάθμισε σε Cardora PRO για απεριόριστα missing-card alerts.',
      }

  const trackedSetIds = new Set(sets.map((set) => set.id))

  const handleToggle = async () => {
    const next = !enabled
    setEnabled(next)
    setSavingToggle(true)
    try {
      await updateBinderAlertSettings(next)
    } catch {
      setEnabled(!next)
    } finally {
      setSavingToggle(false)
    }
  }

  const handleWatch = async (set) => {
    setPendingSetId(set.id)
    setWatchError(null)
    try {
      await watchBinderSet(set.id)
      loadAlerts()
    } catch (err) {
      setWatchError(err?.status === 402 ? copy.limitReached : err?.message || copy.limitReached)
    } finally {
      setPendingSetId(null)
    }
  }

  const handleUnwatch = async (setId) => {
    setPendingSetId(setId)
    setSets((prev) => prev.filter((set) => set.id !== setId))
    try {
      await unwatchBinderSet(setId)
    } catch {
      loadAlerts()
    } finally {
      setPendingSetId(null)
    }
  }

  if (!isAuthenticated) {
    return (
      <BinderShell>
        <div className="rounded-xl border border-dashed border-[#eadab7] p-12 text-center">
          <p className="font-display text-xl font-semibold text-ink">{copy.signInTitle}</p>
          <Button as={Link} to={localized('/eisodos')} className="mt-5">
            {copy.signInCta}
          </Button>
        </div>
      </BinderShell>
    )
  }

  return (
    <BinderShell>
      <div className="mb-8 max-w-2xl">
        <h2 className="font-display text-3xl font-semibold text-ink sm:text-4xl">{copy.title}</h2>
        <p className="mt-2 text-sm leading-6 text-mist">{copy.description}</p>
      </div>

      {error ? (
        <div className="rounded-xl border border-dashed border-[#eadab7] p-8 text-center text-sm text-mist">{copy.loadError}</div>
      ) : loading ? (
        <div className="h-20 animate-pulse rounded-xl border border-[#eee2c4] bg-[#fdf8ee]" />
      ) : (
        <>
          <div className="flex items-start justify-between gap-4 rounded-xl border border-[#eee2c4] bg-white px-5 py-4">
            <div className="flex items-start gap-3">
              <BellRing className="mt-0.5 h-4.5 w-4.5 shrink-0 text-[#9d6a17]" />
              <div>
                <p className="text-sm font-semibold text-ink">{copy.toggleLabel}</p>
                <p className="mt-1 text-xs leading-5 text-slate-400">{copy.toggleHint}</p>
              </div>
            </div>
            <ToggleSwitch checked={enabled} onChange={handleToggle} disabled={savingToggle} />
          </div>

          {!isPro && watchedLimit ? (
            <div className="mt-3 flex items-center justify-between gap-3 rounded-xl border border-[#eee2c4] bg-white px-5 py-3">
              <p className="text-xs font-medium text-slate-500">
                {watchedCount}/{watchedLimit} {copy.watchedProgress}
              </p>
              {watchedCount >= watchedLimit ? (
                <Link
                  to={localized('/cardora-pro')}
                  className="flex items-center gap-1.5 text-xs font-semibold text-[#9d6a17] hover:underline"
                >
                  <Crown className="h-3.5 w-3.5" />
                  {copy.upgradeCta}
                </Link>
              ) : null}
            </div>
          ) : null}

          <div className="mt-10">
            <h3 className="mb-3 font-display text-xl font-semibold text-ink">{copy.trackedTitle}</h3>
            {sets.length ? (
              <div className="divide-y divide-[#eee2c4] rounded-xl border border-[#eee2c4]">
                {sets.map((set) => {
                  const logo = GAME_LOGOS[set.game.slug]
                  return (
                    <div key={set.id} className="flex items-center justify-between gap-4 px-4 py-3">
                      <div className="flex min-w-0 items-center gap-3">
                        {logo ? (
                          <img
                            src={logo}
                            alt={set.game.name}
                            className={cn('h-5 max-w-[70px] shrink-0 object-contain object-left', MONOCHROME_LOGO_SLUGS.has(set.game.slug) && 'brightness-0')}
                          />
                        ) : (
                          <span className="shrink-0 text-[11px] font-semibold text-slate-400">{set.game.name}</span>
                        )}
                        <p className="truncate text-sm font-medium text-ink">{set.name}</p>
                      </div>
                      <div className="flex shrink-0 items-center gap-3">
                        <span
                          className={cn(
                            'rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide',
                            set.source === 'owned'
                              ? 'border-[#eadab7] bg-[#fff8ec] text-[#9d6a17]'
                              : 'border-[#eee2c4] text-slate-400',
                          )}
                        >
                          {set.source === 'owned' ? copy.owned : copy.watched}
                        </span>
                        {set.source === 'watched' ? (
                          <button
                            type="button"
                            onClick={() => handleUnwatch(set.id)}
                            disabled={pendingSetId === set.id}
                            className="text-slate-300 transition hover:text-rose-500 disabled:opacity-40"
                            aria-label={copy.remove}
                          >
                            <X className="h-4 w-4" />
                          </button>
                        ) : null}
                      </div>
                    </div>
                  )
                })}
              </div>
            ) : (
              <p className="rounded-xl border border-dashed border-[#eadab7] p-6 text-center text-sm text-slate-500">
                {copy.trackedEmpty}
              </p>
            )}
          </div>

          <div className="mt-10">
            <h3 className="font-display text-xl font-semibold text-ink">{copy.addTitle}</h3>
            <p className="mt-1 text-sm leading-6 text-mist">{copy.addDescription}</p>

            {watchError ? (
              <div className="mt-3 flex items-center gap-2 rounded-xl border border-[#eadab7] bg-[#fff8ec] px-4 py-2.5 text-xs text-[#6b4718]">
                <Crown className="h-3.5 w-3.5 shrink-0" />
                <span>{watchError}</span>
                <Link to={localized('/cardora-pro')} className="ml-auto shrink-0 font-semibold underline">
                  {copy.upgradeCta}
                </Link>
              </div>
            ) : null}

            <div className="mt-4 flex flex-col gap-3 sm:flex-row">
              <Select value={gameSlug} onChange={(event) => setGameSlug(event.target.value)} className="sm:w-56">
                {games.map((game) => (
                  <option key={game.slug} value={game.slug}>
                    {game.name}
                  </option>
                ))}
              </Select>
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
            </div>

            <div className="mt-4 divide-y divide-[#eee2c4] rounded-xl border border-[#eee2c4]">
              {searching ? (
                <div className="p-4 text-center text-sm text-slate-400">…</div>
              ) : searchResults.length ? (
                searchResults.map((set) => {
                  const isTracked = trackedSetIds.has(set.id)
                  return (
                    <div key={set.id} className="flex items-center justify-between gap-4 px-4 py-3">
                      <p className="truncate text-sm font-medium text-ink">{set.name}</p>
                      <button
                        type="button"
                        disabled={isTracked || pendingSetId === set.id}
                        onClick={() => handleWatch(set)}
                        className={cn(
                          'shrink-0 rounded-md border px-3 py-1.5 text-xs font-semibold transition',
                          isTracked
                            ? 'border-[#eee2c4] text-slate-300'
                            : 'border-[#c79d62] text-[#6b4718] hover:bg-[#fff8ec]',
                        )}
                      >
                        {isTracked ? copy.alreadyWatching : copy.watchCta}
                      </button>
                    </div>
                  )
                })
              ) : (
                <div className="p-6 text-center text-sm text-slate-400">{copy.noResults}</div>
              )}
            </div>
          </div>
        </>
      )}
    </BinderShell>
  )
}

export default BinderAlertsPage
