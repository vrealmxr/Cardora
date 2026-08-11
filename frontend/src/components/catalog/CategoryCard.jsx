import { ArrowUpRight } from 'lucide-react'
import { Link } from 'react-router-dom'
import CardSurface from '@/components/ui/CardSurface'
import { useI18n } from '@/hooks/useI18n'
import { formatNumber } from '@/utils/formatters'

function CategoryCard({ category }) {
  const { locale } = useI18n()
  const categoryKey = String(category.id ?? category.slug ?? '').toLowerCase()
  const greekDescriptionOverrides = {
    cards: 'Pokémon, Yu-Gi-Oh!, Magic, One Piece και sports cards — με verified πωλητές και ασφαλή πληρωμή.',
    kartes: 'Pokémon, Yu-Gi-Oh!, Magic, One Piece και sports cards — με verified πωλητές και ασφαλή πληρωμή.',
    figures: 'Anime statues, Funko Pop και premium display pieces — με verified πωλητές και ασφαλή πληρωμή',
    figoures: 'Anime statues, Funko Pop και premium display pieces — με verified πωλητές και ασφαλή πληρωμή',
    comics: 'Κόμικς, manga, graphic novels και συλλεκτικές εκδόσεις — με verified πωλητές και ασφαλή πληρωμή',
    'komik-vivlia': 'Κόμικς, manga, graphic novels και συλλεκτικές εκδόσεις — με verified πωλητές και ασφαλή πληρωμή',
    misc: 'Για σπάνια συλλεκτικά που δεν ανήκουν σε μία μόνο κατηγορία',
    diafora: 'Για σπάνια συλλεκτικά που δεν ανήκουν σε μία μόνο κατηγορία',
  }
  const resolvedDescription =
    locale === 'en' ? category.description : greekDescriptionOverrides[categoryKey] ?? category.description

  return (
    <CardSurface className="h-full overflow-hidden p-0">
      <Link to={`/${category.slug}`} className="block h-full p-5">
        <div
          className="relative flex h-full min-h-[240px] flex-col justify-between overflow-hidden rounded-[22px] border border-[#eadab7] bg-[linear-gradient(135deg,rgba(255,255,255,0.96),rgba(249,240,220,0.9)_58%,rgba(232,205,150,0.32))] p-5"
        >
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.85),transparent_32%),radial-gradient(circle_at_bottom_left,rgba(243,202,87,0.24),transparent_38%)]" />

          <div className="relative flex items-start justify-between gap-3">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.35em] text-gold-700">
                Cardora Market
              </p>
              <h3 className="mt-2.5 font-display text-3xl text-ink">{category.name}</h3>
            </div>
            <div className="rounded-full border border-[#eadab7] bg-white/80 p-2.5 text-gold-700">
              <ArrowUpRight className="h-5 w-5" />
            </div>
          </div>

          <div className="relative space-y-3">
            <p className="max-w-sm text-sm leading-6 text-mist">{resolvedDescription}</p>

            <div className="grid grid-cols-3 gap-1.5">
              <div className="min-w-0 rounded-xl border border-[#eadab7] bg-white px-2 py-2">
                <p className="min-h-[22px] break-words text-[8px] uppercase leading-3 tracking-[0.12em] text-[#968565] sm:text-[9px]">
                  Listings
                </p>
                <p className="mt-1 text-[15px] font-semibold text-ink">
                  {formatNumber(category.metrics.listings)}
                </p>
              </div>

              <div className="min-w-0 rounded-xl border border-[#eadab7] bg-white px-2 py-2">
                <p className="min-h-[22px] break-words text-[8px] uppercase leading-3 tracking-[0.12em] text-[#968565] sm:text-[9px]">
                  Πωλήσεις
                </p>
                <p className="mt-1 text-[15px] font-semibold text-ink">
                  {formatNumber(category.metrics.sold)}
                </p>
              </div>

              <div className="min-w-0 rounded-xl border border-[#eadab7] bg-white px-2 py-2">
                <p className="min-h-[22px] break-words text-[8px] uppercase leading-3 tracking-[0.12em] text-[#968565] sm:text-[9px]">
                  Verified
                </p>
                <p className="mt-1 text-[15px] font-semibold text-ink">
                  {formatNumber(category.metrics.verified)}
                </p>
              </div>
            </div>
          </div>
        </div>
      </Link>
    </CardSurface>
  )
}

export default CategoryCard
