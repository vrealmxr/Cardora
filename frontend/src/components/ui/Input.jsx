import { forwardRef } from 'react'
import { cn } from '@/utils/helpers'

export const Input = forwardRef(function Input({ className, ...props }, ref) {
  return (
    <input
      ref={ref}
      className={cn(
        'w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-2.5 text-[13px] text-white placeholder:text-mist focus:border-gold-300/50 focus:bg-white/8 focus:outline-none focus:ring-0',
        className,
      )}
      {...props}
    />
  )
})

export function Select({ className, children, ...props }) {
  return (
    <select
      className={cn(
        'w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-2.5 text-[13px] text-white focus:border-gold-300/50 focus:bg-white/8 focus:outline-none focus:ring-0',
        className,
      )}
      {...props}
    >
      {children}
    </select>
  )
}

export function Textarea({ className, ...props }) {
  return (
    <textarea
      className={cn(
        'min-h-[130px] w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-2.5 text-[13px] text-white placeholder:text-mist focus:border-gold-300/50 focus:bg-white/8 focus:outline-none focus:ring-0',
        className,
      )}
      {...props}
    />
  )
}
