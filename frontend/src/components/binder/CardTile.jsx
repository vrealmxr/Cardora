import { Check, Lock } from 'lucide-react'
import { useState } from 'react'
import PlaceholderCardArt from '@/components/binder/PlaceholderCardArt'
import { cn } from '@/utils/helpers'

const RARITY_LABEL = {
  common: 'Common',
  uncommon: 'Uncommon',
  rare: 'Rare',
  holo: 'Holo Rare',
  rookie: 'Rookie',
  insert: 'Insert',
  auto: 'Auto',
  secret: 'Secret Rare',
}

function CardTile({ card, owned, onToggleOwned, price, onPriceChange, isEnglish }) {
  const [editingPrice, setEditingPrice] = useState(false)

  return (
    <div
      className={cn(
        'group relative flex flex-col overflow-hidden rounded-[16px] border p-2.5 transition duration-200',
        owned ? 'border-[#d8b06a] bg-white shadow-glass' : 'border-[#eadab7] bg-white/60 hover:border-[#d8b06a]/60',
      )}
    >
      <button
        type="button"
        onClick={() => onToggleOwned(card.id)}
        className={cn(
          'absolute right-4 top-4 z-10 flex h-6 w-6 items-center justify-center rounded-full border transition',
          owned
            ? 'border-[#c79d62] bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_48%,#c79d62_100%)] text-[#231508] shadow-[0_4px_10px_rgba(199,157,98,0.4)]'
            : 'border-[#dcd0b6] bg-white text-transparent hover:border-[#c79d62]',
        )}
        aria-pressed={owned}
        aria-label={isEnglish ? 'Toggle owned' : 'Εναλλαγή κατοχής'}
      >
        <Check className="h-3.5 w-3.5" strokeWidth={3} />
      </button>

      <div className={cn('transition', !owned && 'opacity-60 grayscale-[0.4]')}>
        <PlaceholderCardArt category={card.category} name={card.name} number={card.number} rarity={card.rarity} />
      </div>

      <div className="mt-2.5 space-y-1">
        <div className="flex items-center justify-between gap-1.5">
          <span className="truncate text-[10.5px] text-slate-500">{card.subtitle}</span>
          <span className="shrink-0 rounded-full border border-[#eadab7] bg-[#fff8ec] px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-[#9d6a17]">
            {RARITY_LABEL[card.rarity]}
          </span>
        </div>

        <div className="flex items-center gap-1.5 pt-1">
          <Lock className="h-2.5 w-2.5 text-slate-300" />
          {editingPrice ? (
            <input
              type="number"
              step="0.01"
              min="0"
              autoFocus
              defaultValue={price}
              onBlur={(event) => {
                onPriceChange(card.id, Number(event.target.value) || 0)
                setEditingPrice(false)
              }}
              onKeyDown={(event) => {
                if (event.key === 'Enter') event.currentTarget.blur()
              }}
              className="w-16 rounded-md border border-[#d8b06a] bg-white px-1.5 py-0.5 text-[11px] font-semibold text-[#6b4718] outline-none"
            />
          ) : (
            <button
              type="button"
              onClick={() => setEditingPrice(true)}
              className="text-[11px] font-semibold text-[#9d6a17] underline decoration-dotted decoration-[#c79d62]/50 underline-offset-2 transition hover:decoration-[#9d6a17]"
              title={isEnglish ? 'Private price — only visible to you' : 'Ιδιωτική τιμή — ορατή μόνο σε εσένα'}
            >
              €{Number(price ?? 0).toFixed(2)}
            </button>
          )}
        </div>
      </div>
    </div>
  )
}

export default CardTile
