import { catalogValueLabel, distinctCatalogSeries } from '@/utils/catalogLabels'
import { useState } from 'react'
import Badge from '@/components/ui/Badge'
import { useI18n } from '@/hooks/useI18n'
import { cn } from '@/utils/helpers'
import { lazyListingImageProps } from '@/utils/mediaImages'

function ProductVisual({ product, className, hoverSwap = false }) {
  const { locale } = useI18n()
  const isAuction = product.saleFormat === 'auction'
  const isTrade = product.saleFormat === 'trade'
  const lot = product.lot
  const mediaItems = Array.isArray(product.media) ? product.media.filter((item) => item?.url) : []
  const leadMedia = mediaItems[0] ?? null
  const hoverMedia = hoverSwap && mediaItems.length > 1 ? mediaItems[1] : null
  const [requestedImage, setRequestedImage] = useState(null)
  const [readyImage, setReadyImage] = useState(null)
  const contextTags = [...new Set([catalogValueLabel('franchise', product.franchise, locale), distinctCatalogSeries(product), catalogValueLabel('typeLabel', product.typeLabel, locale)].filter(Boolean))]
    .slice(0, 3)
  const resolveCardImage = (media) => media?.thumbUrl ?? media?.previewUrl ?? media?.url ?? null
  const hoverImage = resolveCardImage(hoverMedia)
  const loadHoverImage = Boolean(hoverImage && requestedImage === hoverImage)
  const canSwapOnHover = loadHoverImage && readyImage === hoverImage

  const copy =
    locale === 'en'
      ? {
          auction: 'Auction',
          trade: 'Trade',
          lot: 'Card lot',
          listing: 'Listing',
        }
      : {
          auction: 'Δημοπρασία',
          trade: 'Ανταλλαγή',
          lot: 'Lot καρτών',
          listing: 'Αγγελία',
        }

  return (
    <div
      className={cn('relative h-full min-h-[200px] overflow-hidden rounded-[24px] border border-[#ead7ae] bg-white', className)}
      onPointerEnter={(event) => {
        if (hoverImage && event.pointerType !== 'touch') setRequestedImage(hoverImage)
      }}
    >
      {leadMedia ? (
        <>
          <img
            {...lazyListingImageProps(leadMedia)}
            alt=""
            aria-hidden="true"
            className={cn(
              'absolute inset-0 h-full w-full scale-110 object-cover blur-xl transition-opacity duration-300',
              canSwapOnHover ? 'opacity-30 group-hover:opacity-0' : 'opacity-30',
            )}
            loading="lazy"
            decoding="async"
          />

          {canSwapOnHover ? (
            <img
              {...lazyListingImageProps(hoverMedia)}
              alt=""
              aria-hidden="true"
              className="absolute inset-0 h-full w-full scale-110 object-cover opacity-0 blur-xl transition-opacity duration-300 group-hover:opacity-30"
              loading="lazy"
              decoding="async"
            />
          ) : null}

          <img
            {...lazyListingImageProps(leadMedia)}
            alt={product.title ?? leadMedia.alt ?? 'Product image'}
            className={cn(
              'relative z-[1] h-full w-full object-contain p-3 sm:p-4 transition-opacity duration-300',
              canSwapOnHover ? 'opacity-100 group-hover:opacity-0' : 'opacity-100',
            )}
            loading="lazy"
            decoding="async"
          />

          {loadHoverImage ? (
            <img
              {...lazyListingImageProps(hoverMedia)}
              alt=""
              aria-hidden="true"
              onLoad={() => setReadyImage(hoverImage)}
              onError={() => setReadyImage(null)}
              className={cn('pointer-events-none absolute inset-0 z-[1] h-full w-full object-contain p-3 opacity-0 transition-opacity duration-300 sm:p-4', canSwapOnHover && 'group-hover:opacity-100')}
              decoding="async"
            />
          ) : null}
        </>
      ) : (
        <div className="absolute inset-0 bg-[linear-gradient(180deg,#ffffff_0%,#f8f5ef_100%)]" />
      )}

      <div className="pointer-events-none absolute inset-0 z-[2] bg-[linear-gradient(180deg,rgba(255,255,255,0.02),rgba(255,255,255,0.14)_100%)]" />

      <div className="absolute left-3 right-3 top-3 z-[3] flex flex-wrap items-center gap-2">
        <Badge tone="gold" className="bg-[#fff8ea] text-gold-700 shadow-[0_8px_20px_rgba(15,23,42,0.06)]">
          {product.category?.name ?? copy.listing}
        </Badge>
        {isAuction ? <Badge tone="warning" className="bg-[#fff8eb] text-amber-700">{copy.auction}</Badge> : null}
        {isTrade ? <Badge tone="info" className="bg-[#fffaf2] text-[#6f5937]">{copy.trade}</Badge> : null}
        {lot ? <Badge tone="info" className="bg-[#fffaf2] text-[#6f5937]">{copy.lot}</Badge> : null}
        {product.visual?.label ? (
          <Badge
            tone="muted"
            className="bg-[rgba(255,255,255,0.97)] text-slate-700 shadow-[0_8px_20px_rgba(15,23,42,0.06)]"
          >
            {({ Listing: copy.listing, Auction: copy.auction, Trade: copy.trade, 'Card lot': copy.lot })[product.visual.label] ?? product.visual.label}
          </Badge>
        ) : null}
      </div>

      {contextTags.length > 0 ? (
        <div className="absolute bottom-3 left-3 right-3 z-[3] flex flex-wrap gap-2">
          {contextTags.map((tag, index) => (
            <span
              key={`${tag}-${index}`}
              className="rounded-full border border-[#e0c894] bg-[rgba(255,255,255,0.98)] px-3 py-1 text-[11px] font-semibold text-slate-700 shadow-[0_8px_20px_rgba(15,23,42,0.06)] backdrop-blur-sm"
            >
              {tag}
            </span>
          ))}
        </div>
      ) : null}
    </div>
  )
}

export default ProductVisual
