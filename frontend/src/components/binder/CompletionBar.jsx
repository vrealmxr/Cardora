import { cn } from '@/utils/helpers'

function CompletionBar({ percent = 0, className, trackClassName, label, size = 'md' }) {
  const clamped = Math.max(0, Math.min(100, percent))
  const height = size === 'sm' ? 'h-1.5' : 'h-2.5'

  return (
    <div className={cn('w-full', className)}>
      {label ? (
        <div className="mb-1.5 flex items-center justify-between text-[11px] font-semibold">
          <span className="text-white/70">{label}</span>
          <span className="text-[#f3d385]">{clamped}%</span>
        </div>
      ) : null}
      <div className={cn('w-full overflow-hidden rounded-full bg-white/10', height, trackClassName)}>
        <div
          className="h-full rounded-full bg-[linear-gradient(90deg,#c79d62_0%,#f3d385_50%,#ecd3a2_100%)] shadow-[0_0_10px_rgba(243,211,133,0.5)] transition-all duration-500"
          style={{ width: `${clamped}%` }}
        />
      </div>
    </div>
  )
}

export default CompletionBar
