import { ArrowLeft } from 'lucide-react'
import { Link, useLocation } from 'react-router-dom'
import { useI18n } from '@/hooks/useI18n'
import { cn, localizePath } from '@/utils/helpers'

// Shared chrome for every Cardora Binder / Cardora Scanner page. Quiet,
// editorial header — a wordmark and a row of text tabs, no icon badges.
function BinderShell({ children }) {
  const { locale } = useI18n()
  const location = useLocation()
  const isEnglish = locale === 'en'

  const localized = (path) => localizePath(path, locale)

  const tabs = [
    { to: localized('/cardora-binder'), label: 'Dashboard', end: true },
    { to: localized('/cardora-binder/library'), label: isEnglish ? 'My binder' : 'Το binder μου' },
    { to: localized('/cardora-binder/alerts'), label: isEnglish ? 'Alerts' : 'Ειδοποιήσεις' },
    { to: localized('/cardora-scanner'), label: 'Cardora Scanner' },
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
          className="mb-6 inline-flex items-center gap-2 text-xs font-medium text-slate-400 transition hover:text-[#6b4718]"
        >
          <ArrowLeft className="h-3.5 w-3.5" />
          {isEnglish ? 'Back to Cardora Marketplace' : 'Πίσω στο Cardora Marketplace'}
        </Link>

        <div className="mb-9 flex flex-col gap-5 border-b border-[#eee2c4] pb-5 sm:flex-row sm:items-end sm:justify-between">
          <h1 className="font-display text-[1.7rem] font-semibold leading-none text-ink sm:text-3xl">
            Cardora <span className="italic text-[#9d6a17]">Binder</span>
          </h1>

          <nav className="flex items-center gap-5 sm:gap-6">
            {tabs.map((tab) => {
              const active = isActive(tab)
              return (
                <Link
                  key={tab.to}
                  to={tab.to}
                  className={cn(
                    'border-b-2 pb-1 text-[13px] font-semibold transition',
                    active
                      ? 'border-[#c79d62] text-[#6b4718]'
                      : 'border-transparent text-slate-500 hover:border-[#eadab7] hover:text-[#6b4718]',
                  )}
                >
                  {tab.label}
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
