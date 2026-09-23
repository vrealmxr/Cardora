import { forwardRef, useState } from 'react'
import { Eye, EyeOff } from 'lucide-react'
import { cn } from '@/utils/helpers'

const inputFieldClassName =
  'w-full rounded-xl border border-[#e8d6ae] bg-white px-3.5 py-2.5 text-[13px] text-slate-800 placeholder:text-slate-400 focus:border-gold-300 focus:bg-[#fffdf8] focus:outline-none focus:ring-0'

export const Input = forwardRef(function Input({ className, type, ...props }, ref) {
  const [revealed, setRevealed] = useState(false)

  if (type === 'password') {
    return (
      <div className="relative">
        <input
          ref={ref}
          type={revealed ? 'text' : 'password'}
          className={cn(inputFieldClassName, 'pr-10', className)}
          {...props}
        />
        <button
          type="button"
          onClick={() => setRevealed((prev) => !prev)}
          className="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 transition hover:text-slate-600"
          aria-label={revealed ? 'Απόκρυψη κωδικού' : 'Εμφάνιση κωδικού'}
          tabIndex={-1}
        >
          {revealed ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
        </button>
      </div>
    )
  }

  return <input ref={ref} type={type} className={cn(inputFieldClassName, className)} {...props} />
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
