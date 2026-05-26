import { Link } from 'react-router-dom'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import EmptyState from '@/components/ui/EmptyState'
import SectionHeader from '@/components/ui/SectionHeader'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { formatCurrency } from '@/utils/formatters'
import { normalizeTextTree } from '@/utils/textEncoding'

const getMaxSelectableQuantity = (item) => {
  if (item.itemType === 'draw_entry') {
    const perUserLimit = Number(item.draw?.maxEntriesPerUser ?? 0)
    const remainingEntries = Number(item.draw?.entriesRemaining ?? item.draw?.targetEntries ?? 0)
    const hardLimit = perUserLimit > 0 ? perUserLimit : remainingEntries || 20

    return Math.max(Number(item.quantity ?? 1), Math.min(Math.max(hardLimit, 1), 20))
  }

  const stock = Number(item.product?.stock ?? item.quantity ?? 1)
  return Math.max(Number(item.quantity ?? 1), Math.min(Math.max(stock, 1), 20))
}

function CartPage() {
  const { currentUser } = useAuth()
  const { locale } = useI18n()
  const {
    cartDetailed,
    cartSummary,
    getMarketplaceGateMessage,
    marketplaceAccess,
    moveCartItemToFavorites,
    removeFromCart,
    updateCartQuantity,
  } = useMarketplace()

  const copy = normalizeTextTree(
    locale === 'en'
      ? {
          emptyTitle: 'Your cart is empty',
          emptyDescription:
            'Add collectibles or raffle entries and continue to checkout when you are ready.',
          keepBrowsing: 'Keep browsing',
          eyebrow: 'Cart',
          title: 'Ready for checkout',
          description:
            'Review the products and raffle entries in your cart before moving to the protected payment step.',
          qty: 'Qty',
          moveToFavorites: 'Move to favorites',
          remove: 'Remove',
          summary: 'Order summary',
          total: 'Total',
          totalDue: 'Final amount',
          noticePhysical:
            'The final amount already includes shipping and Cardora transaction protection, so both buyer and seller stay covered through to safe delivery and completion.',
          noticeDraw:
            'The final amount covers your entries and secure Cardora checkout, with your participation recorded safely in the system.',
          checkout: 'Continue to checkout',
          raffleTag: 'Raffle entry',
          raffleHostedBy: 'Hosted by',
          rafflePrize: 'Prize',
          pricePerEntry: 'per entry',
          lockedTitle: 'Marketplace access is still locked',
          verification: 'Open verification center',
          dashboard: 'Open seller dashboard',
        }
      : {
          emptyTitle: 'Το καλάθι είναι άδειο',
          emptyDescription:
            'Πρόσθεσε συλλεκτικά ή συμμετοχές σε κληρώσεις και προχώρησε στο checkout όταν είσαι έτοιμος.',
          keepBrowsing: 'Συνέχισε τις αγορές',
          eyebrow: 'Καλάθι',
          title: 'Έτοιμο για checkout',
          description:
            'Δες προϊόντα και συμμετοχές κληρώσεων στο καλάθι πριν περάσεις στο προστατευμένο βήμα πληρωμής.',
          qty: 'Τεμ.',
          moveToFavorites: 'Μεταφορά στα αγαπημένα',
          remove: 'Αφαίρεση',
          summary: 'Σύνοψη παραγγελίας',
          total: 'Σύνολο',
          totalDue: 'Τελικό πληρωτέο ποσό',
          noticePhysical:
            'Στο τελικό ποσό περιλαμβάνονται ήδη τα μεταφορικά και η προστασία συναλλαγής από την Cardora, ώστε να καλύπτονται τόσο ο αγοραστής όσο και ο πωλητής μέχρι την ασφαλή παραλαβή και ολοκλήρωση.',
          noticeDraw:
            'Το τελικό ποσό καλύπτει τις συμμετοχές σου και το ασφαλές checkout της Cardora, με την καταγραφή τους απευθείας στο σύστημα.',
          checkout: 'Συνέχεια στο checkout',
          raffleTag: 'Συμμετοχή σε κλήρωση',
          raffleHostedBy: 'Διοργανωτής',
          rafflePrize: 'Δώρο',
          pricePerEntry: 'ανά συμμετοχή',
          lockedTitle: 'Το marketplace παραμένει κλειδωμένο',
          verification: 'Άνοιγμα verification center',
          dashboard: 'Άνοιγμα seller dashboard',
          lotCardsTag: 'Επιλογή καρτών από lot',
          fromLot: 'Από το lot',
          shippingInsurance: 'Μεταφορικά + ασφάλιση',
          lotShippingRule:
            'Έως 10 κάρτες το κόστος μένει 2,50€. Από την 11η και μετά προστίθενται 0,25€ για κάθε επιπλέον κάρτα.',
        },
  )

  const ownCartItems = cartDetailed.filter((item) =>
    item.itemType === 'draw_entry'
      ? Number(item.draw?.hostUserId ?? item.draw?.host?.id ?? 0) === Number(currentUser?.id ?? 0)
      : Number(item.product?.sellerId ?? item.product?.seller?.id ?? 0) === Number(currentUser?.id ?? 0),
  )
  const hasOwnCartItems = ownCartItems.length > 0
  const ownItemsMessage =
    locale === 'en'
      ? 'Your cart includes your own listing or raffle. Remove those items before continuing to Stripe checkout.'
      : 'Στο καλάθι υπάρχουν δικά σου listings ή δική σου κλήρωση. Αφαίρεσέ τα πριν συνεχίσεις στο Stripe checkout.'
  const marketplaceBlockedMessage =
    currentUser && marketplaceAccess && !marketplaceAccess.can_buy
      ? getMarketplaceGateMessage('buy')
      : ''
  const checkoutBlockedMessage = hasOwnCartItems ? ownItemsMessage : marketplaceBlockedMessage
  const checkoutBlocked = Boolean(checkoutBlockedMessage)

  if (!cartDetailed.length) {
    return (
      <div className="container pb-16">
        <EmptyState
          title={copy.emptyTitle}
          description={copy.emptyDescription}
          actionLabel={copy.keepBrowsing}
          onAction={() => {}}
        />
      </div>
    )
  }

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />
      <div className="grid gap-8 xl:grid-cols-[1fr,360px]">
        <div className="space-y-5">
          {checkoutBlocked ? (
            <CardSurface className="border-amber-400/20 bg-amber-500/10">
              <p className="text-[11px] uppercase tracking-[0.28em] text-amber-100">{copy.lockedTitle}</p>
              <p className="mt-3 text-sm leading-7 text-amber-100">{checkoutBlockedMessage}</p>
              {marketplaceBlockedMessage ? (
                <div className="mt-4 flex flex-wrap gap-3">
                  <Button as={Link} to="/epalithefsi-logariasmou" variant="secondary" size="sm">
                    {copy.verification}
                  </Button>
                  <Button as={Link} to="/dashboard-politi" size="sm">
                    {copy.dashboard}
                  </Button>
                </div>
              ) : null}
            </CardSurface>
          ) : null}

          {cartDetailed.map((item) => {
            const quantityOptions = Array.from(
              { length: getMaxSelectableQuantity(item) },
              (_, index) => index + 1,
            )
            const isLotSelectionItem = item.itemMode === 'lot_individual_cards'

            return (
              <CardSurface key={item.id}>
                <div className="grid gap-4 lg:grid-cols-[1fr,auto]">
                  <div>
                    {item.itemType === 'draw_entry' ? (
                      <>
                        <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">
                          {copy.raffleTag}
                        </p>
                        <h3 className="mt-2 text-xl font-semibold text-white">{item.draw?.title}</h3>
                        <p className="mt-1 text-sm text-mist">
                          {copy.rafflePrize}: {item.draw?.prizeTitle}
                        </p>
                        <p className="mt-1 text-sm text-mist">
                          {copy.raffleHostedBy}: {item.draw?.hostedBy}
                        </p>
                        <p className="mt-4 text-lg font-semibold text-white">
                          {formatCurrency(item.unitPrice)} / {copy.pricePerEntry}
                        </p>
                      </>
                    ) : (
                      <>
                        <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">
                          {isLotSelectionItem ? copy.lotCardsTag : item.product?.typeLabel}
                        </p>
                        <h3 className="mt-2 text-xl font-semibold text-white">
                          {isLotSelectionItem ? item.displayTitle : item.product?.title}
                        </h3>
                        <p className="mt-1 text-sm text-mist">
                          {isLotSelectionItem
                            ? `${copy.fromLot}: ${item.product?.title}`
                            : [item.product?.subtitle, item.product?.franchise].filter(Boolean).join(' • ')}
                        </p>
                        <p className="mt-4 text-lg font-semibold text-white">
                          {formatCurrency(item.unitPrice)}
                        </p>
                        {isLotSelectionItem ? (
                          <>
                            <div className="mt-3 flex flex-wrap gap-2">
                              {item.selectedCards?.map((card) => (
                                <span
                                  key={card.id}
                                  className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] text-white/78"
                                >
                                  {card.title} • {formatCurrency(card.price)}
                                </span>
                              ))}
                            </div>
                            <div className="mt-3 rounded-xl border border-gold-300/15 bg-gold-300/10 px-4 py-3 text-sm leading-7 text-gold-50">
                              <p className="font-semibold text-white">
                                {copy.shippingInsurance}: {formatCurrency(item.shippingCost)}
                              </p>
                              <p className="mt-1">{copy.lotShippingRule}</p>
                            </div>
                          </>
                        ) : null}
                      </>
                    )}
                  </div>

                  <div className="flex flex-wrap items-center gap-3">
                    {isLotSelectionItem ? (
                      <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white">
                        {copy.qty} {item.selectedCardsCount}
                      </div>
                    ) : (
                      <select
                        value={item.quantity}
                        onChange={(event) => updateCartQuantity(item.id, Number(event.target.value))}
                        className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white"
                      >
                        {quantityOptions.map((value) => (
                          <option key={value} value={value}>
                            {copy.qty} {value}
                          </option>
                        ))}
                      </select>
                    )}

                    {item.itemType === 'listing' && !isLotSelectionItem ? (
                      <Button
                        variant="secondary"
                        size="sm"
                        onClick={() => moveCartItemToFavorites(item.id)}
                      >
                        {copy.moveToFavorites}
                      </Button>
                    ) : null}

                    <Button variant="danger" size="sm" onClick={() => removeFromCart(item.id)}>
                      {copy.remove}
                    </Button>
                  </div>
                </div>
              </CardSurface>
            )
          })}
        </div>

        <CardSurface className="h-fit">
          <h3 className="font-display text-3xl text-white">{copy.summary}</h3>

          <div className="mt-5 rounded-[24px] border border-white/10 bg-white/5 px-5 py-4">
            <p className="text-xs uppercase tracking-[0.28em] text-gold-100">{copy.totalDue}</p>
            <div className="mt-3 flex items-end justify-between gap-4">
              <span className="text-sm text-mist">{copy.total}</span>
              <span className="text-3xl font-semibold text-white">
                {formatCurrency(cartSummary.total)}
              </span>
            </div>
          </div>

          <div className="mt-5 rounded-[22px] border border-gold-300/20 bg-gold-300/10 p-4 text-sm leading-7 text-gold-50">
            {cartSummary.containsPhysicalItems ? copy.noticePhysical : copy.noticeDraw}
          </div>

          <div className="mt-5 flex flex-col gap-3">
            <Button as={Link} to="/checkout" className="w-full" disabled={checkoutBlocked}>
              {copy.checkout}
            </Button>
            <Button as={Link} to="/kartes" variant="secondary" className="w-full">
              {copy.keepBrowsing}
            </Button>
          </div>
        </CardSurface>
      </div>
    </div>
  )
}

export default CartPage
