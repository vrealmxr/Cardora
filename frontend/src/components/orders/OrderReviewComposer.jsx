import { Star } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import Button from '@/components/ui/Button'
import { Input, Textarea } from '@/components/ui/Input'
import { cn } from '@/utils/helpers'
import { normalizeTextTree } from '@/utils/textEncoding'

const DEFAULT_DRAFT = {
  rating: 5,
  title: '',
  body: '',
  isPublic: true,
}

function OrderReviewComposer({
  locale = 'el',
  role = 'buyer',
  initialReview = null,
  isBusy = false,
  isLoading = false,
  onSubmit,
  onCancel,
}) {
  const [draft, setDraft] = useState(DEFAULT_DRAFT)

  useEffect(() => {
    setDraft({
      rating: Number(initialReview?.rating ?? 5),
      title: initialReview?.title ?? '',
      body: initialReview?.body ?? '',
      isPublic: initialReview?.is_public ?? true,
    })
  }, [initialReview])

  const isEnglish = locale === 'en'
  const reviewTarget = role === 'seller'
    ? (isEnglish ? 'buyer' : 'αγοραστή')
    : (isEnglish ? 'seller' : 'πωλητή')

  const copy = useMemo(
    () =>
      normalizeTextTree(
        isEnglish
          ? {
              title: `Review the ${reviewTarget}`,
              description:
                'A short, honest review helps the next transaction feel safer and more transparent.',
              rating: 'Rating',
              titleField: 'Short title',
              bodyField: 'Your experience',
              titlePlaceholder: 'What stood out?',
              bodyPlaceholder: 'How did the order go from your side?',
              publicLabel: 'Show this review publicly on the profile',
              save: initialReview ? 'Save changes' : 'Publish review',
              cancel: 'Cancel',
              loading: 'Loading your review...',
            }
          : {
              title: `Αξιολόγησε τον ${reviewTarget}`,
              description:
                'Μια σύντομη και καθαρή αξιολόγηση βοηθά την επόμενη συναλλαγή να γίνει με περισσότερη σιγουριά και διαφάνεια.',
              rating: 'Βαθμολογία',
              titleField: 'Σύντομος τίτλος',
              bodyField: 'Η εμπειρία σου',
              titlePlaceholder: 'Τι ξεχώρισε;',
              bodyPlaceholder: 'Πώς κύλησε η παραγγελία από τη δική σου πλευρά;',
              publicLabel: 'Να φαίνεται δημόσια στο προφίλ',
              save: initialReview ? 'Αποθήκευση αλλαγών' : 'Δημοσίευση αξιολόγησης',
              cancel: 'Ακύρωση',
              loading: 'Φορτώνουμε την αξιολόγησή σου...',
            },
      ),
    [initialReview, isEnglish, reviewTarget],
  )

  const handleSubmit = (event) => {
    event.preventDefault()
    onSubmit?.(draft)
  }

  return (
    <div className="rounded-2xl border border-gold-300/20 bg-gold-300/10 p-4">
      <h4 className="text-lg font-semibold text-white">{copy.title}</h4>
      <p className="mt-2 text-sm leading-7 text-gold-50">{copy.description}</p>

      {isLoading ? (
        <div className="mt-4 rounded-xl border border-white/10 bg-[#0d1523] px-4 py-3 text-sm text-mist">
          {copy.loading}
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="mt-4 space-y-4">
          <div>
            <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.rating}</p>
            <div className="mt-3 flex flex-wrap gap-2">
              {[1, 2, 3, 4, 5].map((value) => (
                <button
                  key={value}
                  type="button"
                  onClick={() => setDraft((current) => ({ ...current, rating: value }))}
                  className={cn(
                    'inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold transition',
                    draft.rating === value
                      ? 'border-gold-300/30 bg-gold-300 text-slate-950'
                      : 'border-white/10 bg-white/5 text-white/80 hover:border-white/20',
                  )}
                >
                  <Star className={cn('h-4 w-4', draft.rating >= value ? 'fill-current' : '')} />
                  {value}
                </button>
              ))}
            </div>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <div>
              <p className="mb-2 text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.titleField}</p>
              <Input
                value={draft.title}
                onChange={(event) => setDraft((current) => ({ ...current, title: event.target.value }))}
                placeholder={copy.titlePlaceholder}
              />
            </div>

            <label className="flex items-end gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white/85">
              <input
                type="checkbox"
                checked={draft.isPublic}
                onChange={(event) =>
                  setDraft((current) => ({ ...current, isPublic: event.target.checked }))
                }
                className="mt-1 h-4 w-4 rounded border-white/20 bg-transparent text-gold-300 focus:ring-0"
              />
              <span>{copy.publicLabel}</span>
            </label>
          </div>

          <div>
            <p className="mb-2 text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.bodyField}</p>
            <Textarea
              value={draft.body}
              onChange={(event) => setDraft((current) => ({ ...current, body: event.target.value }))}
              placeholder={copy.bodyPlaceholder}
            />
          </div>

          <div className="flex flex-wrap gap-3">
            <Button type="submit" disabled={isBusy}>
              {copy.save}
            </Button>
            <Button type="button" variant="secondary" onClick={onCancel} disabled={isBusy}>
              {copy.cancel}
            </Button>
          </div>
        </form>
      )}
    </div>
  )
}

export default OrderReviewComposer
