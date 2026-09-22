import { Layers } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import BinderShell from '@/components/binder/BinderShell'
import GAME_LOGOS, { MONOCHROME_LOGO_SLUGS } from '@/assets/binder-logos'
import { fetchBinderGames } from '@/services/binderService'
import { useI18n } from '@/hooks/useI18n'
import { cn, localizePath } from '@/utils/helpers'

const CATEGORY_ORDER = ['tcg', 'sports', 'gaming']

const CATEGORY_LABEL = {
  tcg: { el: 'Trading Card Games', en: 'Trading Card Games' },
  sports: { el: 'Sports', en: 'Sports' },
  gaming: { el: 'Gaming & Esports', en: 'Gaming & Esports' },
}

function BinderGamesPage() {
  const { locale } = useI18n()
  const isEnglish = locale === 'en'
  const localized = (path) => localizePath(path, locale)

  const [grouped, setGrouped] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    document.title = 'Cardora Binder'
  }, [])

  useEffect(() => {
    let cancelled = false

    fetchBinderGames()
      .then((payload) => {
        if (!cancelled) setGrouped(payload?.data ?? {})
      })
      .catch((err) => {
        if (!cancelled) setError(err.message)
      })

    return () => {
      cancelled = true
    }
  }, [])

  const copy = isEnglish
    ? {
        title: 'Pick a game to start collecting',
        description: 'Choose a franchise, browse its sets, and track exactly which cards you own.',
        sets: 'sets',
        loadError: 'Could not load the game list. Try again in a moment.',
      }
    : {
        title: 'Διάλεξε παιχνίδι για να ξεκινήσεις',
        description: 'Επίλεξε franchise, δες τα σετ του, και παρακολούθησε ακριβώς ποιες κάρτες έχεις.',
        sets: 'σετ',
        loadError: 'Δεν φορτώθηκε η λίστα παιχνιδιών. Δοκίμασε ξανά σε λίγο.',
      }

  return (
    <BinderShell>
      <div className="mb-10 max-w-2xl">
        <h2 className="font-display text-3xl font-semibold text-ink sm:text-4xl">{copy.title}</h2>
        <p className="mt-2 text-sm leading-6 text-mist">{copy.description}</p>
      </div>

      {error ? (
        <div className="rounded-xl border border-dashed border-[#eadab7] p-12 text-center">
          <p className="mx-auto max-w-md text-sm text-mist">{copy.loadError}</p>
        </div>
      ) : !grouped ? (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="h-24 animate-pulse rounded-xl border border-[#eee2c4] bg-[#fdf8ee]" />
          ))}
        </div>
      ) : (
        <div className="space-y-11">
          {CATEGORY_ORDER.filter((category) => grouped[category]?.length).map((category) => (
            <section key={category}>
              <p className="mb-3 border-b border-[#eee2c4] pb-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">
                {CATEGORY_LABEL[category][locale] ?? CATEGORY_LABEL[category].el}
              </p>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                {grouped[category].map((game) => {
                  const logo = GAME_LOGOS[game.slug]
                  return (
                    <Link
                      key={game.slug}
                      to={localized(`/cardora-binder/games/${game.slug}`)}
                      className="group flex flex-col justify-between rounded-xl border border-[#eee2c4] bg-white px-4 py-4 transition hover:border-[#c79d62]"
                    >
                      <div className="flex h-9 items-center">
                        {logo ? (
                          <img
                            src={logo}
                            alt={game.name}
                            className={cn(
                              'max-h-9 max-w-full object-contain object-left',
                              MONOCHROME_LOGO_SLUGS.has(game.slug) && 'brightness-0',
                            )}
                          />
                        ) : (
                          <p className="font-display text-lg font-semibold leading-snug text-ink group-hover:text-[#6b4718]">
                            {game.name}
                          </p>
                        )}
                      </div>
                      <p className="mt-3 flex items-center gap-1 text-[11px] text-slate-400">
                        <Layers className="h-3 w-3" />
                        {game.setsCount} {copy.sets}
                      </p>
                    </Link>
                  )
                })}
              </div>
            </section>
          ))}
        </div>
      )}
    </BinderShell>
  )
}

export default BinderGamesPage
