import { Search } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import BinderShell from '@/components/binder/BinderShell'
import SetCard from '@/components/binder/SetCard'
import { Input } from '@/components/ui/Input'
import { BINDER_CATEGORIES, BINDER_SETS, MY_BINDER } from '@/data/binderMockData'
import { useI18n } from '@/hooks/useI18n'
import { cn, localizePath } from '@/utils/helpers'

function BinderSetCatalogPage() {
  const { locale } = useI18n()
  const isEnglish = locale === 'en'
  const localized = (path) => localizePath(path, locale)

  const [category, setCategory] = useState('all')
  const [query, setQuery] = useState('')

  useEffect(() => {
    document.title = 'Cardora Binder — Register a set'
  }, [])

  const copy = isEnglish
    ? {
        eyebrow: 'Register a set',
        title: 'Find your set',
        description: 'Filter by franchise or brand, then pick the set you want to start tracking.',
        searchPlaceholder: 'Search a set…',
        all: 'All',
        results: 'sets found',
        empty: 'No sets match your filters.',
        registered: 'In your binder',
        register: 'Register',
      }
    : {
        eyebrow: 'Καταχώριση σετ',
        title: 'Βρες το σετ σου',
        description: 'Φιλτράρισε ανά franchise ή brand, και μετά επίλεξε το σετ που θέλεις να ξεκινήσεις να παρακολουθείς.',
        searchPlaceholder: 'Αναζήτησε ένα σετ…',
        all: 'Όλα',
        results: 'σετ βρέθηκαν',
        empty: 'Κανένα σετ δεν ταιριάζει με τα φίλτρα σου.',
        registered: 'Στο binder σου',
        register: 'Καταχώριση',
      }

  const filtered = useMemo(() => {
    return BINDER_SETS.filter((set) => {
      if (category !== 'all' && set.category !== category) return false
      if (query.trim() && !set.name.toLowerCase().includes(query.trim().toLowerCase())) return false
      return true
    })
  }, [category, query])

  return (
    <BinderShell>
      <div className="mb-6 max-w-2xl">
        <p className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#9d6a17]">{copy.eyebrow}</p>
        <h2 className="mt-2 font-display text-3xl font-semibold text-ink sm:text-4xl">{copy.title}</h2>
        <p className="mt-2 text-sm leading-6 text-mist">{copy.description}</p>
      </div>

      <div className="mb-6 flex flex-col gap-3 rounded-[20px] border border-[#eadab7] bg-white p-3 shadow-glass sm:flex-row sm:items-center">
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
        <div className="flex flex-wrap gap-1.5">
          <button
            type="button"
            onClick={() => setCategory('all')}
            className={cn(
              'rounded-full border px-3 py-1.5 text-xs font-semibold transition',
              category === 'all'
                ? 'border-[#d8b06a] bg-[linear-gradient(145deg,rgba(255,247,229,0.98)_0%,rgba(243,229,193,0.96)_100%)] text-[#6b4718]'
                : 'border-[#eadab7] bg-white text-slate-700 hover:border-[#d8b06a] hover:bg-[#fff8ec] hover:text-[#6b4718]',
            )}
          >
            {copy.all}
          </button>
          {BINDER_CATEGORIES.map((cat) => (
            <button
              key={cat.value}
              type="button"
              onClick={() => setCategory(cat.value)}
              className={cn(
                'rounded-full border px-3 py-1.5 text-xs font-semibold transition',
                category === cat.value
                  ? 'border-[#d8b06a] bg-[linear-gradient(145deg,rgba(255,247,229,0.98)_0%,rgba(243,229,193,0.96)_100%)] text-[#6b4718]'
                  : 'border-[#eadab7] bg-white text-slate-700 hover:border-[#d8b06a] hover:bg-[#fff8ec] hover:text-[#6b4718]',
              )}
            >
              {cat.label[locale] ?? cat.label.el}
            </button>
          ))}
        </div>
      </div>

      <p className="mb-4 text-xs font-semibold text-slate-400">
        {filtered.length} {copy.results}
      </p>

      {filtered.length ? (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          {filtered.map((set) => {
            const registered = MY_BINDER.registeredSetIds.includes(set.id)
            return (
              <SetCard
                key={set.id}
                set={set}
                to={localized(`/cardora-binder/sets/${set.id}`)}
                actionLabel={registered ? copy.registered : copy.register}
              />
            )
          })}
        </div>
      ) : (
        <div className="rounded-[24px] border border-dashed border-[#eadab7] p-12 text-center">
          <p className="mx-auto max-w-md text-sm text-mist">{copy.empty}</p>
        </div>
      )}
    </BinderShell>
  )
}

export default BinderSetCatalogPage
