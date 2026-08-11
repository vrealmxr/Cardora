import { cn } from '@/utils/helpers'

function StatTile({ icon: Icon, label, value, hint, accent = 'gold' }) {
  const accentClass =
    accent === 'gold'
      ? 'text-[#f3d385] bg-[#f3d385]/10 border-[#f3d385]/25'
      : 'text-emerald-300 bg-emerald-400/10 border-emerald-300/25'

  return (
    <div className="relative overflow-hidden rounded-[20px] border border-white/10 bg-white/[0.04] p-4 backdrop-blur-sm sm:p-5">
      <div className={cn('mb-3 inline-flex h-9 w-9 items-center justify-center rounded-xl border', accentClass)}>
        <Icon className="h-4.5 w-4.5" />
      </div>
      <p className="font-display text-2xl font-semibold text-white sm:text-3xl">{value}</p>
      <p className="mt-1 text-xs font-medium text-white/60">{label}</p>
      {hint ? <p className="mt-2 text-[11px] text-white/40">{hint}</p> : null}
    </div>
  )
}

export default StatTile
