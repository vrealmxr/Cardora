import { useI18n } from '@/hooks/useI18n'

const SANS = 'Helvetica Neue, Helvetica, Arial, sans-serif'

export function StripeLogo({ className = 'h-6' }) {
  return (
    <svg viewBox="0 0 76 30" className={className} role="img" aria-label="Stripe" xmlns="http://www.w3.org/2000/svg">
      <text x="0" y="23" fontFamily={SANS} fontSize="28" fontWeight="700" letterSpacing="-1.4" fill="#635bff">
        stripe
      </text>
    </svg>
  )
}

export function EbayLogo({ className = 'h-6' }) {
  return (
    <svg viewBox="0 0 64 30" className={className} role="img" aria-label="eBay" xmlns="http://www.w3.org/2000/svg">
      <text x="0" y="23" fontFamily={SANS} fontSize="28" fontWeight="700" letterSpacing="-1.2">
        <tspan fill="#e53238">e</tspan>
        <tspan fill="#0064d2">b</tspan>
        <tspan fill="#f5af02">a</tspan>
        <tspan fill="#86b817">y</tspan>
      </text>
    </svg>
  )
}

const COPY = {
  en: { payments: 'Payments by', prices: 'Price estimates from eBay data' },
  el: { payments: 'Πληρωμές μέσω', prices: 'Εκτιμήσεις τιμών με δεδομένα eBay' },
}

function PartnerLogos({ className = '', size = 'md' }) {
  const { locale } = useI18n()
  const copy = COPY[locale === 'en' ? 'en' : 'el']
  const logoClass = size === 'sm' ? 'h-5' : 'h-6'
  const labelClass =
    'text-[10px] font-semibold uppercase tracking-[0.2em] text-[#8d7a58] whitespace-nowrap'

  return (
    <div className={`flex flex-wrap items-center gap-x-6 gap-y-3 ${className}`}>
      <div className="flex items-center gap-2.5">
        <span className={labelClass}>{copy.payments}</span>
        <StripeLogo className={logoClass} />
      </div>
      <div className="flex items-center gap-2.5">
        <span className={labelClass}>{copy.prices}</span>
        <EbayLogo className={logoClass} />
      </div>
    </div>
  )
}

export default PartnerLogos
