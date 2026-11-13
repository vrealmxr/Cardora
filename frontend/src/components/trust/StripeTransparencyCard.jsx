import { marketplaceTrust } from '@/data/marketplaceTrust'
import { ArrowRightLeft, ShieldCheck, WalletCards } from 'lucide-react'
import { useI18n } from '@/hooks/useI18n'

function StripeWordmark() {
  return (
    <div className="inline-flex items-center rounded-full border border-[#635bff]/18 bg-white px-3 py-1.5 shadow-[0_12px_30px_rgba(99,91,255,0.08)]">
      <span className="text-sm font-semibold tracking-[-0.03em] text-[#635bff]">stripe</span>
    </div>
  )
}

function StripeTransparencyCard({ compact = false, className = '' }) {
  const { locale } = useI18n()

  const trust = marketplaceTrust[locale]
  const copy = {
    eyebrow: locale === 'en' ? 'Payments through Stripe' : 'Πληρωμές μέσω Stripe',
    title: trust.title,
    description: trust.payment,
    points: [WalletCards, ShieldCheck, ArrowRightLeft].map((icon, index) => ({ icon, label: trust.paymentPoints[index] })),
  }

  return (
    <div
      className={[
        'rounded-[24px] border border-[#e4d4b3] bg-[linear-gradient(135deg,rgba(255,255,255,0.98),rgba(252,247,236,0.96)_52%,rgba(241,225,188,0.42))] shadow-[0_18px_38px_rgba(180,151,92,0.1)]',
        compact ? 'p-4' : 'p-5',
        className,
      ].join(' ')}
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="inline-flex items-center gap-2 rounded-full border border-[#e6d9bd] bg-white/92 px-3 py-1.5 text-[11px] uppercase tracking-[0.24em] text-[#7a6440]">
          <ShieldCheck className="h-3.5 w-3.5 text-[#8f88ff]" />
          {copy.eyebrow}
        </div>
        <StripeWordmark />
      </div>

      <h3 className={`mt-4 font-semibold text-ink ${compact ? 'text-xl' : 'font-display text-3xl'}`}>
        {copy.title}
      </h3>
      <p className="mt-2 text-sm leading-7 text-mist">{copy.description}</p>

      <div className={`mt-4 grid gap-2.5 ${compact ? 'md:grid-cols-3' : 'sm:grid-cols-3'}`}>
        {copy.points.map((item) => {
          const Icon = item.icon
          return (
            <div key={item.label} className="rounded-[18px] border border-[#eadfc8] bg-white/88 px-3.5 py-3">
              <div className="flex items-center gap-2.5">
                <Icon className="h-4 w-4 text-[#a49fff]" />
                <p className="text-sm font-medium text-ink">{item.label}</p>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}

export default StripeTransparencyCard
