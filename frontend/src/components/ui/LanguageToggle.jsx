import { useI18n } from '@/hooks/useI18n'
import { cn } from '@/utils/helpers'

function LanguageToggle({ compact = false, className }) {
  const { locale, setLocale, t } = useI18n()

  const handleLocaleChange = (nextLocale) => {
    if (nextLocale === locale) return

    if (typeof window === 'undefined') {
      setLocale(nextLocale)
      return
    }

    window.localStorage.setItem('cardora-locale', nextLocale)
    document.documentElement.lang = nextLocale
    window.location.reload()
  }

  return (
    <div
      className={cn(
        'inline-flex items-center gap-1 rounded-full border border-[#d7b67f]/45 bg-[linear-gradient(160deg,rgba(248,235,202,0.1)_0%,rgba(228,197,138,0.12)_24%,rgba(11,22,40,0.92)_72%)] p-1 shadow-[0_8px_20px_rgba(199,157,98,0.16)]',
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
          className={cn(
            'rounded-full px-2.5 py-1 font-semibold tracking-[0.16em] transition',
            locale === option.value
              ? 'border border-[#d8b980]/80 bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_52%,#c79d62_100%)] text-[#241608] shadow-[0_6px_16px_rgba(199,157,98,0.34)]'
              : 'text-[#e4c58f]/80 hover:text-[#f8ebcd]',
          )}
        >
          {option.label}
        </button>
      ))}
    </div>
  )
}

export default LanguageToggle
