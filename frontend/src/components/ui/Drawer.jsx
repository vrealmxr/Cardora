import { useEffect } from 'react'
import { createPortal } from 'react-dom'
import { X } from 'lucide-react'
import { cn } from '@/utils/helpers'

function Drawer({ open, title, onClose, children, className }) {
  useEffect(() => {
    if (!open) return undefined

    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    return () => {
      document.body.style.overflow = previousOverflow
    }
  }, [open])

  if (typeof document === 'undefined') return null

  return createPortal(
    <div
      className={cn(
        'fixed inset-0 isolate z-[400] transition',
        open ? 'pointer-events-auto' : 'pointer-events-none',
      )}
    >
      <div
        className={cn(
          'absolute inset-0 bg-[rgba(28,24,17,0.18)] backdrop-blur-sm transition-opacity',
          open ? 'opacity-100' : 'opacity-0',
        )}
        onClick={onClose}
      />
      <div
        className={cn(
          'absolute right-0 top-0 h-full w-full max-w-sm transform border-l border-[#ead7ae] bg-[linear-gradient(180deg,#fffefb_0%,#fffdfa_100%)] p-5 shadow-[0_18px_48px_rgba(15,23,42,0.08)] transition duration-300',
          open ? 'translate-x-0' : 'translate-x-full',
          className,
        )}
      >
        <div className="mb-5 flex items-center justify-between">
          <h3 className="font-display text-2xl text-ink">{title}</h3>
          <button
            type="button"
            onClick={onClose}
            className="rounded-full border border-[#ead7ae] bg-white p-1.5 text-[#7a6440] transition hover:border-gold-300/40 hover:text-gold-700"
            aria-label="Close menu"
            title="Close menu"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        <div className="premium-scrollbar h-[calc(100%-64px)] overflow-y-auto pr-1.5">{children}</div>
      </div>
    </div>,
    document.body,
  )
}

export default Drawer
