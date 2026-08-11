import { Album, ArrowLeft, LayoutGrid, ScanLine } from 'lucide-react'
import { Link, useLocation } from 'react-router-dom'
import { useI18n } from '@/hooks/useI18n'
import { cn, localizePath } from '@/utils/helpers'

// Shared chrome for every Cardora Binder / Cardora Scanner page. Deliberately
// distinct from the light cream marketplace shell — deep navy + gold, same
// family the account-menu teaser already introduced — so this reads as its
// own product wearing the same brand, not just another marketplace page.
function BinderShell({ children }) {
  const { locale } = useI18n()
  const location = useLocation()
  const isEnglish = locale === 'en'

  const localized = (path) => localizePath(path, locale)

  const tabs = [
    { to: localized('/cardora-binder'), icon: LayoutGrid, label: isEnglish ? 'Dashboard' : 'Dashboard', end: true },
    { to: localized('/cardora-binder/library'), icon: Album, label: isEnglish ? 'My Binder' : 'Το Binder μου' },
    { to: localized('/cardora-scanner'), icon: ScanLine, label: 'Cardora Scanner' },
  ]

  const isActive = (tab) => {
    if (tab.end) return location.pathname === tab.to
    return location.pathname.startsWith(tab.to)
  }

  return (
    <div className="binder-shell relative min-h-screen overflow-x-hidden bg-[radial-gradient(120%_60%_at_50%_-10%,#1a2f52_0%,#0b1628_46%,#050c18_100%)] pb-24 pt-8 text-white">
      <div className="pointer-events-none absolute inset-x-0 top-0 h-[420px] bg-gold-radial opacity-60" />
      <div className="container relative">
        <Link
          to={localized('/')}
          className="mb-6 inline-flex items-center gap-2 text-xs font-medium text-white/50 transition hover:text-[#f3d385]"
        >
          <ArrowLeft className="h-3.5 w-3.5" />
          {isEnglish ? 'Back to Cardora Marketplace' : 'Πίσω στο Cardora Marketplace'}
        </Link>

        <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl border border-[#f3d385]/30 bg-[linear-gradient(135deg,#1c1204_0%,#3a2708_45%,#6b4a15_100%)] shadow-[0_10px_24px_rgba(120,80,20,0.35)]">
              <Album className="h-5 w-5 text-[#f3d385]" />
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-[0.32em] text-[#f3d385]/80">Cardora</p>
              <h1 className="font-display text-2xl font-semibold text-white sm:text-3xl">Binder</h1>
            </div>
          </div>

          <nav className="flex items-center gap-1.5 rounded-2xl border border-white/10 bg-white/[0.04] p-1.5 backdrop-blur-sm">
            {tabs.map((tab) => {
              const active = isActive(tab)
              return (
                <Link
                  key={tab.to}
                  to={tab.to}
                  className={cn(
                    'flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold transition sm:px-3.5',
                    active
                      ? 'bg-[linear-gradient(135deg,#3a2708_0%,#6b4a15_100%)] text-[#ffedc2] shadow-[0_6px_16px_rgba(120,80,20,0.35)]'
                      : 'text-white/60 hover:bg-white/5 hover:text-white',
                  )}
                >
                  <tab.icon className="h-3.5 w-3.5" />
                  <span className="hidden sm:inline">{tab.label}</span>
                </Link>
              )
            })}
          </nav>
        </div>

        {children}
      </div>
    </div>
  )
}

export default BinderShell
