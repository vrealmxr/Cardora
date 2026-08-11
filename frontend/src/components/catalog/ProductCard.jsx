import { Gavel, Heart, ShoppingBag, Star } from 'lucide-react'
import { Link } from 'react-router-dom'
import ProductVisual from '@/components/catalog/ProductVisual'
import UserAvatar from '@/components/people/UserAvatar'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { useAuth } from '@/hooks/useAuth'
import { useMarketplace } from '@/hooks/useMarketplace'
import { formatCurrency } from '@/utils/formatters'
import { normalizeTextTree } from '@/utils/textEncoding'
import { cn, getCollectorProfileRoute, getUserDisplayName } from '@/utils/helpers'

function ProductCard({ product, className, renderFooter }) {
  const { currentUser } = useAuth()
  const { addToCart, favoriteProductIds, getMarketplaceGateMessage, marketplaceAccess, toggleFavorite } =
    useMarketplace()
  const isFavorite = favoriteProductIds.includes(product.id)
  const isAuction = product.saleFormat === 'auction'
  const isTrade = product.saleFormat === 'trade'
  const lot = product.lot
  const collectorRoute = getCollectorProfileRoute(product.seller?.handle ?? '')
  const isOwnListing = Number(product.sellerId ?? product.seller?.id ?? 0) === Number(currentUser?.id ?? 0)
  const buyBlocked = Boolean(currentUser && marketplaceAccess && !marketplaceAccess.can_buy)
  const buyBlockedMessage = getMarketplaceGateMessage('buy')
  const wholeLotUnavailable = Boolean(
    lot?.allowsIndividualPurchase && lot?.wholeLotPurchaseAvailable === false,
  )

  const ownListingLabel = 'Your listing'
  const copy = normalizeTextTree({
    auction: 'Auction',
    trade: 'Trade',
    lot: 'Card lot',
    cards: 'cards',
    hits: 'hits',
    more: 'more',
    currentBid: 'Current bid',
    price: 'Price',
    step: 'step',
    bid: 'Bid',
    tradeRequest: 'Trade request',
    cart: 'Add to cart',
    bids: 'bids',
    blocked: 'Complete activation',
    openLot: 'Open lot',
    individualPurchase: 'Single-card purchase enabled',
    individualShipping: 'Shipping + insurance start at €2.50',
    featured: 'Featured',
    availableCards: 'available cards',
  })

  const cartButtonLabel = wholeLotUnavailable
    ? copy.openLot
    : buyBlocked
      ? copy.blocked
      : isOwnListing
        ? ownListingLabel
        : copy.cart
  const cartButtonDisabled = buyBlocked || isOwnListing

  return (
    <CardSurface
      className={cn('group listing-hover-glow p-3.5', product.featured && 'featured-glow', className)}
    >
      <div className="relative">
        <Link to={`/proion/${product.slug}`} className="block">
          <ProductVisual product={product} className="min-h-[220px]" hoverSwap />
        </Link>
        <button
          type="button"
          onClick={(event) => {
            event.preventDefault()
            event.stopPropagation()
            void toggleFavorite(product.id)
          }}
          onPointerDown={(event) => {
            event.preventDefault()
            event.stopPropagation()
          }}
          className={cn(
            'absolute right-3 top-3 z-[6] flex h-9 w-9 items-center justify-center rounded-xl border p-2 backdrop-blur-sm transition',
            isFavorite
              ? 'border-gold-300/45 bg-gold-300/22 text-gold-100'
              : 'border-[#e4d2ab] bg-white/88 text-ink hover:border-gold-300/45 hover:bg-[#fff8ea] hover:text-gold-600',
          )}
          aria-label={isFavorite ? 'Remove from favorites' : 'Add to favorites'}
          title={isFavorite ? 'Remove from favorites' : 'Add to favorites'}
        >
          <Heart className={cn('h-4 w-4', isFavorite && 'fill-current')} />
        </button>
      </div>

      <div className="mt-4 space-y-3">
        <div className="flex flex-wrap items-center gap-2">
          <Badge tone="gold">{product.rarity}</Badge>
          <Badge tone={product.condition === 'Sealed' ? 'success' : 'muted'}>{product.condition}</Badge>
          {isAuction ? <Badge tone="warning">{copy.auction}</Badge> : null}
          {isTrade ? <Badge tone="info">{copy.trade}</Badge> : null}
          {lot ? <Badge tone="info">{copy.lot}</Badge> : null}
          {product.featured ? <Badge tone="warning">{copy.featured}</Badge> : null}
        </div>

        <div>
          <Link
            to={`/proion/${product.slug}`}
            className="text-balance text-[1.05rem] font-semibold text-ink transition group-hover:text-gold-600"
          >
            {product.title}
          </Link>
          <p className="mt-1 text-sm text-mist">{[product.subtitle, product.franchise].filter(Boolean).join(' • ')}</p>
        </div>

        {lot ? (
          <div className="rounded-2xl border border-[#eadab7] bg-white p-3">
            <div className="flex flex-wrap gap-2">
              <Badge tone="gold">{lot.totalCards} {copy.cards}</Badge>
              {lot.guaranteedHits ? <Badge tone="info">{lot.guaranteedHits} {copy.hits}</Badge> : null}
            </div>
            <div className="mt-2 flex flex-wrap gap-2">
              {lot.previewCards.slice(0, 3).map((item, index) => (
                <span key={`${item}-${index}`} className="rounded-full border border-[#eadab7] bg-white px-2.5 py-1 text-[11px] text-mist">
                  {item}
                </span>
              ))}
              {lot.overflowCount > 0 ? (
                <span className="rounded-full border border-gold-300/20 bg-gold-300/12 px-2.5 py-1 text-[11px] text-gold-700">
                  +{lot.overflowCount} {copy.more}
                </span>
              ) : null}
            </div>
            {lot.allowsIndividualPurchase ? (
              <div className="mt-3 rounded-xl border border-gold-300/18 bg-gold-300/10 px-3 py-2 text-[11px] leading-6 text-[#7a6440]">
                <p className="font-semibold text-gold-700">{copy.individualPurchase}</p>
                <p className="mt-1">
                  {lot.availableIndividualCardsCount} {copy.availableCards} • {copy.individualShipping}
                </p>
              </div>
            ) : null}
          </div>
        ) : null}

        <div className="flex items-end justify-between gap-3">
          <div>
            <p className="text-[11px] uppercase tracking-[0.26em] text-[#8d7a58]">
              {isAuction ? copy.currentBid : isTrade ? copy.trade : copy.price}
            </p>
            <p className="mt-1 text-xl font-semibold text-ink">{formatCurrency(product.price)}</p>
            {isAuction && product.auction ? (
              <p className="text-xs text-mist">
                {product.auction.bidCount} {copy.bids} • {copy.step} {formatCurrency(product.auction.bidIncrement)}
              </p>
            ) : product.oldPrice ? (
              <p className="text-sm text-[#9f9071] line-through">{formatCurrency(product.oldPrice)}</p>
            ) : null}
          </div>

          {isAuction ? (
            <Button as={Link} to={`/proion/${product.slug}`} size="sm" variant="secondary">
              <Gavel className="h-4 w-4" />
              {copy.bid}
            </Button>
          ) : isTrade ? (
            <Button as={Link} to={`/proion/${product.slug}`} size="sm" variant="secondary">
              <ShoppingBag className="h-4 w-4" />
              {copy.tradeRequest}
            </Button>
          ) : wholeLotUnavailable ? (
            <Button as={Link} to={`/proion/${product.slug}`} size="sm" variant="secondary">
              <ShoppingBag className="h-4 w-4" />
              {copy.openLot}
            </Button>
          ) : (
            <Button
              size="sm"
              onClick={() => addToCart(product.id)}
              disabled={cartButtonDisabled}
              title={buyBlocked ? buyBlockedMessage ?? '' : undefined}
            >
              <ShoppingBag className="h-4 w-4" />
              {cartButtonLabel}
            </Button>
          )}
        </div>

        {buyBlocked ? (
          <div className="rounded-xl border border-amber-400/20 bg-amber-500/10 px-3 py-2 text-xs leading-6 text-[#8b5f18]">
            {buyBlockedMessage}
          </div>
        ) : null}

        <div className="flex items-center justify-between gap-3 rounded-xl border border-[#eadab7] bg-white px-3 py-2.5">
          <Link to={collectorRoute} className="flex min-w-0 items-center gap-3">
            <UserAvatar user={product.seller} size="sm" />
            <div>
              <p className="text-sm font-semibold text-ink transition hover:text-gold-600">
                {getUserDisplayName(product.seller)}
              </p>
              <p className="text-xs text-mist">{product.seller?.city}</p>
            </div>
          </Link>
          <div className="flex items-center gap-1 text-sm text-gold-700">
            <Star className="h-4 w-4 fill-current" />
            {product.sellerRating}
          </div>
        </div>

        {renderFooter ? <div className="border-t border-[#eadab7] pt-3">{renderFooter(product)}</div> : null}
      </div>
    </CardSurface>
  )
}

export default ProductCard
