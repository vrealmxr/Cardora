import { Check, Crown, Loader2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import BinderShell from '@/components/binder/BinderShell'
import Button from '@/components/ui/Button'
import { cardoraService } from '@/services/cardoraService'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { cn, localizePath } from '@/utils/helpers'

function BinderDuplicatesPage() {
  const { locale } = useI18n()
  const { currentUser, isAuthenticated } = useAuth()
  const isEnglish = locale === 'en'
  const localized = (path) => localizePath(path, locale)

  const [loading, setLoading] = useState(true)
  const [cards, setCards] = useState([])
  const [selection, setSelection] = useState({})
  const [prices, setPrices] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [result, setResult] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    document.title = 'Cardora Binder — Bulk list duplicates'
  }, [])

  useEffect(() => {
    if (!isAuthenticated || !currentUser?.isPro) {
      setLoading(false)
      return
    }
    cardoraService
      .fetchBinderDuplicates()
      .then((data) => setCards(data ?? []))
      .catch(() => setCards([]))
      .finally(() => setLoading(false))
  }, [isAuthenticated, currentUser?.isPro])

  const copy = isEnglish
    ? {
        title: 'Bulk-list your duplicates',
        description: 'Every card you own more than one copy of. Pick the ones to list, set a price, and create draft listings in one go.',
        empty: 'No duplicates yet — raise the quantity on an owned card in a set to add it here.',
        priceLabel: 'Price (€)',
        submit: 'Create listings',
        submitting: 'Creating listings…',
        success: 'listings created — find them under "My listings" to review and publish.',
        proOnlyTitle: 'Cardora PRO feature',
        proOnlyDesc: 'Bulk-listing your duplicates is available on Cardora PRO.',
        upgradeCta: 'Upgrade to PRO',
        signInTitle: 'Sign in to bulk-list duplicates',
        signInCta: 'Sign in',
        qty: 'copies',
      }
    : {
        title: 'Bulk-listing των duplicates σου',
        description: 'Κάθε κάρτα που κατέχεις σε περισσότερα από ένα αντίτυπα. Διάλεξε ποιες θες να καταχωρήσεις, βάλε τιμή, και δημιούργησε draft αγγελίες με ένα κλικ.',
        empty: 'Δεν έχεις duplicates ακόμα — ανέβασε την ποσότητα σε μια κάρτα που κατέχεις σε ένα σετ για να εμφανιστεί εδώ.',
        priceLabel: 'Τιμή (€)',
        submit: 'Δημιουργία αγγελιών',
        submitting: 'Δημιουργία αγγελιών…',
        success: 'αγγελίες δημιουργήθηκαν — βρες τις στις "Οι αγγελίες μου" για έλεγχο και δημοσίευση.',
        proOnlyTitle: 'Λειτουργία Cardora PRO',
        proOnlyDesc: 'Το bulk-listing των duplicates είναι διαθέσιμο στο Cardora PRO.',
        upgradeCta: 'Αναβάθμιση σε PRO',
        signInTitle: 'Συνδέσου για bulk-listing duplicates',
        signInCta: 'Σύνδεση',
        qty: 'αντίτυπα',
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

  if (!currentUser?.isPro) {
    return (
      <BinderShell>
        <div className="rounded-xl border border-dashed border-[#eadab7] p-12 text-center">
          <Crown className="mx-auto h-8 w-8 text-[#9d6a17]" />
          <p className="mt-3 font-display text-xl font-semibold text-ink">{copy.proOnlyTitle}</p>
          <p className="mx-auto mt-2 max-w-md text-sm text-mist">{copy.proOnlyDesc}</p>
          <Button as={Link} to={localized('/cardora-pro')} className="mt-5">
            {copy.upgradeCta}
          </Button>
        </div>
      </BinderShell>
    )
  }

  const toggleSelect = (cardId) => {
    setSelection((prev) => ({ ...prev, [cardId]: !prev[cardId] }))
  }

  const handleSubmit = async () => {
    const items = cards
      .filter((card) => selection[card.cardId])
      .map((card) => ({
        card_id: card.cardId,
        price: Number(prices[card.cardId] ?? card.purchasePrice ?? 0),
      }))
      .filter((item) => item.price > 0)

    if (!items.length) return

    setSubmitting(true)
    setError(null)
    try {
      const res = await cardoraService.bulkListBinderDuplicates(items)
      setResult(res?.createdListingIds?.length ?? 0)
      setSelection({})
    } catch (err) {
      setError(err?.message || 'Error')
    } finally {
      setSubmitting(false)
    }
  }

  const selectedCount = Object.values(selection).filter(Boolean).length

  return (
    <BinderShell>
      <div className="mb-8 max-w-2xl">
        <h2 className="font-display text-3xl font-semibold text-ink sm:text-4xl">{copy.title}</h2>
        <p className="mt-2 text-sm leading-6 text-mist">{copy.description}</p>
      </div>

      {result !== null ? (
        <div className="mb-6 rounded-xl border border-emerald-300/40 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
          {result} {copy.success}
        </div>
      ) : null}
      {error ? <div className="mb-6 rounded-xl border border-rose-300/40 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</div> : null}

      {loading ? (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="h-40 animate-pulse rounded-xl border border-[#eee2c4] bg-[#fdf8ee]" />
          ))}
        </div>
      ) : cards.length ? (
        <>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
            {cards.map((card) => {
              const selected = Boolean(selection[card.cardId])
              return (
                <div
                  key={card.cardId}
                  className={cn(
                    'flex flex-col overflow-hidden rounded-lg border p-2.5 transition',
                    selected ? 'border-[#c79d62] bg-white' : 'border-[#eee2c4] bg-white/60',
                  )}
                >
                  <button
                    type="button"
                    onClick={() => toggleSelect(card.cardId)}
                    className="relative flex aspect-[5/7] w-full items-center justify-center overflow-hidden rounded-md bg-[#f3e9d2]"
                  >
                    {card.imageUrl ? (
                      <img src={card.imageUrl} alt={card.name} className="h-full w-full object-contain" />
                    ) : (
                      <span className="px-2 text-center text-[10px] text-slate-400">{card.name}</span>
                    )}
                    <span
                      className={cn(
                        'absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full border',
                        selected ? 'border-[#c79d62] bg-[#c79d62] text-white' : 'border-[#dcd0b6] bg-white text-transparent',
                      )}
                    >
                      <Check className="h-3.5 w-3.5" strokeWidth={3} />
                    </span>
                  </button>
                  <p className="mt-2 truncate text-[11px] font-semibold text-ink">{card.name}</p>
                  <p className="text-[10px] text-slate-400">
                    {card.setName} · {card.quantity} {copy.qty}
                  </p>
                  {selected ? (
                    <div className="relative mt-1.5">
                      <span className="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-[10px] text-slate-400">€</span>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder={String(card.purchasePrice ?? '')}
                        value={prices[card.cardId] ?? ''}
                        onChange={(event) => setPrices((prev) => ({ ...prev, [card.cardId]: event.target.value }))}
                        className="w-full rounded-md border border-[#d8b06a] bg-white py-1 pl-5 pr-1.5 text-[11px] font-semibold text-[#6b4718] outline-none"
                      />
                    </div>
                  ) : null}
                </div>
              )
            })}
          </div>

          <div className="mt-6">
            <Button onClick={handleSubmit} disabled={submitting || !selectedCount}>
              {submitting ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
              {submitting ? copy.submitting : `${copy.submit}${selectedCount ? ` (${selectedCount})` : ''}`}
            </Button>
          </div>
        </>
      ) : (
        <div className="rounded-xl border border-dashed border-[#eadab7] p-12 text-center">
          <p className="mx-auto max-w-md text-sm text-mist">{copy.empty}</p>
        </div>
      )}
    </BinderShell>
  )
}

export default BinderDuplicatesPage
