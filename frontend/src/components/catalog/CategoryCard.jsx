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
          className={`relative flex h-full min-h-[240px] flex-col justify-between overflow-hidden rounded-[22px] border border-white/10 bg-gradient-to-br ${category.visual.gradient} p-5`}
        >
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.14),transparent_32%),radial-gradient(circle_at_bottom_left,rgba(243,202,87,0.2),transparent_38%)]" />

          <div className="relative flex items-start justify-between gap-3">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.35em] text-gold-100">
                Cardora Market
              </p>
              <h3 className="mt-2.5 font-display text-3xl text-white">{category.name}</h3>
            </div>
            <div className="rounded-full border border-white/10 bg-white/10 p-2.5 text-gold-100">
              <ArrowUpRight className="h-5 w-5" />
            </div>
          </div>

          <div className="relative space-y-3">
            <p className="max-w-sm text-sm leading-6 text-white/78">{resolvedDescription}</p>

            <div className="grid grid-cols-3 gap-1.5">
              <div className="min-w-0 rounded-xl border border-white/10 bg-black/10 px-2 py-2">
                <p className="min-h-[22px] break-words text-[8px] uppercase leading-3 tracking-[0.12em] text-white/55 sm:text-[9px]">
                  Listings
                </p>
                <p className="mt-1 text-[15px] font-semibold text-white">
                  {formatNumber(category.metrics.listings)}
                </p>
              </div>

              <div className="min-w-0 rounded-xl border border-white/10 bg-black/10 px-2 py-2">
                <p className="min-h-[22px] break-words text-[8px] uppercase leading-3 tracking-[0.12em] text-white/55 sm:text-[9px]">
                  Πωλήσεις
                </p>
                <p className="mt-1 text-[15px] font-semibold text-white">
                  {formatNumber(category.metrics.sold)}
                </p>
              </div>

              <div className="min-w-0 rounded-xl border border-white/10 bg-black/10 px-2 py-2">
                <p className="min-h-[22px] break-words text-[8px] uppercase leading-3 tracking-[0.12em] text-white/55 sm:text-[9px]">
                  Verified
                </p>
                <p className="mt-1 text-[15px] font-semibold text-white">
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
