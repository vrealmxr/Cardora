import { ArrowRightLeft, ShieldCheck, WalletCards } from 'lucide-react'
import { useI18n } from '@/hooks/useI18n'

function StripeWordmark() {
  return (
    <div className="inline-flex items-center rounded-full border border-[#635bff]/25 bg-white px-3 py-1.5 shadow-[0_12px_30px_rgba(99,91,255,0.14)]">
      <span className="text-sm font-semibold tracking-[-0.03em] text-[#635bff]">stripe</span>
    </div>
  )
}

function StripeTransparencyCard({ compact = false, className = '' }) {
  const { locale } = useI18n()

  const copy =
    locale === 'en'
      ? {
          eyebrow: 'Powered by Stripe',
          title: compact ? 'Transparent checkout and payouts' : 'Payments, holds and payouts run through Stripe',
          description: compact
            ? 'Buyer checkout, protected holds and seller payouts run through Stripe so each transaction stays clearer from payment to release.'
            : 'Cardora uses Stripe for buyer checkout, protected holds until confirmation and seller payouts through connected accounts, so the flow stays transparent at every stage.',
          points: [
            { icon: WalletCards, label: 'Stripe Checkout' },
            { icon: ShieldCheck, label: 'Protected hold until confirmation' },
            { icon: ArrowRightLeft, label: 'Seller payouts to connected accounts' },
          ],
        }
      : {
          eyebrow: 'Powered by Stripe',
          title: compact
            ? 'Διαφανές checkout και αποδεσμεύσεις'
            : 'Οι πληρωμές, τα holds και τα payouts περνούν μέσω Stripe',
          description: compact
            ? 'Το checkout του αγοραστή, το protected hold και οι αποδεσμεύσεις προς τον πωλητή περνούν μέσω Stripe ώστε κάθε συναλλαγή να μένει ξεκάθαρη από την πληρωμή μέχρι το release.'
            : 'Η Cardora χρησιμοποιεί Stripe για το checkout του αγοραστή, για την προστατευμένη κράτηση του ποσού μέχρι την επιβεβαίωση και για τα payouts προς τους πωλητές μέσω connected accounts, ώστε η ροή να παραμένει καθαρή και διαφανής σε κάθε στάδιο.',
          points: [
            { icon: WalletCards, label: 'Checkout μέσω Stripe' },
            { icon: ShieldCheck, label: 'Hold μέχρι επιβεβαίωση ή λήξη window' },
            { icon: ArrowRightLeft, label: 'Payouts σε Stripe connected accounts' },
          ],
        }

  return (
    <div
      className={[
        'rounded-[24px] border border-[#635bff]/20 bg-[linear-gradient(135deg,rgba(99,91,255,0.14),rgba(7,16,30,0.9)_58%,rgba(255,255,255,0.04))]',
        compact ? 'p-4' : 'p-5',
        className,
      ].join(' ')}
    >
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-[11px] uppercase tracking-[0.24em] text-[#d7d4ff]">
          <ShieldCheck className="h-3.5 w-3.5 text-[#8f88ff]" />
          {copy.eyebrow}
        </div>
        <StripeWordmark />
      </div>

      <h3 className={`mt-4 font-semibold text-white ${compact ? 'text-xl' : 'font-display text-3xl'}`}>
        {copy.title}
      </h3>
      <p className="mt-2 text-sm leading-7 text-mist">{copy.description}</p>

      <div className={`mt-4 grid gap-2.5 ${compact ? 'md:grid-cols-3' : 'sm:grid-cols-3'}`}>
        {copy.points.map((item) => {
          const Icon = item.icon
          return (
            <div key={item.label} className="rounded-[18px] border border-white/8 bg-white/5 px-3.5 py-3">
              <div className="flex items-center gap-2.5">
                <Icon className="h-4 w-4 text-[#a49fff]" />
                <p className="text-sm font-medium text-white">{item.label}</p>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}

export default StripeTransparencyCard
