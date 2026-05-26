import { cn } from '@/utils/helpers'

function SectionHeader({ eyebrow, title, description, action, align = 'left', className }) {
  return (
    <div
      className={cn(
        'mb-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between',
        align === 'center' && 'md:flex-col md:items-center md:text-center',
        className,
      )}
    >
      <div className={cn('max-w-3xl', align === 'center' && 'mx-auto')}>
        {eyebrow ? (
          <p className="mb-2 text-[10px] font-semibold uppercase tracking-[0.32em] text-[#e4c58a] [text-shadow:0_0_10px_rgba(228,197,138,0.18)]">
            {eyebrow}
          </p>
        ) : null}
        <h2 className="font-display text-3xl text-white sm:text-4xl">{title}</h2>
        {description ? <p className="mt-2.5 text-sm leading-7 text-mist sm:text-[15px]">{description}</p> : null}
      </div>
      {action}
    </div>
  )
}

export default SectionHeader
