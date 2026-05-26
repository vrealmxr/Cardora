import { Copy, Eye, Pencil, Rocket, Trash2 } from 'lucide-react'
import { Link, useNavigate } from 'react-router-dom'
import ProductVisual from '@/components/catalog/ProductVisual'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { useI18n } from '@/hooks/useI18n'
import { formatCurrency, formatDate, formatShortDateTime } from '@/utils/formatters'
import { getStatusTone } from '@/utils/helpers'

function ListingCard({ listing, onMarkSold, onDelete, renderActions }) {
  const { locale } = useI18n()
  const navigate = useNavigate()
  const isAuction = listing.saleFormat === 'auction'
  const isTrade = listing.saleFormat === 'trade'
  const listingDatabaseId = listing.databaseId ?? listing.id

  const copy =
    locale === 'en'
      ? {
          auction: 'Auction',
          trade: 'Trade',
          lot: 'Card lot',
          updated: 'Updated on',
          cards: 'cards',
          guaranteedHits: 'guaranteed hits',
          currentBid: 'Current bid',
          price: 'Price',
          bids: 'Bids',
          views: 'Views',
          favorites: 'Favorites',
          ends: 'Ends',
          offers: 'Offers',
          status: 'Status',
          featured: 'Featured',
          tbd: 'TBD',
          view: 'View',
          edit: 'Edit',
          duplicate: 'Create similar',
          markSold: 'Mark as sold',
          delete: 'Delete',
        }
      : {
          auction: 'Δημοπρασία',
          trade: 'Trade',
          lot: 'Lot καρτών',
          updated: 'Ενημερώθηκε στις',
          cards: 'κάρτες',
          guaranteedHits: 'εγγυημένα hits',
          currentBid: 'Τρέχουσα προσφορά',
          price: 'Τιμή',
          bids: 'Προσφορές',
          views: 'Προβολές',
          favorites: 'Αγαπημένα',
          ends: 'Λήξη',
          offers: 'Προσφορές',
          status: 'Κατάσταση',
          featured: 'Προβεβλημένη',
          tbd: 'Σε εκκρεμότητα',
          view: 'Προβολή',
          edit: 'Επεξεργασία',
          duplicate: 'Δημιουργία παρόμοιας',
          markSold: 'Σήμανση ως πωλημένο',
          delete: 'Διαγραφή',
        }

  const hasMedia = Array.isArray(listing.media) && listing.media.some((item) => item?.url)

  const handleBuilderNavigation = (mode) => {
    if (!listingDatabaseId) return

    const target =
      mode === 'edit'
        ? `/dimiourgia-aggelias?edit=${listingDatabaseId}`
        : `/dimiourgia-aggelias?duplicate=${listingDatabaseId}`

    navigate(target)
  }

  const cardClassName = listing.featured ? 'featured-glow listing-hover-glow' : 'listing-hover-glow'

  return (
    <CardSurface className={cardClassName}>
      <div className="grid gap-5 md:grid-cols-[220px,1fr] md:items-start">
        {hasMedia ? (
          <ProductVisual
            product={{
              ...listing,
              category: listing.category ?? { name: listing.categoryName ?? listing.categoryId },
              lot: listing.lot ?? listing.lotSummary ?? null,
            }}
            className="h-[160px] min-h-[160px] md:h-[190px] md:min-h-[190px]"
          />
        ) : null}

        <div className="space-y-4">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <p className="text-xs uppercase tracking-[0.35em] text-gold-100">{listing.id}</p>
                {isAuction ? <Badge tone="warning">{copy.auction}</Badge> : null}
                {isTrade ? <Badge tone="info">{copy.trade}</Badge> : null}
                {listing.lotSummary ? <Badge tone="info">{copy.lot}</Badge> : null}
                {listing.featured ? <Badge tone="warning">{copy.featured}</Badge> : null}
              </div>
              <h3 className="mt-2 text-xl font-semibold text-white">{listing.title}</h3>
              <p className="mt-1 text-sm text-mist">
                {copy.updated} {formatDate(listing.updatedAt)}
              </p>
            </div>
            <Badge tone={getStatusTone(listing.status)}>{listing.status}</Badge>
          </div>

          {listing.lotSummary ? (
            <div className="rounded-2xl border border-white/8 bg-white/5 p-3.5 text-sm text-white/80">
              {listing.lotSummary.totalCards} {copy.cards} · {listing.lotSummary.guaranteedHits}{' '}
              {copy.guaranteedHits}
            </div>
          ) : null}

          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div className="rounded-2xl border border-white/8 bg-white/5 p-3">
              <p className="text-xs uppercase tracking-[0.3em] text-white/50">
                {isAuction ? copy.currentBid : isTrade ? copy.trade : copy.price}
              </p>
              <p className="mt-2 text-lg font-semibold text-white">{formatCurrency(listing.price)}</p>
            </div>
            <div className="rounded-2xl border border-white/8 bg-white/5 p-3">
              <p className="text-xs uppercase tracking-[0.3em] text-white/50">
                {isAuction ? copy.bids : isTrade ? copy.offers : copy.views}
              </p>
              <p className="mt-2 text-lg font-semibold text-white">
                {isAuction ? listing.bidCount ?? 0 : isTrade ? listing.offers : listing.views}
              </p>
            </div>
            <div className="rounded-2xl border border-white/8 bg-white/5 p-3">
              <p className="text-xs uppercase tracking-[0.3em] text-white/50">{copy.favorites}</p>
              <p className="mt-2 text-lg font-semibold text-white">{listing.saves}</p>
            </div>
            <div className="rounded-2xl border border-white/8 bg-white/5 p-3">
              <p className="text-xs uppercase tracking-[0.3em] text-white/50">
                {isAuction ? copy.ends : isTrade ? copy.status : copy.offers}
              </p>
              <p className="mt-2 text-lg font-semibold text-white">
                {isAuction && listing.auctionEndsAt
                  ? formatShortDateTime(listing.auctionEndsAt)
                  : isAuction
                    ? copy.tbd
                    : isTrade
                      ? listing.status
                      : listing.offers}
              </p>
            </div>
          </div>

          <div className="flex flex-wrap gap-3">
            {renderActions ? renderActions(listing) : null}
            <Button as={Link} to={`/proion/${listing.slug}`} variant="secondary" size="sm">
              <Eye className="h-4 w-4" />
              {copy.view}
            </Button>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => handleBuilderNavigation('edit')}
            >
              <Pencil className="h-4 w-4" />
              {copy.edit}
            </Button>
            <Button
              type="button"
              variant="secondary"
              size="sm"
              onClick={() => handleBuilderNavigation('duplicate')}
            >
              <Copy className="h-4 w-4" />
              {copy.duplicate}
            </Button>
            <Button variant="subtle" size="sm" onClick={() => onMarkSold?.(listing.id)}>
              <Rocket className="h-4 w-4" />
              {copy.markSold}
            </Button>
            <Button variant="danger" size="sm" onClick={() => onDelete?.(listing.id)}>
              <Trash2 className="h-4 w-4" />
              {copy.delete}
            </Button>
          </div>
        </div>
      </div>
    </CardSurface>
  )
}

export default ListingCard
