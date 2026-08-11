import { Shirt, Sparkles, Trophy } from 'lucide-react'
import { cn } from '@/utils/helpers'

const THEME = {
  pokemon: {
    gradient: 'linear-gradient(150deg,#0b2116_0%,#173d27_32%,#3f8752_62%,#a8dd8f_100%)',
    icon: Sparkles,
  },
  basketball: {
    gradient: 'linear-gradient(150deg,#22100a_0%,#5a2a05_32%,#c2650f_62%,#ffc773_100%)',
    icon: Trophy,
  },
  football: {
    gradient: 'linear-gradient(150deg,#03150e_0%,#0b3323_32%,#137a4c_62%,#8be8b8_100%)',
    icon: Shirt,
  },
}

function PlaceholderSetArt({ category = 'pokemon', name, className }) {
  const theme = THEME[category] ?? THEME.pokemon
  const Icon = theme.icon

  return (
    <div
      className={cn('relative flex items-end overflow-hidden rounded-[20px]', className)}
      style={{ background: theme.gradient }}
    >
      <span className="pointer-events-none absolute -left-8 -top-10 h-40 w-40 rounded-full bg-white/10 blur-3xl" />
      <span className="pointer-events-none absolute -bottom-10 -right-6 h-32 w-32 rounded-full bg-black/20 blur-3xl" />
      <Icon
        className="pointer-events-none absolute right-3 top-3 h-8 w-8 text-white/30"
        strokeWidth={1.25}
      />
      <div className="relative grid w-full grid-cols-4 gap-1.5 p-3 opacity-90 sm:grid-cols-6">
        {Array.from({ length: 12 }).map((_, i) => (
          <span key={i} className="aspect-[3/4] rounded-[4px] bg-white/10 backdrop-blur-[1px]" />
        ))}
      </div>
      {name ? (
        <p className="absolute bottom-3 left-3 right-3 truncate font-display text-lg font-semibold text-white drop-shadow-[0_2px_6px_rgba(0,0,0,0.4)]">
          {name}
        </p>
      ) : null}
    </div>
  )
}

export default PlaceholderSetArt
