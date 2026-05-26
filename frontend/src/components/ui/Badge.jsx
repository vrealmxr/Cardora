import { cn } from '@/utils/helpers'

const toneClasses = {
  gold: 'border-gold-300/25 bg-gold-300/12 text-gold-100',
  success: 'border-emerald-400/25 bg-emerald-400/12 text-emerald-100',
  warning: 'border-amber-400/25 bg-amber-400/12 text-amber-100',
  danger: 'border-rose-400/25 bg-rose-400/12 text-rose-100',
  info: 'border-sky-400/25 bg-sky-400/12 text-sky-100',
  muted: 'border-white/12 bg-white/6 text-white/70',
}

function Badge({ className, tone = 'gold', children }) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em]',
        toneClasses[tone],
        className,
      )}
    >
      {children}
    </span>
  )
}

export default Badge
