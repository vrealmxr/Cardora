import { ChevronRight, Plus } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import BinderShell from '@/components/binder/BinderShell'
import CompletionBar from '@/components/binder/CompletionBar'
import Button from '@/components/ui/Button'
import { fetchMyBinderCollection } from '@/services/binderService'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { localizePath } from '@/utils/helpers'

function MySetCard({ set, to, cardsLabel }) {
  return (
    <Link
      to={to}
      className="group flex flex-col rounded-xl border border-[#eee2c4] bg-white p-4 transition hover:border-[#c79d62]"
    >
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="truncate text-[10px] font-semibold uppercase tracking-wider text-slate-400">{set.game.name}</p>
          <p className="mt-1 truncate text-sm font-semibold text-ink group-hover:text-[#6b4718]">{set.name}</p>
        </div>
        <ChevronRight className="mt-0.5 h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-[#9d6a17]" />
      </div>

      <div className="mt-3">
        <CompletionBar percent={set.percent} size="sm" />
        <p className="mt-1.5 text-[11px] text-slate-500">
          {set.ownedCount}/{set.cardCount} {cardsLabel} · €{set.value.toFixed(2)}
        </p>
      </div>
    </Link>
  )
}

function BinderLibraryPage() {
  const { locale } = useI18n()
  const { isAuthenticated } = useAuth()
  const isEnglish = locale === 'en'
  const localized = (path) => localizePath(path, locale)

  const [loading, setLoading] = useState(isAuthenticated)
  const [mySets, setMySets] = useState([])

  useEffect(() => {
    document.title = 'Cardora Binder — My Sets'
  }, [])

  useEffect(() => {
    if (!isAuthenticated) {
      setLoading(false)
      return undefined
    }

    let cancelled = false
    setLoading(true)

    fetchMyBinderCollection()
      .then((res) => {
        if (!cancelled) setMySets(res?.data ?? [])
      })
      .catch(() => {
        if (!cancelled) setMySets([])
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
        title: 'Your registered sets',
        description: 'Tap a set to see every card in it, check off what you own, and track its value.',
        register: 'Register a set',
        empty: 'You have not registered any sets yet — check off a card in a set to start filling your binder.',
        cardsLabel: 'cards',
        signInTitle: 'Sign in to see your sets',
        signInDesc: 'The sets you register show up here once you sign in and check off a card.',
        signInCta: 'Sign in',
      }
    : {
        title: 'Τα καταχωρημένα σετ σου',
        description: 'Πάτα ένα σετ για να δεις όλες τις κάρτες του, να τσεκάρεις αυτές που έχεις και να παρακολουθείς την αξία του.',
        register: 'Καταχώρησε σετ',
        empty: 'Δεν έχεις καταχωρήσει σετ ακόμα — τσέκαρε μια κάρτα σε ένα σετ για να ξεκινήσεις να γεμίζεις το binder σου.',
        cardsLabel: 'κάρτες',
        signInTitle: 'Συνδέσου για να δεις τα σετ σου',
        signInDesc: 'Τα σετ που καταχωρείς εμφανίζονται εδώ μόλις συνδεθείς και τσεκάρεις μια κάρτα.',
        signInCta: 'Σύνδεση',
      }

  return (
    <BinderShell>
      <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div className="max-w-xl">
          <h2 className="font-display text-3xl font-semibold text-ink sm:text-4xl">{copy.title}</h2>
          <p className="mt-2 text-sm leading-6 text-mist">{copy.description}</p>
        </div>
        <Button as={Link} to={localized('/cardora-binder/games')} className="self-start">
          <Plus className="h-4 w-4" />
          {copy.register}
        </Button>
      </div>

      {!isAuthenticated ? (
        <div className="rounded-xl border border-dashed border-[#eadab7] p-12 text-center">
          <p className="font-display text-xl font-semibold text-ink">{copy.signInTitle}</p>
          <p className="mx-auto mt-2 max-w-md text-sm text-mist">{copy.signInDesc}</p>
          <Button as={Link} to={localized('/eisodos')} className="mt-5">
            {copy.signInCta}
          </Button>
        </div>
      ) : loading ? (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="h-28 animate-pulse rounded-xl border border-[#eee2c4] bg-[#fdf8ee]" />
          ))}
        </div>
      ) : mySets.length ? (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          {mySets.map((set) => (
            <MySetCard key={set.id} set={set} to={localized(`/cardora-binder/sets/${set.id}`)} cardsLabel={copy.cardsLabel} />
          ))}
        </div>
      ) : (
        <div className="rounded-xl border border-dashed border-[#eadab7] p-12 text-center">
          <p className="mx-auto max-w-md text-sm text-mist">{copy.empty}</p>
          <Button as={Link} to={localized('/cardora-binder/games')} className="mt-5">
            <Plus className="h-4 w-4" />
            {copy.register}
          </Button>
        </div>
      )}
    </BinderShell>
  )
}

export default BinderLibraryPage
