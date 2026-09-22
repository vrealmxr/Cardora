import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { X } from 'lucide-react'
import Button from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'

// Asked every time a card gets checked off, so the portfolio stats (per-set
// value, total collection value) actually mean something. Skippable —
// tracking a price is optional, not tracking ownership.
function PriceEntryModal({ open, card, isEnglish, onCancel, onConfirm }) {
  const [price, setPrice] = useState('')

  useEffect(() => {
    if (open) setPrice('')
  }, [open, card?.id])

  useEffect(() => {
    if (!open) return undefined
    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      document.body.style.overflow = previousOverflow
    }
  }, [open])

  if (typeof document === 'undefined' || !open || !card) return null

  const copy = isEnglish
    ? {
        title: 'Add to your binder',
        label: 'What did you pay for it? (optional)',
        skip: 'Skip, just check it off',
        confirm: 'Save',
      }
    : {
        title: 'Πρόσθεσέ την στο binder σου',
        label: 'Πόσο την πλήρωσες; (προαιρετικό)',
        skip: 'Παράλειψη, μόνο τσέκαρέ την',
        confirm: 'Αποθήκευση',
      }

  const submit = (event) => {
    event.preventDefault()
    const value = price.trim() === '' ? null : Number(price)
    onConfirm(value !== null && Number.isFinite(value) && value >= 0 ? value : null)
  }

  return createPortal(
    <div className="fixed inset-0 z-[400] flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-[rgba(28,24,17,0.35)]" onClick={onCancel} />
      <form
        onSubmit={submit}
        className="relative w-full max-w-sm rounded-xl border border-[#ead7ae] bg-white p-5 shadow-[0_12px_32px_rgba(15,23,42,0.12)]"
      >
        <button
          type="button"
          onClick={onCancel}
          className="absolute right-4 top-4 text-slate-400 transition hover:text-[#6b4718]"
          aria-label="Close"
        >
          <X className="h-4 w-4" />
        </button>

        <div className="flex items-center gap-3">
          {card.imageUrl ? (
            <img src={card.imageUrl} alt={card.name} className="h-16 w-12 rounded-md object-cover" />
          ) : null}
          <div className="min-w-0">
            <p className="font-display text-lg font-semibold text-ink">{copy.title}</p>
            <p className="truncate text-xs text-slate-500">{card.name}</p>
          </div>
        </div>

        <label className="mt-4 block">
          <span className="mb-1.5 block text-xs font-semibold text-slate-600">{copy.label}</span>
          <div className="relative">
            <span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-slate-400">€</span>
            <Input
              type="number"
              step="0.01"
              min="0"
              autoFocus
              value={price}
              onChange={(event) => setPrice(event.target.value)}
              placeholder="0.00"
              className="pl-7"
            />
          </div>
        </label>

        <div className="mt-5 flex items-center justify-between gap-3">
          <button
            type="button"
            onClick={() => onConfirm(null)}
            className="text-xs font-semibold text-slate-500 underline decoration-dotted underline-offset-2 hover:text-[#6b4718]"
          >
            {copy.skip}
          </button>
          <Button type="submit" className="shrink-0">
            {copy.confirm}
          </Button>
        </div>
      </form>
    </div>,
    document.body,
  )
}

export default PriceEntryModal
