import { useLocation, useNavigate } from 'react-router-dom'
import { useI18n } from '@/hooks/useI18n'
import { cn, localizePath, stripLocaleFromPathname } from '@/utils/helpers'

function LanguageToggle({ compact = false, className }) {
  const { locale, setLocale, t } = useI18n()
  const location = useLocation()
  const navigate = useNavigate()

  const handleLocaleChange = (nextLocale) => {
    if (nextLocale === locale) return

    window.localStorage.setItem('cardora-locale', nextLocale)
    document.documentElement.lang = nextLocale
    setLocale(nextLocale)

    const targetPath = localizePath(stripLocaleFromPathname(location.pathname), nextLocale)
    navigate(`${targetPath}${location.search}${location.hash}`, { replace: true })
  }

  return (
    <div
      className={cn(
        'inline-flex items-center gap-1 rounded-full border border-[#d7b67f]/55 bg-white p-1 shadow-[0_8px_20px_rgba(15,23,42,0.05)]',
        compact ? 'text-[11px]' : 'text-xs',
        className,
      )}
      aria-label={t('common.language')}
    >
      {[
        { value: 'el', label: 'EL' },
        { value: 'en', label: 'EN' },
      ].map((option) => (
        <button
          key={option.value}
          type="button"
          onClick={() => handleLocaleChange(option.value)}
          aria-pressed={locale === option.value}
          aria-label={option.value === 'el' ? 'Switch language to Greek' : 'Switch language to English'}
          className={cn(
            'rounded-full px-2.5 py-1 font-semibold tracking-[0.16em] transition',
            locale === option.value
              ? 'border border-[#d8b980]/80 bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_52%,#c79d62_100%)] text-[#241608] shadow-[0_6px_16px_rgba(199,157,98,0.34)]'
              : 'text-[#6d7687] hover:text-[#8b6a2f]',
          )}
        >
          {option.label}
        </button>
      ))}
    </div>
  )
}

export default LanguageToggle
