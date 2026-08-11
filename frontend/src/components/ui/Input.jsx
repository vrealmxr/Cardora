import { forwardRef } from 'react'
import { cn } from '@/utils/helpers'

export const Input = forwardRef(function Input({ className, ...props }, ref) {
  return (
    <input
      ref={ref}
      className={cn(
        'w-full rounded-xl border border-[#e8d6ae] bg-white px-3.5 py-2.5 text-[13px] text-slate-800 placeholder:text-slate-400 focus:border-gold-300 focus:bg-[#fffdf8] focus:outline-none focus:ring-0',
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
        'w-full rounded-xl border border-[#e8d6ae] bg-white px-3.5 py-2.5 text-[13px] text-slate-800 focus:border-gold-300 focus:bg-[#fffdf8] focus:outline-none focus:ring-0',
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
        'min-h-[130px] w-full rounded-xl border border-[#e8d6ae] bg-white px-3.5 py-2.5 text-[13px] text-slate-800 placeholder:text-slate-400 focus:border-gold-300 focus:bg-[#fffdf8] focus:outline-none focus:ring-0',
        className,
      )}
      {...props}
    />
  )
}
