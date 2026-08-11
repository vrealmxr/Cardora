import { cn } from '@/utils/helpers'

function CardSurface({ className, hover = true, children }) {
  const hasFeaturedGlow = typeof className === 'string' && className.includes('featured-glow')
  const resolvedClassName =
    typeof className === 'string' ? className.replace(/\bfeatured-glow\b/g, '').trim() : className

  const innerClassName = cn(
    'surface-border premium-panel rounded-[24px] border border-[#ead7ae] bg-white p-5 shadow-glass backdrop-blur-md',
    hover && 'transition duration-200 hover:-translate-y-0.5',
    hover && !hasFeaturedGlow && 'hover:shadow-gold',
    resolvedClassName,
  )

  if (hasFeaturedGlow) {
    return (
      <div className="featured-glow h-full rounded-[24px]">
        <div className={cn(innerClassName, 'h-full')}>{children}</div>
      </div>
    )
  }

  return (
    <div className={innerClassName}>
      {children}
    </div>
  )
}

export default CardSurface
