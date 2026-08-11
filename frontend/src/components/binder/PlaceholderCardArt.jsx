import { cn } from '@/utils/helpers'

// Real card photography will come from CSV-linked assets later. Until then
// this is a plain, clearly-a-placeholder swatch — a soft category tint with
// the card number and name — not a fake "photo" pretending to be a scan.
const THEME = {
  pokemon: 'linear-gradient(160deg,#eef7ec_0%,#dcefda_100%)',
  basketball: 'linear-gradient(160deg,#fbeee0_0%,#f5dcc0_100%)',
  football: 'linear-gradient(160deg,#e9f5ee_0%,#d3ecdd_100%)',
}

function PlaceholderCardArt({ category = 'pokemon', name, number, className, compact = false }) {
  const background = THEME[category] ?? THEME.pokemon

  return (
    <div
      className={cn(
        'relative flex aspect-[3/4] w-full flex-col justify-between rounded-[14px] border border-[#eadab7]/70 p-2.5',
        className,
      )}
      style={{ background }}
    >
      {number ? (
        <span className="w-fit rounded-full bg-white/70 px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-slate-500">
          {number}
        </span>
      ) : null}

      <p
        className={cn(
          'truncate font-display font-semibold leading-tight text-[#4a3a1f]',
          compact ? 'text-[10px]' : 'text-sm',
        )}
      >
        {name}
      </p>
    </div>
  )
}

export default PlaceholderCardArt
