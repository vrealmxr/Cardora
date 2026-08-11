import { cn } from '@/utils/helpers'

const THEME = {
  pokemon: 'linear-gradient(150deg,#eef7ec_0%,#dcefda_55%,#c7e6c2_100%)',
  basketball: 'linear-gradient(150deg,#fbeee0_0%,#f5dcc0_55%,#eec495_100%)',
  football: 'linear-gradient(150deg,#e9f5ee_0%,#d3ecdd_55%,#b9e0cb_100%)',
}

function PlaceholderSetArt({ category = 'pokemon', name, className }) {
  const background = THEME[category] ?? THEME.pokemon

  return (
    <div
      className={cn('relative flex items-end overflow-hidden rounded-[18px] border border-[#eadab7]/70', className)}
      style={{ background }}
    >
      <div className="relative grid w-full grid-cols-4 gap-1.5 p-3 sm:grid-cols-6">
        {Array.from({ length: 12 }).map((_, i) => (
          <span key={i} className="aspect-[3/4] rounded-[4px] bg-white/35" />
        ))}
      </div>
      {name ? (
        <p className="absolute bottom-3 left-3 right-3 truncate font-display text-lg font-semibold text-[#4a3a1f]">
          {name}
        </p>
      ) : null}
    </div>
  )
}

export default PlaceholderSetArt
