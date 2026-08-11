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
        'group relative flex flex-col overflow-hidden rounded-[18px] border p-2.5 transition duration-200',
        owned
          ? 'border-[#f3d385]/35 bg-white/[0.05] shadow-[0_10px_22px_rgba(0,0,0,0.28)]'
          : 'border-white/8 bg-white/[0.02] hover:border-white/15',
      )}
    >
      <button
        type="button"
        onClick={() => onToggleOwned(card.id)}
        className={cn(
          'absolute right-4 top-4 z-10 flex h-6 w-6 items-center justify-center rounded-full border transition',
          owned
            ? 'border-[#f3d385] bg-[#f3d385] text-[#231508] shadow-[0_4px_10px_rgba(243,211,133,0.5)]'
            : 'border-white/25 bg-black/30 text-transparent hover:border-white/50',
        )}
        aria-pressed={owned}
        aria-label={isEnglish ? 'Toggle owned' : 'Εναλλαγή κατοχής'}
      >
        <Check className="h-3.5 w-3.5" strokeWidth={3} />
      </button>

      <div className={cn('relative transition', !owned && 'opacity-55 grayscale-[0.35]')}>
        <PlaceholderCardArt category={card.category} name={card.name} number={card.number} rarity={card.rarity} />
      </div>

      <div className="mt-2.5 space-y-1">
        <div className="flex items-center justify-between gap-1.5">
          <span className="truncate text-[10.5px] text-white/45">{card.subtitle}</span>
          <span className="shrink-0 rounded-full border border-white/10 bg-white/5 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-white/55">
            {RARITY_LABEL[card.rarity]}
          </span>
        </div>

        <div className="flex items-center gap-1.5 pt-1">
          <Lock className="h-2.5 w-2.5 text-white/25" />
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
              className="w-16 rounded-md border border-[#f3d385]/40 bg-black/30 px-1.5 py-0.5 text-[11px] font-semibold text-[#f3d385] outline-none"
            />
          ) : (
            <button
              type="button"
              onClick={() => setEditingPrice(true)}
              className="text-[11px] font-semibold text-[#f3d385] underline decoration-dotted decoration-[#f3d385]/40 underline-offset-2 transition hover:decoration-[#f3d385]"
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
