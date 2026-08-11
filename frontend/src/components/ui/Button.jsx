import { cn } from '@/utils/helpers'

const variants = {
  primary:
    'border border-[#d7b57b]/70 bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_48%,#c79d62_100%)] text-[#231508] shadow-[0_10px_26px_rgba(199,157,98,0.36)] hover:-translate-y-0.5 hover:shadow-[0_14px_32px_rgba(199,157,98,0.42)]',
  secondary:
    'border border-[#d4af78]/55 bg-[linear-gradient(155deg,rgba(255,251,241,0.96)_0%,rgba(248,239,219,0.94)_74%)] text-[#5a3a13] hover:border-[#cfa55e] hover:bg-[linear-gradient(155deg,rgba(255,249,235,1)_0%,rgba(244,231,198,0.96)_74%)] hover:shadow-[0_12px_26px_rgba(199,157,98,0.16)]',
  ghost: 'text-slate-700 hover:bg-[#fff8e8] hover:text-[#5a3a13]',
  subtle:
    'border border-[#d9b980]/42 bg-[linear-gradient(155deg,rgba(255,249,237,0.96)_0%,rgba(248,238,214,0.92)_100%)] text-[#6b4718] hover:border-[#e7c99a]/68 hover:bg-[linear-gradient(155deg,rgba(255,247,229,1)_0%,rgba(243,229,193,0.95)_100%)]',
  danger:
    'border border-rose-300/60 bg-[linear-gradient(155deg,rgba(255,244,246,0.98)_0%,rgba(255,235,239,0.96)_100%)] text-rose-700 hover:border-rose-400/75 hover:bg-[linear-gradient(155deg,rgba(255,239,242,1)_0%,rgba(255,227,233,0.98)_100%)] hover:text-rose-800',
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
