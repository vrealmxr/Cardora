import { ShieldCheck, Star } from 'lucide-react'
import { Link } from 'react-router-dom'
import UserAvatar from '@/components/people/UserAvatar'
import Badge from '@/components/ui/Badge'
import CardSurface from '@/components/ui/CardSurface'
import { useI18n } from '@/hooks/useI18n'
import { formatNumber } from '@/utils/formatters'
import { getCollectorProfileRoute, getUserDisplayName } from '@/utils/helpers'

function SellerCard({ seller, hover = true }) {
  const { locale } = useI18n()
  const collectorRoute = getCollectorProfileRoute(seller.handle)
  const copy =
    locale === 'en'
      ? {
          verified: 'Verified',
          rating: 'Rating',
          sales: 'Sales',
          viewCollection: 'View collection',
        }
      : {
          verified: 'Επιβεβαιωμένος',
          rating: 'Βαθμολογία',
          sales: 'Πωλήσεις',
          viewCollection: 'Δες τη συλλογή του',
        }

  return (
    <CardSurface hover={hover}>
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-3">
          <UserAvatar user={seller} size="md" />
          <div>
            <Link to={collectorRoute} className="text-base font-semibold text-white transition hover:text-gold-100">
              {getUserDisplayName(seller)}
            </Link>
            <p className="text-sm text-mist">{seller.city}</p>
          </div>
        </div>
        {seller.verified ? <Badge tone="success">{copy.verified}</Badge> : null}
      </div>
      <p className="mt-3 text-sm leading-6 text-mist">{seller.bio}</p>
      <div className="mt-4 grid grid-cols-2 gap-2.5">
        <div className="rounded-xl border border-white/8 bg-white/5 p-2.5">
          <p className="text-xs uppercase tracking-[0.3em] text-white/50">{copy.rating}</p>
          <p className="mt-1.5 flex items-center gap-2 text-base font-semibold text-white">
            <Star className="h-4 w-4 fill-current text-gold-200" />
            {seller.rating}
          </p>
        </div>
        <div className="rounded-xl border border-white/8 bg-white/5 p-2.5">
          <p className="text-xs uppercase tracking-[0.3em] text-white/50">{copy.sales}</p>
          <p className="mt-1.5 text-base font-semibold text-white">{formatNumber(seller.salesCount)}</p>
        </div>
      </div>
      <div className="mt-3.5 flex flex-wrap gap-2">
        {seller.specializations?.map((item) => (
          <Badge key={item} tone="gold">
            {item}
          </Badge>
        ))}
      </div>
      <div className="mt-3.5 flex items-center gap-2 text-sm text-gold-100">
        <ShieldCheck className="h-4 w-4" />
        {seller.responseTime}
      </div>
      <Link
        to={collectorRoute}
        className="mt-3 inline-flex text-sm font-semibold text-gold-100 transition hover:text-gold-50"
      >
        {copy.viewCollection}
      </Link>
    </CardSurface>
  )
}

export default SellerCard
