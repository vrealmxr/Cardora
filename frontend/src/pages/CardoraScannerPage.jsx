import { AlertCircle, Camera, Crown, ExternalLink, ImagePlus, Loader2, Search, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import BinderShell from '@/components/binder/BinderShell'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input } from '@/components/ui/Input'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { cardoraService } from '@/services/cardoraService'
import { cn, localizePath } from '@/utils/helpers'

function formatPrice(value, currency, locale) {
  if (value === null || value === undefined) return '—'
  try {
    return new Intl.NumberFormat(locale === 'en' ? 'en-IE' : 'el-GR', {
      style: 'currency',
      currency: currency || 'EUR',
    }).format(value)
  } catch {
    return `${Number(value).toFixed(2)} ${currency ?? ''}`.trim()
  }
}

function CardoraScannerPage() {
  const { locale } = useI18n()
  const { isAuthenticated } = useAuth()
  const navigate = useNavigate()
  const isEnglish = locale === 'en'
  const localized = (path) => localizePath(path, locale)
  const fileInputRef = useRef(null)

  const [mode, setMode] = useState('search')
  const [query, setQuery] = useState('')
  const [imagePreview, setImagePreview] = useState(null)
  const [imageName, setImageName] = useState('')
  const [isSearching, setIsSearching] = useState(false)
  const [results, setResults] = useState(null)
  const [searchedTerm, setSearchedTerm] = useState('')
  const [usage, setUsage] = useState(null)
  const [usageError, setUsageError] = useState(null)
  const [lookupError, setLookupError] = useState(null)

  useEffect(() => {
    document.title = 'Cardora Scanner'
  }, [])

  useEffect(() => {
    if (!isAuthenticated) return
    cardoraService
      .getScannerUsage()
      .then((data) => setUsage(data))
      .catch(() => setUsage(null))
  }, [isAuthenticated])

  useEffect(
    () => () => {
      if (imagePreview) URL.revokeObjectURL(imagePreview)
    },
    [imagePreview],
  )

  const copy = isEnglish
    ? {
        eyebrow: 'Instant price check',
        title: 'Cardora Scanner',
        description: 'Search a card by name, or upload a photo — we\'ll estimate a market price from live eBay listings.',
        searchTab: 'Search by name',
        uploadTab: 'Upload a photo',
        searchPlaceholder: 'e.g. Charizard ex 199/165',
        searchCta: 'Search price',
        dropTitle: 'Drop a photo here',
        dropHint: 'or click to browse — JPG, PNG up to 10MB',
        analyzeCta: 'Identify & search price',
        change: 'Change photo',
        scanning: 'Scanning sources…',
        resultsTitle: 'Estimated prices',
        resultsFor: 'Results for',
        suggested: 'Suggested price (median)',
        listings: 'active listings',
        statLow: 'Lowest',
        statMedian: 'Median',
        statAverage: 'Average',
        statHigh: 'Highest',
        basedOn: (used, total) => `Based on ${used} of ${total} active eBay listings`,
        sampleListings: 'Sample listings',
        viewOnEbay: 'View on eBay',
        noResults: 'No eBay listings found for this search. Try the card name with its number, e.g. “Pikachu 025/165”.',
        lookupFailed: 'We could not fetch prices right now. Please try again in a moment.',
        sandboxNote: 'Test mode: eBay sandbox data, not real market prices.',
        disclaimer: 'Estimates from current asking prices on active eBay listings, with outliers removed. Not a guaranteed sale price.',
        scansLeft: 'scans left this month',
        unlimitedScans: 'Unlimited scans (PRO)',
        limitReached: "You've used all your free scans this month. Upgrade to Cardora PRO for unlimited AI Scanner.",
        upgradeCta: 'Upgrade to PRO',
      }
    : {
        eyebrow: 'Άμεσος έλεγχος τιμής',
        title: 'Cardora Scanner',
        description: 'Αναζήτησε μια κάρτα με το όνομά της, ή ανέβασε φωτογραφία — θα εκτιμήσουμε την τιμή αγοράς από ενεργές καταχωρήσεις στο eBay.',
        searchTab: 'Αναζήτηση με όνομα',
        uploadTab: 'Ανέβασμα φωτογραφίας',
        searchPlaceholder: 'π.χ. Charizard ex 199/165',
        searchCta: 'Αναζήτηση τιμής',
        dropTitle: 'Άσε μια φωτογραφία εδώ',
        dropHint: 'ή πάτα για να την επιλέξεις — JPG, PNG έως 10MB',
        analyzeCta: 'Αναγνώριση & αναζήτηση τιμής',
        change: 'Άλλαξε φωτογραφία',
        scanning: 'Σάρωση πηγών…',
        resultsTitle: 'Εκτιμώμενες τιμές',
        resultsFor: 'Αποτελέσματα για',
        suggested: 'Προτεινόμενη τιμή (διάμεσος)',
        listings: 'ενεργές καταχωρήσεις',
        statLow: 'Χαμηλότερη',
        statMedian: 'Διάμεσος',
        statAverage: 'Μέσος όρος',
        statHigh: 'Υψηλότερη',
        basedOn: (used, total) => `Με βάση ${used} από ${total} ενεργές καταχωρήσεις στο eBay`,
        sampleListings: 'Ενδεικτικές καταχωρήσεις',
        viewOnEbay: 'Δες στο eBay',
        noResults: 'Δεν βρέθηκαν καταχωρήσεις στο eBay. Δοκίμασε το όνομα της κάρτας μαζί με τον αριθμό της, π.χ. «Pikachu 025/165».',
        lookupFailed: 'Δεν μπορέσαμε να φέρουμε τιμές αυτή τη στιγμή. Δοκίμασε ξανά σε λίγο.',
        sandboxNote: 'Δοκιμαστική λειτουργία: δεδομένα eBay sandbox, όχι πραγματικές τιμές αγοράς.',
        disclaimer: 'Εκτίμηση από τις τρέχουσες τιμές ενεργών καταχωρήσεων στο eBay, χωρίς τις ακραίες τιμές. Δεν είναι εγγυημένη τιμή πώλησης.',
        scansLeft: 'scans διαθέσιμα αυτόν τον μήνα',
        unlimitedScans: 'Απεριόριστα scans (PRO)',
        limitReached: 'Έχεις χρησιμοποιήσει όλα τα δωρεάν scans σου αυτόν τον μήνα. Αναβάθμισε σε Cardora PRO για απεριόριστο AI Scanner.',
        upgradeCta: 'Αναβάθμιση σε PRO',
      }

  const runSearch = async (term) => {
    if (!term.trim()) return

    if (!isAuthenticated) {
      navigate(localized('/eisodos'))
      return
    }

    setUsageError(null)

    try {
      const result = await cardoraService.recordScannerUsage()
      setUsage(result)
    } catch (err) {
      if (err?.status === 402) {
        setUsageError(copy.limitReached)
        return
      }
      // Non-quota failure (network etc.) — still let the preview search run.
    }

    setIsSearching(true)
    setResults(null)
    setLookupError(null)
    setSearchedTerm(term.trim())

    try {
      setResults(await cardoraService.getScannerPrice(term.trim()))
    } catch {
      setLookupError(copy.lookupFailed)
    } finally {
      setIsSearching(false)
    }
  }

  const scanBlocked = Boolean(usage && !usage.unlimited && usage.remaining === 0)

  const handleFile = (file) => {
    if (!file) return
    setImagePreview(URL.createObjectURL(file))
    setImageName(file.name)
    setResults(null)
  }

  const hasPrices = Boolean(results && results.used_for_stats > 0)
  const price = (value) => formatPrice(value, results?.currency, locale)

  return (
    <BinderShell>
      <div className="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div className="max-w-2xl">
          <p className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#9d6a17]">{copy.eyebrow}</p>
          <h2 className="mt-2 font-display text-3xl font-semibold text-ink sm:text-4xl">{copy.title}</h2>
          <p className="mt-2 text-sm leading-6 text-mist">{copy.description}</p>
        </div>
        {isAuthenticated && usage ? (
          <div className="rounded-xl border border-[#eee2c4] bg-white px-4 py-2.5 text-xs font-medium text-slate-500">
            {usage.unlimited ? (
              <span className="flex items-center gap-1.5 text-[#9d6a17]">
                <Crown className="h-3.5 w-3.5" /> {copy.unlimitedScans}
              </span>
            ) : (
              <span>
                {usage.remaining}/{usage.limit} {copy.scansLeft}
              </span>
            )}
          </div>
        ) : null}
      </div>

      {usageError ? (
        <div className="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-[#eadab7] bg-[#fff8ec] px-4 py-3 text-sm text-[#6b4718]">
          <Crown className="h-4 w-4 shrink-0" />
          <span className="flex-1">{usageError}</span>
          <Link to={localized('/cardora-pro')} className="shrink-0 font-semibold underline">
            {copy.upgradeCta}
          </Link>
        </div>
      ) : null}

      <div className="mb-5 inline-flex rounded-2xl border border-[#eadab7] bg-white p-1.5 shadow-glass">
        <button
          type="button"
          onClick={() => setMode('search')}
          className={cn(
            'flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold transition',
            mode === 'search'
              ? 'border border-[#d8b06a] bg-[linear-gradient(145deg,rgba(255,247,229,0.98)_0%,rgba(243,229,193,0.96)_100%)] text-[#6b4718]'
              : 'text-slate-600 hover:text-[#6b4718]',
          )}
        >
          <Search className="h-3.5 w-3.5" />
          {copy.searchTab}
        </button>
        <button
          type="button"
          onClick={() => setMode('upload')}
          className={cn(
            'flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold transition',
            mode === 'upload'
              ? 'border border-[#d8b06a] bg-[linear-gradient(145deg,rgba(255,247,229,0.98)_0%,rgba(243,229,193,0.96)_100%)] text-[#6b4718]'
              : 'text-slate-600 hover:text-[#6b4718]',
          )}
        >
          <Camera className="h-3.5 w-3.5" />
          {copy.uploadTab}
        </button>
      </div>

      <CardSurface hover={false}>
        {mode === 'search' ? (
          <form
            onSubmit={(event) => {
              event.preventDefault()
              runSearch(query)
            }}
            className="flex flex-col gap-3 sm:flex-row"
          >
            <div className="relative flex-1">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <Input
                type="text"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={copy.searchPlaceholder}
                className="py-3 pl-10"
              />
            </div>
            <Button type="submit" disabled={isSearching || !query.trim() || scanBlocked}>
              {isSearching ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
              {copy.searchCta}
            </Button>
          </form>
        ) : (
          <div>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              className="hidden"
              onChange={(event) => handleFile(event.target.files?.[0])}
            />
            {imagePreview ? (
              <div className="flex flex-col items-center gap-4 sm:flex-row">
                <div className="relative">
                  <img
                    src={imagePreview}
                    alt={imageName}
                    className="h-36 w-28 rounded-[14px] border border-[#eadab7] object-cover shadow-glass"
                  />
                  <button
                    type="button"
                    onClick={() => {
                      setImagePreview(null)
                      setImageName('')
                      setResults(null)
                      if (fileInputRef.current) fileInputRef.current.value = ''
                    }}
                    className="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full border border-[#eadab7] bg-white text-slate-500 shadow-glass hover:text-slate-800"
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                </div>
                <div className="flex-1 text-center sm:text-left">
                  <p className="truncate text-sm font-medium text-slate-600">{imageName}</p>
                  <div className="mt-3 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                    <Button
                      onClick={() => runSearch(imageName.replace(/\.[a-z0-9]+$/i, '') || 'card')}
                      disabled={isSearching || scanBlocked}
                    >
                      {isSearching ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
                      {copy.analyzeCta}
                    </Button>
                    <button
                      type="button"
                      onClick={() => fileInputRef.current?.click()}
                      className="text-xs font-semibold text-slate-500 underline decoration-dotted underline-offset-4 hover:text-slate-700"
                    >
                      {copy.change}
                    </button>
                  </div>
                </div>
              </div>
            ) : (
              <button
                type="button"
                onClick={() => fileInputRef.current?.click()}
                className="flex w-full flex-col items-center gap-3 rounded-[18px] border-2 border-dashed border-[#eadab7] py-12 text-slate-500 transition hover:border-[#d8b06a] hover:text-[#6b4718]"
              >
                <ImagePlus className="h-8 w-8" />
                <span className="text-sm font-semibold text-ink">{copy.dropTitle}</span>
                <span className="text-xs text-slate-400">{copy.dropHint}</span>
              </button>
            )}
          </div>
        )}
      </CardSurface>

      {isSearching ? (
        <div className="mt-8 flex items-center gap-3 text-sm text-slate-500">
          <Loader2 className="h-4 w-4 animate-spin text-[#9d6a17]" />
          {copy.scanning}
        </div>
      ) : null}

      {lookupError && !isSearching ? (
        <div className="mt-8 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          <AlertCircle className="h-4 w-4 shrink-0" />
          <span>{lookupError}</span>
        </div>
      ) : null}

      {results && !isSearching ? (
        <div className="mt-8">
          <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
              <h3 className="font-display text-xl font-semibold text-ink">{copy.resultsTitle}</h3>
              <p className="text-xs text-slate-500">
                {copy.resultsFor} <span className="font-semibold text-slate-700">“{searchedTerm}”</span>
              </p>
            </div>
            {hasPrices ? (
              <div className="flex items-center gap-2 rounded-xl border border-[#eadab7] bg-[#fff8ec] px-4 py-2.5">
                <div>
                  <p className="text-[10px] font-semibold uppercase tracking-wide text-[#9d6a17]/80">{copy.suggested}</p>
                  <p className="font-display text-lg font-bold text-[#6b4718]">{price(results.median)}</p>
                </div>
              </div>
            ) : null}
          </div>

          {results.environment === 'sandbox' ? (
            <p className="mb-4 rounded-lg border border-dashed border-[#eadab7] bg-[#fffaf0] px-3 py-2 text-[11px] font-medium text-[#9d6a17]">
              {copy.sandboxNote}
            </p>
          ) : null}

          {hasPrices ? (
            <>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {[
                  [copy.statLow, results.min],
                  [copy.statMedian, results.median],
                  [copy.statAverage, results.average],
                  [copy.statHigh, results.max],
                ].map(([label, value]) => (
                  <div key={label} className="rounded-[16px] border border-[#ead9b1] bg-white px-4 py-3.5 shadow-glass">
                    <p className="text-[11px] text-slate-400">{label}</p>
                    <p className="font-display text-lg font-bold text-[#9d6a17]">{price(value)}</p>
                  </div>
                ))}
              </div>
              <p className="mt-2 text-[11px] text-slate-500">
                {copy.basedOn(results.used_for_stats, results.total_matches)}
              </p>

              {results.items?.length ? (
                <div className="mt-6">
                  <h4 className="mb-3 text-sm font-semibold text-ink">{copy.sampleListings}</h4>
                  <ul className="grid gap-3 sm:grid-cols-2">
                    {results.items.slice(0, 6).map((item) => (
                      <li key={item.item_id ?? item.url}>
                        <a
                          href={item.url}
                          target="_blank"
                          rel="noopener noreferrer nofollow"
                          className="flex items-center gap-3 rounded-[16px] border border-[#ead9b1] bg-white p-3 shadow-glass transition hover:border-[#d8b06a]"
                        >
                          {item.image_url ? (
                            <img
                              src={item.image_url}
                              alt=""
                              loading="lazy"
                              className="h-14 w-11 shrink-0 rounded-md border border-[#f0e4c8] object-cover"
                            />
                          ) : null}
                          <div className="min-w-0 flex-1">
                            <p className="line-clamp-2 text-xs font-medium text-slate-700">{item.title}</p>
                            <p className="mt-1 flex items-center gap-1 text-[11px] text-slate-400">
                              {item.condition ? `${item.condition} · ` : ''}
                              {copy.viewOnEbay} <ExternalLink className="h-3 w-3" />
                            </p>
                          </div>
                          <p className="shrink-0 font-display text-sm font-bold text-[#9d6a17]">
                            {formatPrice(item.price, item.currency, locale)}
                          </p>
                        </a>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
            </>
          ) : (
            <p className="rounded-[16px] border border-[#ead9b1] bg-white px-4 py-4 text-sm text-slate-500 shadow-glass">
              {copy.noResults}
            </p>
          )}

          <p className="mt-4 text-[11px] text-slate-400">{copy.disclaimer}</p>
        </div>
      ) : null}
    </BinderShell>
  )
}

export default CardoraScannerPage
