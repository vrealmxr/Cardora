import Badge from '@/components/ui/Badge'
import { useI18n } from '@/hooks/useI18n'
import { cn } from '@/utils/helpers'

function ProductVisual({ product, className, hoverSwap = false }) {
  const { locale } = useI18n()
  const isAuction = product.saleFormat === 'auction'
  const isTrade = product.saleFormat === 'trade'
  const lot = product.lot
  const mediaItems = Array.isArray(product.media) ? product.media.filter((item) => item?.url) : []
  const leadMedia = mediaItems[0] ?? null
  const hoverMedia = hoverSwap && mediaItems.length > 1 ? mediaItems[1] : null
  const canSwapOnHover = Boolean(hoverMedia)
  const contextTags = [...new Set([product.franchise, product.series, product.typeLabel].filter(Boolean))]
    .slice(0, 3)

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
          trade: 'Trade',
          lot: 'Lot καρτών',
          listing: 'Αγγελία',
        }

  return (
    <div className={cn('relative h-full min-h-[200px] overflow-hidden rounded-[24px] border border-white/12 bg-[#071126]', className)}>
      {leadMedia ? (
        <>
          <img
            src={leadMedia.url}
            alt={leadMedia.alt ?? product.title ?? 'Product image'}
            className={cn(
              'absolute inset-0 h-full w-full scale-110 object-cover blur-xl transition-opacity duration-300',
              canSwapOnHover ? 'opacity-30 group-hover:opacity-0' : 'opacity-30',
            )}
            loading="lazy"
          />

          {canSwapOnHover ? (
            <img
              src={hoverMedia.url}
              alt={hoverMedia.alt ?? product.title ?? 'Product image'}
              className="absolute inset-0 h-full w-full scale-110 object-cover opacity-0 blur-xl transition-opacity duration-300 group-hover:opacity-30"
              loading="lazy"
            />
          ) : null}

          <img
            src={leadMedia.url}
            alt={leadMedia.alt ?? product.title ?? 'Product image'}
            className={cn(
              'relative z-[1] h-full w-full object-contain p-3 sm:p-4 transition-opacity duration-300',
              canSwapOnHover ? 'opacity-100 group-hover:opacity-0' : 'opacity-100',
            )}
            loading="lazy"
          />

          {canSwapOnHover ? (
            <img
              src={hoverMedia.url}
              alt={hoverMedia.alt ?? product.title ?? 'Product image'}
              className="pointer-events-none absolute inset-0 z-[1] h-full w-full object-contain p-3 opacity-0 transition-opacity duration-300 group-hover:opacity-100 sm:p-4"
              loading="lazy"
            />
          ) : null}
        </>
      ) : (
        <div className={cn('absolute inset-0 bg-gradient-to-br opacity-95', product.visual?.gradient ?? 'from-[#214b80] via-[#17243a] to-[#09111d]')} />
      )}

      <div className="pointer-events-none absolute inset-0 z-[2] bg-[linear-gradient(180deg,rgba(6,12,24,0.05),rgba(6,12,24,0.56)_100%)]" />

      <div className="absolute left-3 right-3 top-3 z-[3] flex flex-wrap items-center gap-2">
        <Badge tone="gold">{product.category?.name ?? copy.listing}</Badge>
        {isAuction ? <Badge tone="warning">{copy.auction}</Badge> : null}
        {isTrade ? <Badge tone="info">{copy.trade}</Badge> : null}
        {lot ? <Badge tone="info">{copy.lot}</Badge> : null}
        {product.visual?.label ? <Badge tone="muted">{product.visual.label}</Badge> : null}
      </div>

      {contextTags.length > 0 ? (
        <div className="absolute bottom-3 left-3 right-3 z-[3] flex flex-wrap gap-2">
          {contextTags.map((tag, index) => (
            <span key={`${tag}-${index}`} className="rounded-full border border-white/14 bg-[#0b162b]/80 px-3 py-1 text-[11px] font-medium text-white/88 backdrop-blur-sm">
              {tag}
            </span>
          ))}
        </div>
      ) : null}
    </div>
  )
}

export default ProductVisual
