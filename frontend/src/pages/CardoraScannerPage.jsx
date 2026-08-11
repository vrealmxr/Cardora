import { Camera, ImagePlus, Loader2, ScanLine, Search, Sparkles, TrendingUp, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import BinderShell from '@/components/binder/BinderShell'
import { useI18n } from '@/hooks/useI18n'
import { cn } from '@/utils/helpers'

const MOCK_SOURCES = ['Cardora Market', 'eBay', 'TCGplayer', 'Cardmarket']

function fakeSearch(term) {
  const seed = term.split('').reduce((sum, ch) => sum + ch.charCodeAt(0), 0) || 42
  const base = 8 + (seed % 180)
  return MOCK_SOURCES.map((source, i) => {
    const variance = ((seed * (i + 3)) % 40) - 15
    const price = Math.max(1, Math.round((base + variance) * 100) / 100)
    return { source, price, listings: 3 + ((seed + i * 7) % 24) }
  }).sort((a, b) => a.price - b.price)
}

function CardoraScannerPage() {
  const { locale } = useI18n()
  const isEnglish = locale === 'en'
  const fileInputRef = useRef(null)

  const [mode, setMode] = useState('search')
  const [query, setQuery] = useState('')
  const [imagePreview, setImagePreview] = useState(null)
  const [imageName, setImageName] = useState('')
  const [isSearching, setIsSearching] = useState(false)
  const [results, setResults] = useState(null)
  const [searchedTerm, setSearchedTerm] = useState('')

  useEffect(() => {
    document.title = 'Cardora Scanner'
  }, [])

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
        description: 'Search a card by name, or upload a photo — we\'ll estimate a market price from multiple sources.',
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
        suggested: 'Suggested average',
        listings: 'active listings',
        disclaimer: 'Mock estimate for preview — live scraping connects once the backend is wired up.',
      }
    : {
        eyebrow: 'Άμεσος έλεγχος τιμής',
        title: 'Cardora Scanner',
        description: 'Αναζήτησε μια κάρτα με το όνομά της, ή ανέβασε φωτογραφία — θα εκτιμήσουμε μια τιμή αγοράς από πολλαπλές πηγές.',
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
        suggested: 'Προτεινόμενος μέσος όρος',
        listings: 'ενεργές καταχωρήσεις',
        disclaimer: 'Mock εκτίμηση για preview — το πραγματικό scraping θα συνδεθεί με το backend.',
      }

  const runSearch = (term) => {
    if (!term.trim()) return
    setIsSearching(true)
    setResults(null)
    setSearchedTerm(term.trim())
    window.setTimeout(() => {
      setResults(fakeSearch(term.trim()))
      setIsSearching(false)
    }, 900)
  }

  const handleFile = (file) => {
    if (!file) return
    setImagePreview(URL.createObjectURL(file))
    setImageName(file.name)
    setResults(null)
  }

  const suggestedAverage =
    results && results.length ? results.reduce((sum, r) => sum + r.price, 0) / results.length : null

  return (
    <BinderShell>
      <div className="mb-8 max-w-2xl">
        <p className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#f3d385]/80">{copy.eyebrow}</p>
        <h2 className="mt-2 font-display text-3xl font-semibold text-white sm:text-4xl">{copy.title}</h2>
        <p className="mt-2 text-sm leading-6 text-white/55">{copy.description}</p>
      </div>

      <div className="mb-5 inline-flex rounded-2xl border border-white/10 bg-white/[0.04] p-1.5">
        <button
          type="button"
          onClick={() => setMode('search')}
          className={cn(
            'flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold transition',
            mode === 'search'
              ? 'bg-[linear-gradient(135deg,#3a2708_0%,#6b4a15_100%)] text-[#ffedc2] shadow-[0_6px_16px_rgba(120,80,20,0.35)]'
              : 'text-white/55 hover:text-white',
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
              ? 'bg-[linear-gradient(135deg,#3a2708_0%,#6b4a15_100%)] text-[#ffedc2] shadow-[0_6px_16px_rgba(120,80,20,0.35)]'
              : 'text-white/55 hover:text-white',
          )}
        >
          <Camera className="h-3.5 w-3.5" />
          {copy.uploadTab}
        </button>
      </div>

      <div className="rounded-[24px] border border-white/10 bg-white/[0.04] p-5 sm:p-6">
        {mode === 'search' ? (
          <form
            onSubmit={(event) => {
              event.preventDefault()
              runSearch(query)
            }}
            className="flex flex-col gap-3 sm:flex-row"
          >
            <div className="relative flex-1">
              <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-white/35" />
              <input
                type="text"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={copy.searchPlaceholder}
                className="w-full rounded-xl border border-white/10 bg-black/20 py-3 pl-10 pr-3 text-sm text-white placeholder:text-white/35 outline-none focus:border-[#f3d385]/40"
              />
            </div>
            <button
              type="submit"
              disabled={isSearching || !query.trim()}
              className="inline-flex items-center justify-center gap-2 rounded-xl border border-[#d7b57b]/70 bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_48%,#c79d62_100%)] px-5 py-3 text-sm font-semibold text-[#231508] shadow-[0_10px_26px_rgba(199,157,98,0.36)] transition hover:-translate-y-0.5 disabled:pointer-events-none disabled:opacity-50"
            >
              {isSearching ? <Loader2 className="h-4 w-4 animate-spin" /> : <ScanLine className="h-4 w-4" />}
              {copy.searchCta}
            </button>
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
                    className="h-36 w-28 rounded-[14px] border border-white/15 object-cover shadow-[0_10px_24px_rgba(0,0,0,0.35)]"
                  />
                  <button
                    type="button"
                    onClick={() => {
                      setImagePreview(null)
                      setImageName('')
                      setResults(null)
                      if (fileInputRef.current) fileInputRef.current.value = ''
                    }}
                    className="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full border border-white/20 bg-black/70 text-white/70 hover:text-white"
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                </div>
                <div className="flex-1 text-center sm:text-left">
                  <p className="truncate text-sm font-medium text-white/70">{imageName}</p>
                  <div className="mt-3 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                    <button
                      type="button"
                      onClick={() => runSearch(imageName.replace(/\.[a-z0-9]+$/i, '') || 'card')}
                      disabled={isSearching}
                      className="inline-flex items-center gap-2 rounded-xl border border-[#d7b57b]/70 bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_48%,#c79d62_100%)] px-4 py-2.5 text-sm font-semibold text-[#231508] shadow-[0_10px_26px_rgba(199,157,98,0.36)] transition hover:-translate-y-0.5 disabled:pointer-events-none disabled:opacity-50"
                    >
                      {isSearching ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />}
                      {copy.analyzeCta}
                    </button>
                    <button
                      type="button"
                      onClick={() => fileInputRef.current?.click()}
                      className="text-xs font-semibold text-white/50 underline decoration-dotted underline-offset-4 hover:text-white/80"
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
                className="flex w-full flex-col items-center gap-3 rounded-[18px] border-2 border-dashed border-white/15 py-12 text-white/50 transition hover:border-[#f3d385]/35 hover:text-white/75"
              >
                <ImagePlus className="h-8 w-8" />
                <span className="text-sm font-semibold text-white">{copy.dropTitle}</span>
                <span className="text-xs text-white/40">{copy.dropHint}</span>
              </button>
            )}
          </div>
        )}
      </div>

      {isSearching ? (
        <div className="mt-8 flex items-center gap-3 text-sm text-white/50">
          <Loader2 className="h-4 w-4 animate-spin text-[#f3d385]" />
          {copy.scanning}
        </div>
      ) : null}

      {results && !isSearching ? (
        <div className="mt-8">
          <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
              <h3 className="font-display text-xl font-semibold text-white">{copy.resultsTitle}</h3>
              <p className="text-xs text-white/45">
                {copy.resultsFor} <span className="font-semibold text-white/70">“{searchedTerm}”</span>
              </p>
            </div>
            {suggestedAverage !== null ? (
              <div className="flex items-center gap-2 rounded-xl border border-[#f3d385]/25 bg-[#f3d385]/10 px-4 py-2.5">
                <TrendingUp className="h-4 w-4 text-[#f3d385]" />
                <div>
                  <p className="text-[10px] font-semibold uppercase tracking-wide text-[#f3d385]/70">{copy.suggested}</p>
                  <p className="font-display text-lg font-bold text-[#f3d385]">€{suggestedAverage.toFixed(2)}</p>
                </div>
              </div>
            ) : null}
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            {results.map((r) => (
              <div
                key={r.source}
                className="flex items-center justify-between rounded-[16px] border border-white/10 bg-white/[0.04] px-4 py-3.5"
              >
                <div>
                  <p className="text-sm font-semibold text-white">{r.source}</p>
                  <p className="text-[11px] text-white/40">
                    {r.listings} {copy.listings}
                  </p>
                </div>
                <p className="font-display text-lg font-bold text-[#f3d385]">€{r.price.toFixed(2)}</p>
              </div>
            ))}
          </div>

          <p className="mt-4 text-[11px] text-white/30">{copy.disclaimer}</p>
        </div>
      ) : null}
    </BinderShell>
  )
}

export default CardoraScannerPage
