import UserAvatar from '@/components/people/UserAvatar'
import Badge from '@/components/ui/Badge'
import CardSurface from '@/components/ui/CardSurface'
import { useI18n } from '@/hooks/useI18n'
import { formatCurrency, formatShortDateTime } from '@/utils/formatters'
import { getUserDisplayName } from '@/utils/helpers'

function ListingPreviewCard({ preview, seller, sticky = false }) {
  const { locale } = useI18n()
  const isAuction = preview.saleFormat === 'auction'
  const isTrade = preview.saleFormat === 'trade'
  const lot = preview.lot
  const mediaItems = Array.isArray(preview.media) ? preview.media.filter((item) => item?.url) : []
  const leadMedia = mediaItems[0] ?? null
  const lotOverflowCount = lot
    ? lot.overflowCount ?? Math.max((lot.totalCards ?? 0) - (lot.previewCards?.length ?? 0), 0)
    : 0

  const copy =
    locale === 'en'
      ? {
          auction: 'Auction',
          trade: 'Trade',
          lot: 'Card lot',
          preview: 'Preview',
          listing: 'Listing',
          placeholder: 'Your item will appear here as you complete the form.',
          acceptsOffers: 'Accepts offers',
          featured: 'Featured',
          startingBid: 'Starting bid',
          tradeValue: 'Declared trade value',
          finalPrice: 'Final price',
          winner: '1 winner',
          available: 'available',
          availableNow: 'Available now',
          reserve: 'Reserve',
          noReserve: 'No reserve',
          bidStep: 'Bid step',
          ends: 'Ends',
          notSet: 'Not set',
          cards: 'cards',
          hits: 'guaranteed hits',
          more: 'more',
          sellerNote:
            'Before a listing goes live, Cardora reviews the details and images so buyers see clear, reliable information from the start.',
        }
      : {
          auction: 'Δημοπρασία',
          trade: 'Trade',
          lot: 'Lot καρτών',
          preview: 'Προεπισκόπηση',
          listing: 'Αγγελία',
          placeholder: 'Το αντικείμενό σου θα εμφανιστεί εδώ καθώς συμπληρώνεις τη φόρμα.',
          acceptsOffers: 'Δέχεται προσφορές',
          featured: 'Προβεβλημένη',
          startingBid: 'Αρχική προσφορά',
          tradeValue: 'Δηλωμένη αξία trade',
          finalPrice: 'Τελική τιμή',
          winner: '1 νικητής',
          available: 'διαθέσιμα',
          availableNow: 'Άμεσα διαθέσιμο',
          reserve: 'Reserve',
          noReserve: 'Χωρίς reserve',
          bidStep: 'Βήμα προσφοράς',
          ends: 'Λήξη',
          notSet: 'Δεν έχει οριστεί',
          cards: 'κάρτες',
          hits: 'εγγυημένα hits',
          more: 'ακόμα',
          sellerNote:
            'Πριν εμφανιστεί δημόσια μια αγγελία, η Cardora ελέγχει τα στοιχεία και τις φωτογραφίες ώστε οι αγοραστές να βλέπουν καθαρή και αξιόπιστη παρουσίαση.',
        }

  const cardClassName = [
    sticky ? 'sticky top-[128px] self-start p-0' : 'p-0',
    preview.featured ? 'featured-glow' : null,
  ]
    .filter(Boolean)
    .join(' ')

  return (
    <CardSurface className={cardClassName}>
      <div className="relative min-h-[270px] overflow-hidden rounded-[24px] border-b border-white/8">
        {leadMedia ? (
          <>
            <img
              src={leadMedia.url}
              alt={leadMedia.alt ?? preview.title}
              className="absolute inset-0 h-full w-full scale-110 object-cover opacity-30 blur-xl"
              loading="lazy"
            />
            <img
              src={leadMedia.url}
              alt={leadMedia.alt ?? preview.title}
              className="absolute inset-0 z-[1] h-full w-full object-contain p-3 sm:p-4"
              loading="lazy"
            />
          </>
        ) : (
          <div className={`absolute inset-0 bg-gradient-to-br opacity-95 ${preview.visual.gradient}`} />
        )}
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.12),transparent_28%),radial-gradient(circle_at_bottom_left,rgba(243,202,87,0.18),transparent_32%)]" />
        <div className="relative z-[2] flex h-full flex-col justify-between p-5">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone="gold">{preview.categoryName}</Badge>
              {isAuction ? <Badge tone="warning">{copy.auction}</Badge> : null}
              {isTrade ? <Badge tone="info">{copy.trade}</Badge> : null}
              {lot ? <Badge tone="info">{copy.lot}</Badge> : null}
            </div>
            <span className="text-[10px] uppercase tracking-[0.3em] text-white/60">{copy.preview}</span>
          </div>

          <div className="space-y-3">
            <div className="rounded-[20px] border border-white/10 bg-black/10 p-4">
              <p className="text-xs uppercase tracking-[0.32em] text-white/55">{preview.typeLabel || copy.listing}</p>
              <h3 className="mt-3 text-balance font-display text-3xl text-white">{preview.title}</h3>
              <p className="mt-2 text-sm leading-6 text-white/70">
                {preview.subtitle || preview.franchise || copy.placeholder}
              </p>
            </div>
            <div className="flex flex-wrap gap-2">
              {preview.condition ? <Badge tone="muted">{preview.condition}</Badge> : null}
              {preview.rarity ? <Badge tone="gold">{preview.rarity}</Badge> : null}
              {preview.acceptOffers ? <Badge tone="info">{copy.acceptsOffers}</Badge> : null}
              {preview.featured ? <Badge tone="warning">{copy.featured}</Badge> : null}
            </div>
          </div>
        </div>
      </div>

      <div className="space-y-4 p-5">
        <div className="flex items-end justify-between gap-3">
          <div>
            <p className="text-[11px] uppercase tracking-[0.28em] text-white/45">
              {isAuction ? copy.startingBid : isTrade ? copy.tradeValue : copy.finalPrice}
            </p>
            <p className="mt-2 text-2xl font-semibold text-white">{formatCurrency(preview.price || 0)}</p>
          </div>
          <div className="text-right text-sm text-mist">
            <p>{isAuction ? copy.winner : `${preview.quantity || 1} ${copy.available}`}</p>
            <p>{preview.availability || copy.availableNow}</p>
          </div>
        </div>

        {isAuction && preview.auction ? (
          <div className="grid gap-3 sm:grid-cols-3">
            <div className="rounded-[18px] border border-white/8 bg-white/5 p-3">
              <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.reserve}</p>
              <p className="mt-2 text-sm font-semibold text-white">
                {preview.auction.reservePrice ? formatCurrency(preview.auction.reservePrice) : copy.noReserve}
              </p>
            </div>
            <div className="rounded-[18px] border border-white/8 bg-white/5 p-3">
              <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.bidStep}</p>
              <p className="mt-2 text-sm font-semibold text-white">{formatCurrency(preview.auction.bidIncrement || 0)}</p>
            </div>
            <div className="rounded-[18px] border border-white/8 bg-white/5 p-3">
              <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.ends}</p>
              <p className="mt-2 text-sm font-semibold text-white">
                {preview.auction.endsAt ? formatShortDateTime(preview.auction.endsAt) : copy.notSet}
              </p>
            </div>
          </div>
        ) : null}

        {lot ? (
          <div className="rounded-[20px] border border-white/8 bg-white/5 p-4">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone="gold">{lot.totalCards} {copy.cards}</Badge>
              {lot.guaranteedHits ? <Badge tone="info">{lot.guaranteedHits} {copy.hits}</Badge> : null}
            </div>
            <div className="mt-3 flex flex-wrap gap-2">
              {lot.previewCards.slice(0, 4).map((item) => (
                <Badge key={item} tone="muted">
                  {item}
                </Badge>
              ))}
              {lotOverflowCount > 0 ? <Badge tone="info">+{lotOverflowCount} {copy.more}</Badge> : null}
            </div>
            {lot.note ? <p className="mt-3 text-sm leading-7 text-mist">{lot.note}</p> : null}
          </div>
        ) : null}

        <div className="rounded-[20px] border border-white/8 bg-white/5 px-4 py-3.5">
          <div className="flex items-center gap-3">
            <UserAvatar user={seller} size="md" className="h-10 w-10 text-sm" />
            <div>
              <p className="text-sm font-semibold text-white">{getUserDisplayName(seller)}</p>
              <p className="text-xs text-mist">{seller?.city}</p>
            </div>
          </div>
        </div>

        <div className="rounded-[20px] border border-gold-300/15 bg-gold-300/10 px-4 py-3.5 text-sm leading-7 text-gold-50">
          {copy.sellerNote}
        </div>
      </div>
    </CardSurface>
  )
}

export default ListingPreviewCard
