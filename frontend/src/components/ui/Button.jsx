import { cn } from '@/utils/helpers'

const variants = {
  primary:
    'border border-[#d7b57b]/70 bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_48%,#c79d62_100%)] text-[#231508] shadow-[0_10px_26px_rgba(199,157,98,0.36)] hover:-translate-y-0.5 hover:shadow-[0_14px_32px_rgba(199,157,98,0.42)]',
  secondary:
    'border border-[#d4af78]/55 bg-[linear-gradient(155deg,rgba(228,197,138,0.16)_0%,rgba(14,27,48,0.9)_74%)] text-[#f6e7c5] hover:border-[#e7c99a]/75 hover:bg-[linear-gradient(155deg,rgba(228,197,138,0.24)_0%,rgba(16,31,54,0.93)_74%)] hover:shadow-[0_12px_26px_rgba(199,157,98,0.24)]',
  ghost: 'text-white/80 hover:bg-white/8 hover:text-white',
  subtle:
    'border border-[#d9b980]/42 bg-[linear-gradient(155deg,rgba(228,197,138,0.18)_0%,rgba(228,197,138,0.1)_38%,rgba(14,27,47,0.78)_100%)] text-[#f7ebcf] hover:border-[#e7c99a]/68 hover:bg-[linear-gradient(155deg,rgba(228,197,138,0.24)_0%,rgba(228,197,138,0.14)_38%,rgba(15,29,51,0.84)_100%)]',
  danger:
    'border border-rose-400/20 bg-rose-500/10 text-rose-100 hover:border-rose-400/40 hover:bg-rose-500/15',
}

function Button({
  className,
  variant = 'primary',
  size = 'md',
  as: Component = 'button',
  children,
  disabled = false,
  onClick,
  ...props
}) {
  const sizes = {
    sm: 'rounded-lg px-3 py-2 text-xs',
    md: 'rounded-xl px-4 py-2.5 text-sm',
    lg: 'rounded-xl px-5 py-3 text-sm',
  }

  const handleClick = (event) => {
    if (disabled) {
      event.preventDefault()
      event.stopPropagation()
      return
    }

    onClick?.(event)
  }

  return (
    <Component
      className={cn(
        'inline-flex items-center justify-center gap-2 font-semibold transition duration-200 disabled:cursor-not-allowed disabled:opacity-60',
        disabled && 'pointer-events-none',
        variants[variant],
        sizes[size],
        className,
      )}
      {...props}
      aria-disabled={disabled}
      onClick={handleClick}
      disabled={Component === 'button' ? disabled : undefined}
      tabIndex={disabled ? -1 : props.tabIndex}
    >
      {children}
    </Component>
  )
}

export default Button
