import { Shirt, Sparkles, Trophy } from 'lucide-react'
import { cn } from '@/utils/helpers'

// Real card/set photography will come from CSV-linked assets later. Until
// then this renders a deliberately-designed "foil" placeholder so the grid
// never looks broken — gradient + icon + name/number, themed per category.
const THEME = {
  pokemon: {
    gradient: 'linear-gradient(160deg,#0f2a1c_0%,#173d27_38%,#2f6b3f_72%,#7fbf6b_100%)',
    ring: 'rgba(127,191,107,0.5)',
    icon: Sparkles,
  },
  basketball: {
    gradient: 'linear-gradient(160deg,#2a1204_0%,#5a2a05_38%,#c2650f_72%,#f3ab4a_100%)',
    ring: 'rgba(243,171,74,0.5)',
    icon: Trophy,
  },
  football: {
    gradient: 'linear-gradient(160deg,#04180f_0%,#0b3323_38%,#137a4c_72%,#5fd39a_100%)',
    ring: 'rgba(95,211,154,0.5)',
    icon: Shirt,
  },
}

const RARITY_ACCENT = {
  holo: true,
  secret: true,
  auto: true,
}

function PlaceholderCardArt({ category = 'pokemon', name, number, rarity, className, compact = false }) {
  const theme = THEME[category] ?? THEME.pokemon
  const Icon = theme.icon
  const isFoil = RARITY_ACCENT[rarity]

  return (
    <div
      className={cn(
        'group/art relative flex aspect-[3/4] w-full flex-col justify-between overflow-hidden rounded-[16px] p-3 text-white shadow-[0_10px_24px_rgba(6,12,24,0.28)]',
        className,
      )}
      style={{ background: theme.gradient, boxShadow: `0 0 0 1px ${theme.ring} inset, 0 10px 24px rgba(6,12,24,0.3)` }}
    >
      {isFoil ? (
        <span className="pointer-events-none absolute inset-0 -translate-x-full bg-[linear-gradient(115deg,transparent_25%,rgba(255,255,255,0.5)_48%,transparent_70%)] opacity-70 transition-transform duration-700 ease-out group-hover/art:translate-x-full" />
      ) : null}
      <span className="pointer-events-none absolute -right-6 -top-6 h-24 w-24 rounded-full bg-white/10 blur-2xl" />

      <div className="relative flex items-start justify-between">
        <Icon className={cn('shrink-0 text-white/85', compact ? 'h-3.5 w-3.5' : 'h-4 w-4')} />
        {number ? (
          <span className="rounded-full bg-black/25 px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-white/90 backdrop-blur-sm">
            {number}
          </span>
        ) : null}
      </div>

      <div className="relative flex flex-1 items-center justify-center">
        <Icon className={cn('text-white/25', compact ? 'h-7 w-7' : 'h-10 w-10')} strokeWidth={1.25} />
      </div>

      <div className="relative">
        <p
          className={cn(
            'truncate font-display font-semibold leading-tight text-white/90',
            compact ? 'text-[10px]' : 'text-sm',
          )}
        >
          {name}
        </p>
      </div>
    </div>
  )
}

export default PlaceholderCardArt
