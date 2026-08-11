import { Album, ArrowLeft, LayoutGrid, ScanLine } from 'lucide-react'
import { Link, useLocation } from 'react-router-dom'
import { useI18n } from '@/hooks/useI18n'
import { cn, localizePath } from '@/utils/helpers'

// Shared chrome for every Cardora Binder / Cardora Scanner page. Same white
// + gold marketplace language as the rest of the site — the sub-app reads as
// distinct through its own header/tab-nav and focus, not a different palette.
function BinderShell({ children }) {
  const { locale } = useI18n()
  const location = useLocation()
  const isEnglish = locale === 'en'

  const localized = (path) => localizePath(path, locale)

  const tabs = [
    { to: localized('/cardora-binder'), icon: LayoutGrid, label: 'Dashboard', end: true },
    { to: localized('/cardora-binder/library'), icon: Album, label: isEnglish ? 'My Binder' : 'Το Binder μου' },
    { to: localized('/cardora-scanner'), icon: ScanLine, label: 'Cardora Scanner' },
  ]

  const isActive = (tab) => {
    if (tab.end) return location.pathname === tab.to
    return location.pathname.startsWith(tab.to)
  }

  return (
    <div className="min-h-screen bg-[#fffdfa] pb-24 pt-8">
      <div className="container">
        <Link
          to={localized('/')}
          className="mb-6 inline-flex items-center gap-2 text-xs font-medium text-slate-500 transition hover:text-[#6b4718]"
        >
          <ArrowLeft className="h-3.5 w-3.5" />
          {isEnglish ? 'Back to Cardora Marketplace' : 'Πίσω στο Cardora Marketplace'}
        </Link>

        <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl border border-[#d7b57b]/70 bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_48%,#c79d62_100%)] shadow-[0_10px_24px_rgba(199,157,98,0.3)]">
              <Album className="h-5 w-5 text-[#5a3a13]" />
            </div>
            <div>
              <p className="text-[10px] font-bold uppercase tracking-[0.32em] text-[#9d6a17]">Cardora</p>
              <h1 className="font-display text-2xl font-semibold text-ink sm:text-3xl">Binder</h1>
            </div>
          </div>

          <nav className="flex items-center gap-1.5 rounded-2xl border border-[#eadab7] bg-white p-1.5 shadow-[0_10px_22px_rgba(199,157,98,0.08)]">
            {tabs.map((tab) => {
              const active = isActive(tab)
              return (
                <Link
                  key={tab.to}
                  to={tab.to}
                  className={cn(
                    'flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold transition sm:px-3.5',
                    active
                      ? 'border border-[#d8b06a] bg-[linear-gradient(145deg,rgba(255,247,229,0.98)_0%,rgba(243,229,193,0.96)_100%)] text-[#6b4718] shadow-[0_8px_18px_rgba(199,157,98,0.16)]'
                      : 'border border-transparent text-slate-600 hover:border-[#eadab7] hover:bg-[#fff8ec] hover:text-[#6b4718]',
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
