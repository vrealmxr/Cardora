import { Plus } from 'lucide-react'
import { useEffect } from 'react'
import { Link } from 'react-router-dom'
import BinderShell from '@/components/binder/BinderShell'
import SetCard from '@/components/binder/SetCard'
import Button from '@/components/ui/Button'
import { getMyRegisteredSets } from '@/data/binderMockData'
import { useI18n } from '@/hooks/useI18n'
import { localizePath } from '@/utils/helpers'

function BinderLibraryPage() {
  const { locale } = useI18n()
  const isEnglish = locale === 'en'
  const localized = (path) => localizePath(path, locale)
  const mySets = getMyRegisteredSets()

  useEffect(() => {
    document.title = 'Cardora Binder — My Sets'
  }, [])

  const copy = isEnglish
    ? {
        eyebrow: 'My binder',
        title: 'Your registered sets',
        description: 'Tap a set to see every card in it, check off what you own, and track its value.',
        register: 'Register a set',
        empty: 'You have not registered any sets yet — register your first one to start filling your binder.',
      }
    : {
        eyebrow: 'Το binder μου',
        title: 'Τα καταχωρημένα σετ σου',
        description: 'Πάτα ένα σετ για να δεις όλες τις κάρτες του, να τσεκάρεις αυτές που έχεις και να παρακολουθείς την αξία του.',
        register: 'Καταχώρησε σετ',
        empty: 'Δεν έχεις καταχωρήσει σετ ακόμα — καταχώρησε το πρώτο σου σετ για να ξεκινήσεις να γεμίζεις το binder σου.',
      }

  return (
    <BinderShell>
      <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div className="max-w-xl">
          <p className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#9d6a17]">{copy.eyebrow}</p>
          <h2 className="mt-2 font-display text-3xl font-semibold text-ink sm:text-4xl">{copy.title}</h2>
          <p className="mt-2 text-sm leading-6 text-mist">{copy.description}</p>
        </div>
        <Button as={Link} to={localized('/cardora-binder/sets')} className="self-start">
          <Plus className="h-4 w-4" />
          {copy.register}
        </Button>
      </div>

      {mySets.length ? (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
          {mySets.map(({ set, completion }) => (
            <SetCard key={set.id} set={set} to={localized(`/cardora-binder/sets/${set.id}`)} completion={completion} />
          ))}
        </div>
      ) : (
        <div className="rounded-[24px] border border-dashed border-[#eadab7] p-12 text-center">
          <p className="mx-auto max-w-md text-sm text-mist">{copy.empty}</p>
          <Button as={Link} to={localized('/cardora-binder/sets')} className="mt-5">
            <Plus className="h-4 w-4" />
            {copy.register}
          </Button>
        </div>
      )}
    </BinderShell>
  )
}

export default BinderLibraryPage
