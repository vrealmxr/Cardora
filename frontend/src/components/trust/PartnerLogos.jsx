import { useI18n } from '@/hooks/useI18n'
import boxNowLogo from '@/assets/shipping/boxnow.svg'
import dhlLogo from '@/assets/shipping/dhl.svg'

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
  en: { payments: 'Payments by', prices: 'Price estimates from eBay data', shipping: 'Shipping with' },
  el: { payments: 'Πληρωμές μέσω', prices: 'Εκτιμήσεις τιμών με δεδομένα eBay', shipping: 'Αποστολές με' },
}

function PartnerLogos({ className = '', size = 'md' }) {
  const { locale } = useI18n()
  const copy = COPY[locale === 'en' ? 'en' : 'el']
  const logoClass = size === 'sm' ? 'h-5' : 'h-6'
  const labelClass =
    'text-center text-[10px] font-semibold uppercase tracking-[0.2em] text-[#8d7a58]'

  return (
    <div className={`flex flex-wrap items-center gap-x-6 gap-y-3 ${className}`}>
      <div className="flex max-w-full flex-wrap items-center justify-center gap-2.5">
        <span className={labelClass}>{copy.payments}</span>
        <StripeLogo className={logoClass} />
      </div>
      <div className="flex max-w-full flex-wrap items-center justify-center gap-2.5">
        <span className={labelClass}>{copy.prices}</span>
        <EbayLogo className={logoClass} />
      </div>
      <div className="flex flex-wrap items-center justify-center gap-x-3 gap-y-2">
        <span className={labelClass}>{copy.shipping}</span>
        <div className="flex shrink-0 items-center gap-4">
          <img src={boxNowLogo} alt="BOX NOW" width="139" height="102" className={size === 'sm' ? 'h-8 w-auto' : 'h-9 w-auto'} />
          <img src={dhlLogo} alt="DHL" width="143.5" height="20" className={size === 'sm' ? 'h-auto w-20' : 'h-auto w-24'} />
        </div>
      </div>
    </div>
  )
}

export default PartnerLogos
