import { CheckCircle2, Loader2, MapPin, Search } from 'lucide-react'
import { useState } from 'react'
import Button from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { apiClient } from '@/services/apiClient'
import { normalizeTextTree } from '@/utils/textEncoding'

function PickupPointPicker({
  carrier,
  locale = 'el',
  defaultCountryCode = 'GR',
  defaultPostalCode = '',
  defaultCity = '',
  selectedPoint = null,
  onSelect,
}) {
  const isEnglish = locale === 'en'
  const [postalCode, setPostalCode] = useState(defaultPostalCode)
  const [city, setCity] = useState(defaultCity)
  const [points, setPoints] = useState([])
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState('')
  const [available, setAvailable] = useState(true)
  const [hasSearched, setHasSearched] = useState(false)

  const copy = normalizeTextTree(
    isEnglish
      ? {
          title: carrier === 'boxnow' ? 'Choose a BoxNow locker' : 'Choose a DHL service point',
          postalCode: 'Postal code',
          city: 'City',
          search: 'Search points',
          searching: 'Searching...',
          empty: 'No pickup points found for this area. Try a different postal code.',
          unavailable:
            'Live pickup point lookup is not available yet. It will turn on automatically once carrier credentials are active.',
          selected: 'Selected pickup point',
          choose: 'Select',
          change: 'Change',
          hint: 'Enter a postal code and search to see nearby points.',
        }
      : {
          title: carrier === 'boxnow' ? 'Επίλεξε locker της BoxNow' : 'Επίλεξε σημείο DHL Service Point',
          postalCode: 'Ταχυδρομικός κώδικας',
          city: 'Πόλη',
          search: 'Αναζήτηση σημείων',
          searching: 'Αναζήτηση...',
          empty: 'Δεν βρέθηκαν σημεία παραλαβής για αυτή την περιοχή. Δοκίμασε άλλον ταχυδρομικό κώδικα.',
          unavailable:
            'Η ζωντανή αναζήτηση σημείων παραλαβής δεν είναι ακόμα διαθέσιμη. Θα ενεργοποιηθεί αυτόματα μόλις ενεργοποιηθούν τα credentials του μεταφορέα.',
          selected: 'Επιλεγμένο σημείο παραλαβής',
          choose: 'Επιλογή',
          change: 'Αλλαγή',
          hint: 'Βάλε ταχυδρομικό κώδικα και κάνε αναζήτηση για να δεις κοντινά σημεία.',
        },
  )

  const handleSearch = async () => {
    setIsLoading(true)
    setError('')

    try {
      const params = new URLSearchParams({ carrier })
      params.set('country_code', defaultCountryCode || 'GR')
      if (postalCode) params.set('postal_code', postalCode)
      if (city) params.set('city', city)

      const response = await apiClient.get(`/shipping/pickup-points?${params.toString()}`)
      setAvailable(Boolean(response?.available))
      setPoints(Array.isArray(response?.points) ? response.points : [])
      setHasSearched(true)
    } catch (requestError) {
      setError(requestError.message)
      setPoints([])
    } finally {
      setIsLoading(false)
    }
  }

  const formatPointAddress = (point) =>
    [point.address, point.postal_code, point.city].filter(Boolean).join(', ')

  if (selectedPoint) {
    return (
      <div className="rounded-2xl border border-emerald-400/25 bg-emerald-500/10 p-4">
        <p className="text-[11px] uppercase tracking-[0.24em] text-emerald-800">{copy.selected}</p>
        <div className="mt-2 flex items-start justify-between gap-3">
          <div className="flex items-start gap-2">
            <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-200" />
            <div>
              <p className="text-sm font-semibold text-white">{selectedPoint.name}</p>
              <p className="mt-1 text-xs leading-6 text-mist">{formatPointAddress(selectedPoint)}</p>
            </div>
          </div>
          <Button type="button" variant="secondary" size="sm" onClick={() => onSelect?.(null)}>
            {copy.change}
          </Button>
        </div>
      </div>
    )
  }

  return (
    <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
      <div className="flex items-center gap-2 text-sm font-semibold text-white">
        <MapPin className="h-4 w-4 text-gold-100" />
        {copy.title}
      </div>

      <div className="mt-3 grid gap-3 sm:grid-cols-[1fr,1fr,auto]">
        <Input
          value={postalCode}
          onChange={(event) => setPostalCode(event.target.value)}
          placeholder={copy.postalCode}
        />
        <Input value={city} onChange={(event) => setCity(event.target.value)} placeholder={copy.city} />
        <Button type="button" variant="secondary" onClick={handleSearch} disabled={isLoading}>
          {isLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
          {isLoading ? copy.searching : copy.search}
        </Button>
      </div>

      {error ? (
        <p className="mt-3 rounded-xl border border-rose-400/20 bg-rose-500/10 px-3 py-2 text-xs text-rose-100">
          {error}
        </p>
      ) : null}

      {!hasSearched && !error ? <p className="mt-3 text-xs leading-6 text-mist">{copy.hint}</p> : null}

      {hasSearched && !available ? (
        <p className="mt-3 rounded-xl border border-amber-400/20 bg-amber-500/10 px-3 py-2 text-xs text-amber-800">
          {copy.unavailable}
        </p>
      ) : null}

      {hasSearched && available && points.length === 0 ? (
        <p className="mt-3 text-xs leading-6 text-mist">{copy.empty}</p>
      ) : null}

      {points.length > 0 ? (
        <ul className="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
          {points.map((point) => (
            <li key={point.id}>
              <button
                type="button"
                onClick={() => onSelect?.({ ...point, carrier })}
                className="flex w-full items-start justify-between gap-3 rounded-xl border border-white/10 bg-[#0d1523] px-3 py-2 text-left transition hover:border-gold-300/40"
              >
                <span>
                  <span className="block text-sm font-semibold text-white">{point.name}</span>
                  <span className="mt-1 block text-xs leading-6 text-mist">{formatPointAddress(point)}</span>
                </span>
                <span className="mt-1 shrink-0 text-[11px] font-semibold uppercase tracking-[0.2em] text-gold-100">
                  {copy.choose}
                </span>
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  )
}

export default PickupPointPicker
