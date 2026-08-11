import { cn } from '@/utils/helpers'

const toneClasses = {
  gold: 'border-gold-300/22 bg-gold-300/8 text-gold-700',
  success: 'border-emerald-400/25 bg-emerald-400/10 text-emerald-700',
  warning: 'border-amber-400/25 bg-amber-400/12 text-amber-700',
  danger: 'border-rose-400/25 bg-rose-400/10 text-rose-700',
  info: 'border-[#d9c7a1] bg-[#fbf7ef] text-[#7a6440]',
  muted: 'border-[#e8dcc1] bg-[#fffaf0] text-slate-600',
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
