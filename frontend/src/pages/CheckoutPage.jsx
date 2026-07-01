import { Home, Package, ShieldCheck, Ticket } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import PickupPointPicker from '@/components/checkout/PickupPointPicker'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input, Select } from '@/components/ui/Input'
import SectionHeader from '@/components/ui/SectionHeader'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { formatCurrency } from '@/utils/formatters'
import { normalizeTextTree } from '@/utils/textEncoding'

const MARKETPLACE_FEE_RATE = Number(import.meta.env.VITE_MARKETPLACE_BUYER_FEE_RATE ?? 0.00)
const roundMoney = (value) => Math.round(Number(value ?? 0) * 100) / 100

function CheckoutPage() {
  const { currentUser, isAuthReady } = useAuth()
  const { locale } = useI18n()
  const {
    cartDetailed,
    cartSummary,
    cancelPendingOrder,
    conversations,
    getMarketplaceGateMessage,
    marketplaceAccess,
    placeOrder,
    productsWithSellers,
  } = useMarketplace()
  const [paymentMethod, setPaymentMethod] = useState('Stripe Checkout')
  const [submitError, setSubmitError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [deliveryType, setDeliveryType] = useState('home')
  const [selectedPoint, setSelectedPoint] = useState(null)
  const [searchParams] = useSearchParams()
  const acceptedOfferId = Number(searchParams.get('offer') ?? 0) || null
  const acceptedOffer = acceptedOfferId
    ? conversations
        .flatMap((conversation) => (Array.isArray(conversation.offers) ? conversation.offers : []))
        .find((offer) => Number(offer.id) === acceptedOfferId) ?? null
    : null
  const acceptedOfferConversation = acceptedOffer
    ? conversations.find((conversation) => Number(conversation.id) === Number(acceptedOffer.conversationId))
    : null
  const acceptedOfferProduct = acceptedOffer
    ? productsWithSellers.find(
        (item) =>
          Number(item.id) ===
          Number(acceptedOfferConversation?.productId ?? acceptedOfferConversation?.listing_id ?? acceptedOffer.listingId),
      ) ?? null
    : null
  const isPrivateOfferCheckout = Boolean(acceptedOffer)
  const requiresShipping = isPrivateOfferCheckout ? true : cartSummary.containsPhysicalItems
  const checkoutCarrier = isPrivateOfferCheckout
    ? acceptedOfferProduct?.deliveryCarrier ?? null
    : cartDetailed.find((item) => item.itemType === 'listing')?.product?.deliveryCarrier ?? null
  const carrierSupportsPickup = requiresShipping && Boolean(checkoutCarrier)
  const isPickupDelivery = carrierSupportsPickup && deliveryType === 'pickup'
  const checkoutCancelled = searchParams.get('cancelled') === '1'
  const cancelledOrderId = searchParams.get('order_id')
  const cancelledCleanupRef = useRef(false)
  const ownCartItems = (isPrivateOfferCheckout ? [] : cartDetailed).filter((item) =>
    item.itemType === 'draw_entry'
      ? Number(item.draw?.hostUserId ?? item.draw?.host?.id ?? 0) === Number(currentUser?.id ?? 0)
      : Number(item.product?.sellerId ?? item.product?.seller?.id ?? 0) === Number(currentUser?.id ?? 0),
  )
  const hasOwnCartItems = ownCartItems.length > 0
  const ownItemsMessage =
    locale === 'en'
      ? 'Your cart includes your own listing or raffle. Remove those items or sign in with a different buyer account to test Stripe checkout.'
      : 'Στο καλάθι υπάρχουν δικά σου listings ή δική σου κλήρωση. Αφαίρεσέ τα ή μπες με διαφορετικό buyer account για να δοκιμάσεις το Stripe checkout.'
  const marketplaceBlockedMessage =
    currentUser && marketplaceAccess && !marketplaceAccess.can_buy
      ? getMarketplaceGateMessage('buy')
      : ''
  const invalidOfferMessage =
    acceptedOfferId && !acceptedOffer
      ? locale === 'en'
        ? 'This private offer is no longer available for checkout.'
        : 'Αυτή η προσωπική προσφορά δεν είναι πλέον διαθέσιμη για checkout.'
      : ''
  const checkoutBlockedMessage = invalidOfferMessage || (hasOwnCartItems ? ownItemsMessage : marketplaceBlockedMessage)
  const checkoutBlocked = Boolean(checkoutBlockedMessage)

  const copy = normalizeTextTree(
    locale === 'en'
      ? {
          eyebrow: 'Checkout',
          title: 'Protected checkout',
          description:
            'Complete the order through Stripe Checkout, with the seller funds released only after delivery confirmation or the auto-release window.',
          shippingTitle: 'Shipping details',
          noShippingTitle: 'No shipping details needed',
          noShippingText:
            'This checkout contains raffle entries only, so payment can continue without a shipping address.',
          name: 'Full name',
          address: 'Address',
          phone: 'Phone',
          city: 'City',
          postalCode: 'Postal code',
          country: 'Country',
          deliveryMethod: 'Delivery method',
          homeDelivery: 'Home delivery',
          pickupPoint: checkoutCarrier === 'boxnow' ? 'BoxNow locker' : 'DHL service point',
          pickupRequired: 'Please select a pickup point to continue.',
          paymentMethod: 'Payment method',
          paymentMethods: ['Stripe Checkout'],
          paymentNotice:
            'Payment stays protected until the order completes correctly, with coverage for both buyer and seller.',
          privateOfferNotice:
            'This checkout uses a private agreed amount between buyer and seller. The public listing price does not change for anyone else.',
          submit: 'Continue to Stripe',
          summary: 'Order summary',
          total: 'Total',
          totalDue: 'Final amount',
          privateOfferSummary: 'Private offer summary',
          privateOfferTag: 'Private buyer-seller amount',
          agreedAmount: 'Agreed total',
          cleanItemValue: 'Clean item value',
          includedShipping: 'Included shipping',
          commissionTitle: 'Cardora seller fee policy',
          commissionText:
            'Seller fee is calculated on clean item value only: €1 for €0.01-€5, 6.5% for €5.01-€300, 5% for €300.01-€2,000 and 4% above €2,000, with a maximum cap of €400. Included shipping and extra costs stay outside this fee base.',
          buyerFeeLabel: 'Buyer fee',
          sellerFeeLabel: 'Seller fee (tiered)',
          paysThisExactAmount: 'The buyer pays the agreed total plus any buyer-side fee shown below.',
          includedPhysical:
            'Shipping and Cardora transaction protection are already included in the final amount, so both buyer and seller stay covered through to delivery and completion.',
          includedDraw:
            'The final amount covers your entries and secure Cardora checkout, with your participation recorded safely in the system.',
          qty: 'Qty',
          raffleTag: 'Raffle entry',
          pricePerEntry: 'per entry',
          cancelledMessage: 'Stripe checkout was cancelled before payment completed.',
          missingUrl: 'Stripe checkout URL was not returned.',
          lockedTitle: 'Marketplace access is still locked',
          verification: 'Open verification center',
          dashboard: 'Open seller dashboard',
        }
      : {
          eyebrow: 'Checkout',
          title: 'Προστατευμένο checkout',
          description:
            'Ολοκλήρωσε την παραγγελία μέσω Stripe Checkout, με αποδέσμευση προς τον πωλητή μόνο μετά την επιβεβαίωση παραλαβής ή τη λήξη του χρονικού παραθύρου.',
          shippingTitle: 'Στοιχεία αποστολής',
          noShippingTitle: 'Δεν χρειάζονται στοιχεία αποστολής',
          noShippingText:
            'Αυτό το checkout περιέχει μόνο συμμετοχές σε κληρώσεις, οπότε η πληρωμή συνεχίζει χωρίς διεύθυνση αποστολής.',
          name: 'Ονοματεπώνυμο',
          address: 'Διεύθυνση',
          phone: 'Τηλέφωνο',
          city: 'Πόλη',
          postalCode: 'Ταχυδρομικός κώδικας',
          country: 'Χώρα',
          deliveryMethod: 'Τρόπος παράδοσης',
          homeDelivery: 'Παράδοση στη διεύθυνση',
          pickupPoint: checkoutCarrier === 'boxnow' ? 'Locker BoxNow' : 'Σημείο DHL Service Point',
          pickupRequired: 'Επίλεξε σημείο παραλαβής για να συνεχίσεις.',
          paymentMethod: 'Μέθοδος πληρωμής',
          paymentMethods: ['Stripe Checkout'],
          paymentNotice:
            'Η πληρωμή παραμένει προστατευμένη μέχρι να ολοκληρωθεί σωστά η παραγγελία, με κάλυψη τόσο για τον αγοραστή όσο και για τον πωλητή.',
          privateOfferNotice:
            'Αυτό το checkout χρησιμοποιεί ιδιωτικό συμφωνημένο ποσό μόνο μεταξύ αγοραστή και πωλητή. Η δημόσια τιμή της αγγελίας δεν αλλάζει για κανέναν άλλο.',
          submit: 'Συνέχεια στο Stripe',
          summary: 'Σύνοψη παραγγελίας',
          total: 'Σύνολο',
          totalDue: 'Τελικό πληρωτέο ποσό',
          privateOfferSummary: 'Σύνοψη προσωπικής προσφοράς',
          privateOfferTag: 'Ιδιωτικό ποσό αγοραστή-πωλητή',
          agreedAmount: 'Συμφωνημένο σύνολο',
          cleanItemValue: 'Καθαρή αξία αντικειμένου',
          includedShipping: 'Μεταφορικά που περιλαμβάνονται',
          commissionTitle: 'Πολιτική χρέωσης πωλητή Cardora',
          commissionText:
            'Η χρέωση πωλητή υπολογίζεται μόνο στην καθαρή αξία αντικειμένου: 1€ για 0,01€-5€, 6,5% για 5,01€-300€, 5% για 300,01€-2.000€ και 4% πάνω από 2.000€, με ανώτατο πλαφόν 400€. Τα περιλαμβανόμενα μεταφορικά και τυχόν επιπλέον έξοδα μένουν εκτός αυτής της βάσης.',
          buyerFeeLabel: 'Χρέωση αγοραστή',
          sellerFeeLabel: 'Χρέωση πωλητή (κλιμακωτή)',
          paysThisExactAmount: 'Ο αγοραστής πληρώνει το συμφωνημένο σύνολο μαζί με τυχόν χρέωση αγοραστή που φαίνεται παρακάτω.',
          includedPhysical:
            'Στο τελικό ποσό περιλαμβάνονται ήδη τα μεταφορικά και η προστασία συναλλαγής από την Cardora, ώστε να καλύπτονται τόσο ο αγοραστής όσο και ο πωλητής μέχρι την ασφαλή παραλαβή και ολοκλήρωση.',
          includedDraw:
            'Το τελικό ποσό καλύπτει τις συμμετοχές σου και το ασφαλές checkout της Cardora, με την καταγραφή τους απευθείας στο σύστημα.',
          qty: 'Τεμ.',
          raffleTag: 'Συμμετοχή σε κλήρωση',
          pricePerEntry: 'ανά συμμετοχή',
          cancelledMessage: 'Το Stripe checkout ακυρώθηκε πριν ολοκληρωθεί η πληρωμή.',
          missingUrl: 'Δεν επιστράφηκε URL checkout από το Stripe.',
          lockedTitle: 'Το marketplace παραμένει κλειδωμένο',
          verification: 'Άνοιγμα verification center',
          dashboard: 'Άνοιγμα seller dashboard',
          lotCardsTag: 'Επιλογή καρτών από lot',
          fromLot: 'Από το lot',
          shippingInsurance: 'Μεταφορικά + ασφάλιση',
          lotShippingRule:
            'Για ξεχωριστή αγορά καρτών, τα μεταφορικά και η ασφάλιση ξεκινούν από 2,50€ έως 10 κάρτες και μετά προστίθενται 0,25€ ανά επιπλέον κάρτα.',
        },
  )

  const countryOptions =
    locale === 'en'
      ? [
          { value: 'GR', label: 'Greece' },
          { value: 'DE', label: 'Germany' },
          { value: 'FR', label: 'France' },
          { value: 'IT', label: 'Italy' },
          { value: 'ES', label: 'Spain' },
          { value: 'US', label: 'United States' },
          { value: 'CA', label: 'Canada' },
          { value: 'GB', label: 'United Kingdom' },
          { value: 'OTHER', label: 'Other country' },
        ]
      : [
          { value: 'GR', label: 'Ελλάδα' },
          { value: 'DE', label: 'Γερμανία' },
          { value: 'FR', label: 'Γαλλία' },
          { value: 'IT', label: 'Ιταλία' },
          { value: 'ES', label: 'Ισπανία' },
          { value: 'US', label: 'ΗΠΑ' },
          { value: 'CA', label: 'Καναδάς' },
          { value: 'GB', label: 'Ηνωμένο Βασίλειο' },
          { value: 'OTHER', label: 'Άλλη χώρα' },
        ]

  const buildShippingAddress = (form) => {
    if (!requiresShipping) {
      return null
    }

    const countryCode = String(form.get('country_code') || 'GR')
    const countryLabel =
      countryOptions.find((country) => country.value === countryCode)?.label ??
      (locale === 'en' ? 'Greece' : 'Ελλάδα')

    const address = {
      full_name: String(form.get('full_name') || ''),
      address_line_1: String(form.get('address') || ''),
      phone: String(form.get('phone') || ''),
      city: String(form.get('city') || ''),
      postal_code: String(form.get('postal_code') || ''),
      country_code: countryCode === 'OTHER' ? '' : countryCode,
      country: countryLabel,
      delivery_type: 'home_delivery',
    }

    if (isPickupDelivery && selectedPoint) {
      address.delivery_type = checkoutCarrier === 'boxnow' ? 'locker' : 'service_point'
      address.address_line_1 = selectedPoint.address || selectedPoint.name || address.address_line_1
      address.city = address.city || selectedPoint.city || ''
      address.postal_code = address.postal_code || selectedPoint.postal_code || ''
      address.service_point = {
        id: selectedPoint.id,
        name: selectedPoint.name,
        address: selectedPoint.address,
        city: selectedPoint.city,
        postal_code: selectedPoint.postal_code,
        country_code: selectedPoint.country_code,
      }
    }

    return address
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const shippingAddress = buildShippingAddress(form)

    try {
      setIsSubmitting(true)
      setSubmitError('')

      if (checkoutBlocked) {
        throw new Error(checkoutBlockedMessage)
      }

      if (isPickupDelivery && !selectedPoint) {
        throw new Error(copy.pickupRequired)
      }

      const checkout = await placeOrder({
        paymentMethod,
        shippingAddress,
        billingAddress: shippingAddress,
        acceptedOfferId: isPrivateOfferCheckout ? acceptedOfferId : null,
      })

      if (!checkout?.checkout_url) {
        throw new Error(copy.missingUrl)
      }

      window.location.href = checkout.checkout_url
    } catch (error) {
      setSubmitError(error.message)
      setIsSubmitting(false)
    }
  }

  useEffect(() => {
    if (!isAuthReady || !currentUser) {
      return
    }

    if (!checkoutCancelled || !cancelledOrderId || cancelledCleanupRef.current) {
      return
    }

    cancelledCleanupRef.current = true

    cancelPendingOrder(cancelledOrderId).catch(() => {})
  }, [cancelPendingOrder, cancelledOrderId, checkoutCancelled, currentUser, isAuthReady])

  const offerItemAmount = Number(acceptedOffer?.itemAmount ?? 0)
  const offerShippingAmount = Number(acceptedOffer?.shippingAmount ?? 0)
  const offerCommissionAmount = Number(acceptedOffer?.commissionAmount ?? 0)
  const offerBuyerFeeAmount = roundMoney(offerItemAmount * MARKETPLACE_FEE_RATE)
  const summaryTotal = isPrivateOfferCheckout
    ? roundMoney(offerItemAmount + offerShippingAmount + offerBuyerFeeAmount)
    : Number(cartSummary.total ?? 0)

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />

      <div className="grid gap-8 xl:grid-cols-[1fr,360px]">
        <CardSurface>
          <form onSubmit={handleSubmit} className="grid gap-5 md:grid-cols-2">
            <div className="md:col-span-2">
              <h3 className="font-display text-3xl text-white">
                {requiresShipping ? copy.shippingTitle : copy.noShippingTitle}
              </h3>
              {!requiresShipping ? (
                <p className="mt-3 text-sm leading-7 text-mist">{copy.noShippingText}</p>
              ) : null}
            </div>

            {requiresShipping ? (
              <>
                <div>
                  <label className="mb-2 block text-sm text-mist">{copy.name}</label>
                  <Input name="full_name" defaultValue="" required />
                </div>
                <div>
                  <label className="mb-2 block text-sm text-mist">{copy.phone}</label>
                  <Input name="phone" defaultValue="" required />
                </div>

                {carrierSupportsPickup ? (
                  <div className="md:col-span-2">
                    <label className="mb-2 block text-sm text-mist">{copy.deliveryMethod}</label>
                    <div className="grid grid-cols-2 gap-3">
                      <button
                        type="button"
                        onClick={() => setDeliveryType('home')}
                        className={`flex items-center justify-center gap-2 rounded-2xl border px-4 py-3 text-sm font-semibold transition ${
                          deliveryType === 'home'
                            ? 'border-gold-300/40 bg-gold-300/10 text-white'
                            : 'border-white/10 bg-white/5 text-mist hover:border-white/20'
                        }`}
                      >
                        <Home className="h-4 w-4" />
                        {copy.homeDelivery}
                      </button>
                      <button
                        type="button"
                        onClick={() => setDeliveryType('pickup')}
                        className={`flex items-center justify-center gap-2 rounded-2xl border px-4 py-3 text-sm font-semibold transition ${
                          deliveryType === 'pickup'
                            ? 'border-gold-300/40 bg-gold-300/10 text-white'
                            : 'border-white/10 bg-white/5 text-mist hover:border-white/20'
                        }`}
                      >
                        <Package className="h-4 w-4" />
                        {copy.pickupPoint}
                      </button>
                    </div>
                  </div>
                ) : null}

                {!isPickupDelivery ? (
                  <div className="md:col-span-2">
                    <label className="mb-2 block text-sm text-mist">{copy.address}</label>
                    <Input name="address" defaultValue="" required />
                  </div>
                ) : null}

                <div>
                  <label className="mb-2 block text-sm text-mist">{copy.city}</label>
                  <Input name="city" defaultValue="" required />
                </div>
                <div>
                  <label className="mb-2 block text-sm text-mist">{copy.postalCode}</label>
                  <Input name="postal_code" defaultValue="" required />
                </div>
                <div className="md:col-span-2">
                  <label className="mb-2 block text-sm text-mist">{copy.country}</label>
                  <Select name="country_code" defaultValue="GR">
                    {countryOptions.map((country) => (
                      <option key={country.value} value={country.value}>
                        {country.label}
                      </option>
                    ))}
                  </Select>
                </div>

                {isPickupDelivery ? (
                  <div className="md:col-span-2">
                    <PickupPointPicker
                      carrier={checkoutCarrier}
                      locale={locale}
                      selectedPoint={selectedPoint}
                      onSelect={setSelectedPoint}
                    />
                  </div>
                ) : null}
              </>
            ) : (
              <div className="md:col-span-2 rounded-[22px] border border-white/10 bg-white/5 p-4 text-sm leading-7 text-mist">
                <div className="flex items-start gap-3">
                  <Ticket className="mt-1 h-5 w-5 shrink-0 text-gold-100" />
                  <span>{copy.noShippingText}</span>
                </div>
              </div>
            )}

            <div className="md:col-span-2">
              <label className="mb-2 block text-sm text-mist">{copy.paymentMethod}</label>
              <Select value={paymentMethod} onChange={(event) => setPaymentMethod(event.target.value)}>
                {copy.paymentMethods.map((method) => (
                  <option key={method}>{method}</option>
                ))}
              </Select>
            </div>

            <div className="md:col-span-2 rounded-[22px] border border-gold-300/20 bg-gold-300/10 p-4 text-sm leading-7 text-gold-50">
              <div className="flex items-start gap-3">
                <ShieldCheck className="mt-1 h-5 w-5 shrink-0" />
                <span>{copy.paymentNotice}</span>
              </div>
            </div>

            {isPrivateOfferCheckout ? (
              <div className="md:col-span-2 rounded-[22px] border border-white/10 bg-white/5 p-4 text-sm leading-7 text-mist">
                <div className="flex items-start gap-3">
                  <ShieldCheck className="mt-1 h-5 w-5 shrink-0 text-gold-100" />
                  <span>{copy.privateOfferNotice}</span>
                </div>
              </div>
            ) : null}

            {checkoutBlocked ? (
              <div className="md:col-span-2 rounded-xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                <p className="font-semibold">{copy.lockedTitle}</p>
                <p className="mt-2 leading-7">{checkoutBlockedMessage}</p>
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
              </div>
            ) : null}

            {checkoutCancelled || submitError ? (
              <div className="md:col-span-2 rounded-xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                {submitError || copy.cancelledMessage}
              </div>
            ) : null}

            <div className="md:col-span-2">
              <Button type="submit" className="w-full" size="lg" disabled={isSubmitting || checkoutBlocked}>
                {copy.submit}
              </Button>
            </div>
          </form>
        </CardSurface>

        <CardSurface className="h-fit">
          <h3 className="font-display text-3xl text-white">{copy.summary}</h3>

          <div className="mt-5 space-y-4">
            {isPrivateOfferCheckout ? (
              <div className="rounded-2xl border border-white/8 bg-white/5 px-4 py-3">
                <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">
                  {copy.privateOfferTag}
                </p>
                <p className="mt-2 text-sm font-semibold text-white">
                  {acceptedOfferProduct?.title ?? acceptedOfferConversation?.productTitle ?? '-'}
                </p>
                <p className="mt-1 text-sm text-mist">{copy.paysThisExactAmount}</p>
                <div className="mt-4 space-y-2 rounded-2xl border border-white/10 bg-black/10 px-4 py-3">
                  <div className="flex items-center justify-between gap-4 text-sm">
                    <span className="text-mist">{copy.cleanItemValue}</span>
                    <span className="font-semibold text-white">{formatCurrency(offerItemAmount)}</span>
                  </div>
                  <div className="flex items-center justify-between gap-4 text-sm">
                    <span className="text-mist">{copy.includedShipping}</span>
                    <span className="font-semibold text-white">{formatCurrency(offerShippingAmount)}</span>
                  </div>
                  <div className="flex items-center justify-between gap-4 text-sm">
                    <span className="text-mist">{copy.buyerFeeLabel}</span>
                    <span className="font-semibold text-white">{formatCurrency(offerBuyerFeeAmount)}</span>
                  </div>
                  <div className="flex items-center justify-between gap-4 border-t border-white/10 pt-2 text-sm">
                    <span className="text-mist">{copy.agreedAmount}</span>
                    <span className="font-semibold text-white">{formatCurrency(summaryTotal)}</span>
                  </div>
                </div>
              </div>
            ) : (
              cartDetailed.map((item) => {
              const isLotSelectionItem = item.itemMode === 'lot_individual_cards'

              return (
                <div key={item.id} className="rounded-2xl border border-white/8 bg-white/5 px-4 py-3">
                  {item.itemType === 'draw_entry' ? (
                    <>
                      <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">
                        {copy.raffleTag}
                      </p>
                      <p className="mt-2 text-sm font-semibold text-white">{item.draw?.title}</p>
                      <p className="mt-1 text-sm text-mist">
                        {copy.qty} {item.quantity} • {formatCurrency(item.unitPrice)} / {copy.pricePerEntry}
                      </p>
                    </>
                  ) : isLotSelectionItem ? (
                    <>
                      <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">
                        {copy.lotCardsTag}
                      </p>
                      <p className="mt-2 text-sm font-semibold text-white">{item.displayTitle}</p>
                      <p className="mt-1 text-sm text-mist">
                        {copy.fromLot}: {item.product?.title}
                      </p>
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
                      <p className="mt-3 text-sm text-mist">
                        {copy.qty} {item.selectedCardsCount} • {formatCurrency(item.unitPrice)}
                      </p>
                      <p className="mt-1 text-sm text-gold-50">
                        {copy.shippingInsurance}: {formatCurrency(item.shippingCost)}
                      </p>
                      <p className="mt-1 text-xs leading-6 text-mist">{copy.lotShippingRule}</p>
                    </>
                  ) : (
                    <>
                      <p className="text-sm font-semibold text-white">{item.product?.title}</p>
                      <p className="mt-1 text-sm text-mist">
                        {copy.qty} {item.quantity} • {formatCurrency(item.unitPrice)}
                      </p>
                    </>
                  )}
                </div>
              )
              })
            )}
          </div>

          <div className="mt-5 rounded-[24px] border border-white/10 bg-white/5 px-5 py-4">
            <p className="text-xs uppercase tracking-[0.28em] text-gold-100">{copy.totalDue}</p>
            <div className="mt-3 flex items-end justify-between gap-4">
              <span className="text-sm text-mist">{copy.total}</span>
              <span className="text-3xl font-semibold text-white">
                {formatCurrency(summaryTotal)}
              </span>
            </div>
          </div>

          <div className="mt-5 rounded-[22px] border border-gold-300/20 bg-gold-300/10 p-4 text-sm leading-7 text-gold-50">
            {isPrivateOfferCheckout
              ? copy.commissionText
              : cartSummary.containsPhysicalItems
                ? copy.includedPhysical
                : copy.includedDraw}
          </div>

          {isPrivateOfferCheckout ? (
            <div className="mt-5 rounded-[22px] border border-white/10 bg-white/5 p-4">
              <p className="text-xs uppercase tracking-[0.28em] text-gold-100">{copy.commissionTitle}</p>
              <div className="mt-3 flex items-end justify-between gap-4">
                <span className="text-sm text-mist">{copy.cleanItemValue}</span>
                <span className="text-xl font-semibold text-white">{formatCurrency(offerItemAmount)}</span>
              </div>
              <div className="mt-3 flex items-end justify-between gap-4">
                <span className="text-sm text-mist">{copy.sellerFeeLabel}</span>
                <span className="text-lg font-semibold text-gold-50">{formatCurrency(offerCommissionAmount)}</span>
              </div>
              <div className="mt-3 flex items-end justify-between gap-4">
                <span className="text-sm text-mist">{copy.buyerFeeLabel}</span>
                <span className="text-lg font-semibold text-gold-50">{formatCurrency(offerBuyerFeeAmount)}</span>
              </div>
            </div>
          ) : null}
        </CardSurface>
      </div>
    </div>
  )
}

export default CheckoutPage
