import { Check, Minus, Plus } from 'lucide-react'
import { useState } from 'react'
import { cn } from '@/utils/helpers'

function RealCardTile({ card, owned, price, quantity, isPro, onToggleOwned, onPriceChange, onQuantityChange, isEnglish }) {
  const [editingPrice, setEditingPrice] = useState(false)

  return (
    <div
      className={cn(
        'group relative flex flex-col overflow-hidden rounded-lg border p-2.5 transition',
        owned ? 'border-[#c79d62] bg-white' : 'border-[#eee2c4] bg-white/60 hover:border-[#c79d62]/60',
      )}
    >
      <button
        type="button"
        onClick={() => onToggleOwned(card.id)}
        className={cn(
          'absolute right-4 top-4 z-10 flex h-6 w-6 items-center justify-center rounded-full border transition',
          owned
            ? 'border-[#c79d62] bg-[#c79d62] text-white'
            : 'border-[#dcd0b6] bg-white text-transparent hover:border-[#c79d62]',
        )}
        aria-pressed={owned}
        aria-label={isEnglish ? 'Toggle owned' : 'Εναλλαγή κατοχής'}
      >
        <Check className="h-3.5 w-3.5" strokeWidth={3} />
      </button>

      <div
        className={cn(
          'flex aspect-[5/7] w-full items-center justify-center overflow-hidden rounded-md bg-[#f3e9d2] transition',
          !owned && 'opacity-70 grayscale-[0.35]',
        )}
      >
        {card.imageUrl ? (
          <img src={card.imageUrl} alt={card.name} loading="lazy" className="h-full w-full object-contain" />
        ) : (
          <span className="px-2 text-center text-[10px] text-slate-400">{card.name}</span>
        )}
      </div>

      <div className="mt-2.5 space-y-1">
        <p className="truncate text-[11.5px] font-semibold text-ink" title={card.name}>
          {card.name}
        </p>
        <div className="flex items-center justify-between gap-1.5">
          <span className="truncate text-[10px] text-slate-400">{card.number}</span>
          {card.rarity ? (
            <span className="shrink-0 truncate rounded-full border border-[#eadab7] bg-[#fff8ec] px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-[#9d6a17]">
              {card.rarity}
            </span>
          ) : null}
        </div>

        {owned ? (
          editingPrice ? (
            <input
              type="number"
              step="0.01"
              min="0"
              autoFocus
              defaultValue={price ?? ''}
              onBlur={(event) => {
                const value = event.target.value.trim()
                onPriceChange(card.id, value === '' ? null : Number(value))
                setEditingPrice(false)
              }}
              onKeyDown={(event) => {
                if (event.key === 'Enter') event.currentTarget.blur()
              }}
              className="w-20 rounded-md border border-[#d8b06a] bg-white px-1.5 py-0.5 text-[11px] font-semibold text-[#6b4718] outline-none"
            />
          ) : (
            <button
              type="button"
              onClick={() => setEditingPrice(true)}
              className="text-[11px] font-semibold text-[#9d6a17] underline decoration-dotted decoration-[#c79d62]/50 underline-offset-2 transition hover:decoration-[#9d6a17]"
            >
              {price != null ? `€${Number(price).toFixed(2)}` : isEnglish ? 'Add price' : 'Πρόσθεσε τιμή'}
            </button>
          )
        ) : null}

        {owned && isPro && onQuantityChange ? (
          <div className="flex items-center gap-1.5 pt-0.5">
            <button
              type="button"
              onClick={() => onQuantityChange(card.id, Math.max(1, (quantity ?? 1) - 1))}
              disabled={(quantity ?? 1) <= 1}
              className="flex h-5 w-5 items-center justify-center rounded border border-[#eadab7] text-slate-500 transition hover:border-[#c79d62] disabled:opacity-30"
            >
              <Minus className="h-3 w-3" />
            </button>
            <span className="min-w-[1.2rem] text-center text-[10px] font-semibold text-slate-500">{quantity ?? 1}×</span>
            <button
              type="button"
              onClick={() => onQuantityChange(card.id, (quantity ?? 1) + 1)}
              className="flex h-5 w-5 items-center justify-center rounded border border-[#eadab7] text-slate-500 transition hover:border-[#c79d62]"
            >
              <Plus className="h-3 w-3" />
            </button>
          </div>
        ) : null}
      </div>
    </div>
  )
}

export default RealCardTile
