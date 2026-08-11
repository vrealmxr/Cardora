import { Euro, Gavel, Heart, ShieldCheck, ShoppingCart, Tag, Truck, ZoomIn } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import Breadcrumbs from '@/components/catalog/Breadcrumbs'
import ProductCard from '@/components/catalog/ProductCard'
import SellerCard from '@/components/people/SellerCard'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import EmptyState from '@/components/ui/EmptyState'
import { Input } from '@/components/ui/Input'
import SectionHeader from '@/components/ui/SectionHeader'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { cardoraService } from '@/services/cardoraService'
import { formatCurrency, formatShortDateTime } from '@/utils/formatters'
import { calculateLotSelectionDomesticShipping } from '@/utils/listingShipping'
import { normalizeTextTree } from '@/utils/textEncoding'

const mapListingDetailsToProduct = (listing, fallbackProduct) => {
  if (!listing || !fallbackProduct) return fallbackProduct

  const attributes = listing.attributes ?? {}
  const productPayload = listing.product ?? {}
  const metadata = productPayload.metadata ?? {}

  return {
    ...fallbackProduct,
    publisher: attributes.publisher ?? productPayload.brand ?? fallbackProduct.publisher ?? '',
    issueNumber: attributes.issue_number ?? productPayload.item_number ?? fallbackProduct.issueNumber ?? '',
    printing: attributes.printing ?? fallbackProduct.printing ?? '',
    characterName: attributes.character_name ?? fallbackProduct.characterName ?? '',
    manufacturerLine: attributes.manufacturer_line ?? fallbackProduct.manufacturerLine ?? '',
    scale: attributes.scale ?? fallbackProduct.scale ?? '',
    figureHeight: attributes.figure_height ?? fallbackProduct.figureHeight ?? '',
    material: attributes.material ?? fallbackProduct.material ?? '',
    miniatureSubtype: attributes.miniature_subtype ?? fallbackProduct.miniatureSubtype ?? '',
    displayStatus: attributes.display_status ?? fallbackProduct.displayStatus ?? '',
    boxCondition: attributes.box_condition ?? fallbackProduct.boxCondition ?? '',
    edition: attributes.edition ?? fallbackProduct.edition ?? '',
    accessories: attributes.accessories ?? fallbackProduct.accessories ?? '',
    serialReference: attributes.serial_reference ?? fallbackProduct.serialReference ?? '',
    bundleContents: attributes.bundle_contents ?? fallbackProduct.bundleContents ?? '',
    writer: attributes.writer ?? fallbackProduct.writer ?? '',
    artist: attributes.artist ?? fallbackProduct.artist ?? '',
    coverArtist: attributes.cover_artist ?? fallbackProduct.coverArtist ?? '',
    signedBy: attributes.signed_by ?? fallbackProduct.signedBy ?? '',
    pageCount: attributes.page_count ?? fallbackProduct.pageCount ?? '',
    isbn: attributes.isbn ?? fallbackProduct.isbn ?? '',
    authenticity:
      productPayload.authenticity_notes ??
      attributes.authenticity ??
      fallbackProduct.authenticity ??
      '',
    shortDescription:
      fallbackProduct.shortDescription ??
      productPayload.subtitle ??
      productPayload.description ??
      '',
    description: productPayload.description ?? fallbackProduct.description ?? '',
    shippingInfo:
      listing.packaging_notes ??
      metadata.shipping_info ??
      fallbackProduct.shippingInfo ??
      '',
    returns: metadata.returns ?? fallbackProduct.returns ?? '',
  }
}

function ProductDetailPage() {
  const { currentUser } = useAuth()
  const { locale } = useI18n()
  const { slug } = useParams()
  const navigate = useNavigate()
  const {
    addToCart,
    createListingOffer,
    favoriteProductIds,
    marketplaceAccess,
    markProductViewed,
    placeAuctionBid,
    productsWithSellers,
    recentProducts,
    startConversationForListing,
    toggleFavorite,
  } = useMarketplace()
  const summaryProduct = productsWithSellers.find((item) => item.slug === slug)
  const [hydratedProduct, setHydratedProduct] = useState(null)
  const product = hydratedProduct ?? summaryProduct
  const [activeMediaIndex, setActiveMediaIndex] = useState(0)
  const [isZoomPinned, setIsZoomPinned] = useState(false)
  const [isImageHovering, setIsImageHovering] = useState(false)
  const [isTouchZoomDragging, setIsTouchZoomDragging] = useState(false)
  const [zoomOrigin, setZoomOrigin] = useState({ x: 50, y: 50 })
  const [bidAmount, setBidAmount] = useState('')
  const [bidNotice, setBidNotice] = useState(null)
  const [cartNotice, setCartNotice] = useState(null)
  const [selectedLotCardIds, setSelectedLotCardIds] = useState([])
  const [offerFormOpen, setOfferFormOpen] = useState(false)
  const [offerTotalAmount, setOfferTotalAmount] = useState('')
  const [offerNote, setOfferNote] = useState('')
  const [offerNotice, setOfferNotice] = useState(null)
  const [isOfferSubmitting, setIsOfferSubmitting] = useState(false)
  const lastViewedProductIdRef = useRef(null)

  const copy = normalizeTextTree(
    locale === 'en'
      ? {
          home: 'Home',
          frontView: 'Front view',
          detailView: 'Detail view',
          collectorView: 'Collector angle',
          auction: 'Auction',
          lot: 'Card lot',
          currentBid: 'Current bid',
          sellingPrice: 'Selling price',
          bids: 'bids',
          available: 'available',
          reserve: 'Reserve',
          noReserve: 'No reserve',
          bidStep: 'Bid step',
          ends: 'Ends',
          watchers: 'Watchers',
          minimumBid: 'Minimum',
          placeBid: 'Place bid',
          favorites: 'Favorites',
          buyout: 'Buyout',
          addToCart: 'Add to cart',
          buyNow: 'Buy now',
          paymentTitle: 'Protected payment',
          paymentText:
            'The payment stays secured by the platform and is released only after you confirm the item arrived as described.',
          shippingTitle: 'Shipping & returns',
          descriptionTitle: 'Item description',
          specsTitle: 'Item details',
          specsDescription:
            'Core listing details, condition information and reference data shown exactly where the buyer expects to find them.',
          lotEyebrow: 'Card lot overview',
          lotTitle: 'A clearer way to show many cards in one listing',
          lotDescription:
            'Instead of turning into a wall of text, the lot stays readable through a short summary, named highlights and the total count.',
          totalCards: 'total cards',
          guaranteedHits: 'guaranteed hits',
          moreCards: 'more cards',
          similarEyebrow: 'Similar Listings',
          similarTitle: 'More items from the same category',
          similarDescription:
            'Continue browsing nearby listings without losing the same collecting context.',
          recentEyebrow: 'Recently Viewed',
          recentTitle: 'Items you looked at recently',
          recentDescription:
            'A quick way back to other pieces you were checking before this one.',
          notFoundTitle: 'Listing not found',
          notFoundDescription:
            'The item you are looking for may no longer be available.',
          reserveMet: 'Sale format',
          fixedPrice: 'Fixed price',
          trade: 'Trade',
          category: 'Category',
          franchise: 'Franchise / Series',
          brandMaker: 'Brand / Maker',
          publisher: 'Publisher',
          issueVolume: 'Issue / Volume',
          printing: 'Printing / edition',
          character: 'Character',
          line: 'Line',
          writer: 'Writer',
          artist: 'Artist',
          coverArtist: 'Cover artist',
          scaleSize: 'Scale / Size',
          miniatureType: 'Miniatures category',
          figureHeight: 'Figure height',
          material: 'Material',
          displayStatus: 'Display status',
          boxCondition: 'Box condition',
          accessories: 'Accessories / parts',
          pageCount: 'Page count',
          isbn: 'ISBN / reference',
          serialReference: 'Serial / authenticity reference',
          contents: 'Contents / included pieces',
          grading: 'Grading company',
          grade: 'Grade',
          signedBy: 'Signed by',
          edition: 'Edition / release',
          authenticity: 'Authenticity',
          condition: 'Condition',
          year: 'Year',
          language: 'Language',
          saleFormat: 'Sale format',
        }
      : {
          home: 'Αρχική',
          frontView: 'Μπροστινή όψη',
          detailView: 'Λεπτομέρεια',
          collectorView: 'Γωνία συλλέκτη',
          auction: 'Δημοπρασία',
          lot: 'Lot καρτών',
          currentBid: 'Τρέχουσα προσφορά',
          sellingPrice: 'Τιμή πώλησης',
          bids: 'προσφορές',
          available: 'διαθέσιμο',
          reserve: 'Reserve',
          noReserve: 'Χωρίς reserve',
          bidStep: 'Βήμα',
          ends: 'Λήξη',
          watchers: 'Παρακολουθούν',
          minimumBid: 'Ελάχιστο',
          placeBid: 'Υποβολή προσφοράς',
          favorites: 'Αγαπημένα',
          buyout: 'Buyout',
          addToCart: 'Προσθήκη στο καλάθι',
          buyNow: 'Άμεση αγορά',
          paymentTitle: 'Προστατευμένη πληρωμή',
          paymentText:
            'Το ποσό παραμένει ασφαλές στην πλατφόρμα και αποδεσμεύεται μόνο αφού επιβεβαιώσεις ότι το αντικείμενο έφτασε όπως περιγραφόταν.',
          shippingTitle: 'Αποστολή & επιστροφές',
          descriptionTitle: 'Περιγραφή αντικειμένου',
          specsTitle: 'Τεχνικά στοιχεία',
          specsDescription:
            'Όλα τα βασικά στοιχεία της αγγελίας, η κατάσταση και τα σημεία αναφοράς εμφανίζονται συγκεντρωμένα εκεί που τα περιμένει ο αγοραστής.',
          lotEyebrow: 'Επισκόπηση lot',
          lotTitle: 'Ένας πιο καθαρός τρόπος να παρουσιαστούν πολλές κάρτες',
          lotDescription:
            'Το lot δεν μετατρέπεται σε κουραστικό κείμενο. Μένει ευανάγνωστο με μικρό summary, βασικά highlights και συνολικό πλήθος.',
          totalCards: 'συνολικές κάρτες',
          guaranteedHits: 'guaranteed hits',
          moreCards: 'ακόμη κάρτες',
          similarEyebrow: 'Παρόμοιες αγγελίες',
          similarTitle: 'Περισσότερα αντικείμενα από την ίδια κατηγορία',
          similarDescription:
            'Συνέχισε την περιήγηση σε συναφή listings χωρίς να χάνεις το ίδιο collecting context.',
          recentEyebrow: 'Πρόσφατα προβεβλημένα',
          recentTitle: 'Αντικείμενα που είδες πριν από αυτό',
          recentDescription:
            'Ένας γρήγορος τρόπος να επιστρέψεις σε κομμάτια που κοίταζες λίγο νωρίτερα.',
          notFoundTitle: 'Το προϊόν δεν βρέθηκε',
          notFoundDescription:
            'Η αγγελία που αναζητάς ίσως να μην είναι πια διαθέσιμη.',
          reserveMet: 'Μοντέλο πώλησης',
          fixedPrice: 'Σταθερή τιμή',
          category: 'Κατηγορία',
          franchise: 'Franchise / Σειρά',
          brandMaker: 'Brand / maker',
          publisher: 'Εκδότης',
          issueVolume: 'Τεύχος / τόμος',
          printing: 'Εκτύπωση / έκδοση',
          character: 'Χαρακτήρας',
          line: 'Line / collection',
          writer: 'Συγγραφέας',
          artist: 'Εικονογράφηση',
          coverArtist: 'Καλλιτέχνης εξωφύλλου',
          scaleSize: 'Scale / μέγεθος',
          miniatureType: 'Υποκατηγορία miniatures',
          figureHeight: 'Ύψος φιγούρας',
          material: 'Υλικό',
          displayStatus: 'Κατάσταση display',
          boxCondition: 'Κατάσταση κουτιού',
          accessories: 'Αξεσουάρ / μέρη',
          pageCount: 'Σελίδες',
          isbn: 'ISBN / αναφορά',
          serialReference: 'Serial / στοιχείο αυθεντικότητας',
          contents: 'Περιεχόμενα / included pieces',
          grading: 'Εταιρεία grading',
          grade: 'Βαθμός',
          signedBy: 'Υπογραφή',
          edition: 'Edition / release',
          authenticity: 'Αυθεντικότητα',
          condition: 'Κατάσταση',
          year: 'Έτος',
          language: 'Γλώσσα',
          saleFormat: 'Μοντέλο πώλησης',
        },
  )

  useEffect(() => {
    let isActive = true

    if (!summaryProduct?.listingId) {
      setHydratedProduct(null)
      return undefined
    }

    const hydrateProduct = async () => {
      try {
        const listing = await cardoraService.getListing(summaryProduct.listingId)
        if (!isActive) return
        setHydratedProduct(mapListingDetailsToProduct(listing, summaryProduct))
      } catch (error) {
        if (!isActive) return
        setHydratedProduct(summaryProduct)
      }
    }

    hydrateProduct()

    return () => {
      isActive = false
    }
  }, [summaryProduct])

  useEffect(() => {
    if (!product?.id) return
    const normalizedProductId = String(product.id)

    if (lastViewedProductIdRef.current === normalizedProductId) {
      return
    }

    lastViewedProductIdRef.current = normalizedProductId
    markProductViewed(normalizedProductId)
  }, [markProductViewed, product?.id])

  useEffect(() => {
    if (product?.saleFormat !== 'auction' || !product.auction) return

    const nextBidAmount = String(product.auction.currentBid + product.auction.bidIncrement)
    setBidAmount((previous) => (previous === nextBidAmount ? previous : nextBidAmount))
  }, [product?.auction?.bidIncrement, product?.auction?.currentBid, product?.id, product?.saleFormat])

  useEffect(() => {
    setActiveMediaIndex(0)
    setIsZoomPinned(false)
    setIsImageHovering(false)
    setIsTouchZoomDragging(false)
    setZoomOrigin({ x: 50, y: 50 })
    setCartNotice(null)
    setBidNotice(null)
    setOfferFormOpen(false)
    setOfferTotalAmount('')
    setOfferNote('')
    setOfferNotice(null)
    setBidAmount('')
    setSelectedLotCardIds([])
  }, [product?.id])

  if (!product) {
    return (
      <div className="container pb-14">
        <EmptyState
          title={copy.notFoundTitle}
          description={copy.notFoundDescription}
        />
      </div>
    )
  }

  const isAuction = product.saleFormat === 'auction'
  const isTrade = product.saleFormat === 'trade'
  const minimumNextBid = isAuction && product.auction ? product.auction.currentBid + product.auction.bidIncrement : 0
  const similarProducts = productsWithSellers
    .filter((item) => item.categoryId === product.categoryId && item.id !== product.id)
    .slice(0, 4)
  const recentlyViewed = recentProducts
    .filter((item) => String(item.id) !== String(product.id))
    .slice(0, 4)
  const isFavorite = favoriteProductIds.includes(product.id)
  const isOwnListing = Number(product.sellerId ?? product.seller?.id ?? 0) === Number(currentUser?.id ?? 0)
  const ownListingLabel = locale === 'en' ? 'Your listing' : 'Δική σου αγγελία'
  const mediaItems = Array.isArray(product.media)
    ? product.media.filter((item) => item?.url)
    : []
  const buyGateMessage =
    currentUser && marketplaceAccess && !marketplaceAccess.can_buy
      ? marketplaceAccess.blocking_message
      : ''
  const allowsPrivateOffers = Boolean(product?.acceptOffers) && product?.saleFormat === 'fixed_price'
  const activeMedia = mediaItems[activeMediaIndex] ?? mediaItems[0] ?? null
  const isFigureProduct = product?.categoryId === 'figures'
  const isComicProduct = product?.categoryId === 'comics'
  const isMiscProduct = product?.categoryId === 'misc'
  const imageTags = [...new Set((
    isComicProduct
      ? [product.publisher || product.brand, product.series, product.issueNumber, product.printing || product.typeLabel]
      : isMiscProduct
        ? [product.franchise, product.typeLabel, product.edition || product.brand]
      : [product.franchise, product.series, product.typeLabel]
  )
    .filter(Boolean))]
    .slice(0, 4)
  const isZoomActive = Boolean(activeMedia) && (isZoomPinned || isImageHovering)
  const wholeLotUnavailable = Boolean(
    product.lot?.allowsIndividualPurchase && product.lot?.wholeLotPurchaseAvailable === false,
  )
  const lotIndividualCards = Array.isArray(product.lot?.individualCards)
    ? product.lot.individualCards
    : []
  const productHighlights = Array.isArray(product.highlights) ? product.highlights.filter(Boolean) : []
  const lotThemes = Array.isArray(product.lot?.themes) ? product.lot.themes.filter(Boolean) : []
  const lotPreviewCards = Array.isArray(product.lot?.previewCards) ? product.lot.previewCards.filter(Boolean) : []
  const selectedLotCards = lotIndividualCards.filter((card) => selectedLotCardIds.includes(card.id))
  const selectedLotCardsCount = selectedLotCards.length
  const selectedLotCardsSubtotal = selectedLotCards.reduce(
    (sum, card) => sum + Number(card.price ?? 0),
    0,
  )
  const selectedLotCardsShipping = selectedLotCardsCount
    ? calculateLotSelectionDomesticShipping(selectedLotCardsCount)
    : 0
  const selectedLotCardsTotal = selectedLotCardsSubtotal + selectedLotCardsShipping
  const lotSelectionButtonLabel =
    locale === 'en' ? 'Add selected cards to cart' : 'Προσθήκη επιλεγμένων καρτών στο καλάθι'
  const lotSelectionTitle =
    locale === 'en' ? 'Choose cards from the lot' : 'Επίλεξε κάρτες από το lot'
  const lotSelectionDescription =
    locale === 'en'
      ? 'Buy only the cards you want instead of the full lot. Shipping and insurance start at €2.50 for up to 10 cards, then €0.25 per extra card.'
      : 'Αγόρασε μόνο τις κάρτες που θέλεις αντί για όλο το lot. Τα μεταφορικά και η ασφάλιση ξεκινούν από 2,50€ έως 10 κάρτες και μετά προστίθενται 0,25€ για κάθε επιπλέον κάρτα.'
  const wholeLotUnavailableMessage =
    locale === 'en'
      ? 'The full lot is no longer available as one bundle. You can still buy the remaining cards individually below.'
      : 'Το πλήρες lot δεν είναι πλέον διαθέσιμο ως ενιαίο πακέτο. Μπορείς όμως να αγοράσεις παρακάτω τις κάρτες που έχουν απομείνει ξεχωριστά.'
  const wholeLotButtonLabel = wholeLotUnavailable
    ? locale === 'en'
      ? 'Full lot unavailable'
      : 'Το πλήρες lot δεν είναι διαθέσιμο'
    : isOwnListing
      ? ownListingLabel
      : copy.addToCart
  const privateOfferButtonLabel = locale === 'en' ? 'Private offer' : 'Προσωπική προσφορά'
  const privateOfferTitle = locale === 'en' ? 'Send a private amount to the seller' : 'Στείλε ιδιωτική προσφορά στον πωλητή'
  const privateOfferDescription =
    locale === 'en'
      ? 'This amount is visible only between you and the seller. The public listing price stays unchanged.'
      : 'Το ποσό αυτό είναι ορατό μόνο σε εσένα και τον πωλητή. Η δημόσια τιμή της αγγελίας δεν αλλάζει.'
  const privateOfferAmountLabel =
    locale === 'en' ? 'Agreed total for this buyer' : 'Συμφωνημένο σύνολο για αυτόν τον αγοραστή'
  const privateOfferAmountHint =
    locale === 'en'
      ? 'The agreed total must already include the mandatory €2.50 shipping amount.'
      : 'Το συμφωνημένο σύνολο πρέπει να περιλαμβάνει ήδη τα υποχρεωτικά 2,50€ μεταφορικών.'
  const privateOfferNoteLabel = locale === 'en' ? 'Message with the offer' : 'Μήνυμα μαζί με την προσφορά'
  const privateOfferNotePlaceholder =
    locale === 'en'
      ? 'Optional note for the seller...'
      : 'Προαιρετικό μήνυμα για τον πωλητή...'
  const privateOfferSubmitLabel = locale === 'en' ? 'Send private offer' : 'Στείλε προσωπική προσφορά'
  const privateOfferCancelLabel = locale === 'en' ? 'Cancel' : 'Ακύρωση'
  const privateOfferSuccess =
    locale === 'en'
      ? 'The private offer was sent. You can continue the negotiation in messages.'
      : 'Η προσωπική προσφορά στάλθηκε. Μπορείς να συνεχίσεις τη διαπραγμάτευση στα μηνύματα.'
  const privateOfferMinimumHint =
    locale === 'en'
      ? 'The total amount must stay above the included €2.50 shipping.'
      : 'Το συνολικό ποσό πρέπει να μένει πάνω από τα 2,50€ που περιλαμβάνονται για μεταφορικά.'
  const privateOfferMessageButtonDisabled = isOwnListing || Boolean(buyGateMessage)
  const toggleLotCardSelection = (cardId) => {
    setSelectedLotCardIds((previous) =>
      previous.includes(cardId)
        ? previous.filter((item) => item !== cardId)
        : [...previous, cardId],
    )
  }

  const updateZoomOriginFromPointer = (event) => {
    const bounds = event.currentTarget.getBoundingClientRect()
    const x = ((event.clientX - bounds.left) / bounds.width) * 100
    const y = ((event.clientY - bounds.top) / bounds.height) * 100

    setZoomOrigin({
      x: Number.isFinite(x) ? Math.min(Math.max(x, 0), 100) : 50,
      y: Number.isFinite(y) ? Math.min(Math.max(y, 0), 100) : 50,
    })
  }

  const handleImagePointerMove = (event) => {
    if (!activeMedia?.url) return
    updateZoomOriginFromPointer(event)
  }

  const handleImagePointerDown = (event) => {
    if (!activeMedia?.url || event.pointerType === 'mouse') return

    setIsTouchZoomDragging(true)
    if (!isZoomPinned) {
      setIsZoomPinned(true)
    }

    updateZoomOriginFromPointer(event)
  }

  const handleImageTouchMove = (event) => {
    if (!activeMedia?.url || !isZoomPinned) return
    event.preventDefault()

    const touch = event.touches?.[0]
    if (!touch) return

    updateZoomOriginFromPointer({
      clientX: touch.clientX,
      clientY: touch.clientY,
      currentTarget: event.currentTarget,
    })
  }

  const handleImagePointerUp = (event) => {
    if (event.pointerType !== 'mouse') {
      setIsTouchZoomDragging(false)
    }
  }

  const specifications = (
    isFigureProduct
      ? [
          [copy.category, [product.category?.name, product.typeLabel].filter(Boolean).join(' / ')],
          [copy.franchise, product.franchise],
          [copy.condition, product.condition],
          [copy.character, product.characterName],
          [copy.line, product.series || product.manufacturerLine],
          [copy.scaleSize, product.scale],
          [copy.miniatureType, product.miniatureSubtype],
          [copy.figureHeight, product.figureHeight],
          [copy.material, product.material],
          [copy.displayStatus, product.displayStatus],
          [copy.boxCondition, product.boxCondition],
          [copy.accessories, product.accessories],
          [copy.edition, product.edition],
          [copy.authenticity, product.authenticity],
          [copy.saleFormat, isAuction ? copy.auction : isTrade ? (copy.trade ?? 'Trade') : copy.fixedPrice],
        ]
      : isComicProduct
        ? [
            [copy.category, [product.category?.name, product.typeLabel].filter(Boolean).join(' / ')],
            [copy.franchise, [product.franchise, product.series].filter(Boolean).join(' • ')],
            [copy.publisher, product.publisher || product.brand],
            [copy.condition, product.condition],
            [copy.issueVolume, product.issueNumber || product.cardNumber],
            [copy.printing, product.printing],
            [copy.language, product.language],
            [copy.writer, product.writer],
            [copy.artist, product.artist],
            [copy.coverArtist, product.coverArtist],
            [copy.pageCount, product.pageCount],
            [copy.isbn, product.isbn],
            [copy.grading, product.gradedCompany],
            [copy.grade, product.grade],
            [copy.signedBy, product.signedBy],
            [copy.edition, product.edition],
            [copy.authenticity, product.authenticity],
            [copy.saleFormat, isAuction ? copy.auction : isTrade ? (copy.trade ?? 'Trade') : copy.fixedPrice],
          ]
      : isMiscProduct
        ? [
            [copy.category, [product.category?.name, product.typeLabel].filter(Boolean).join(' / ')],
            [copy.franchise, product.franchise],
            [copy.brandMaker, product.brand],
            [copy.condition, product.condition],
            [copy.edition, product.edition],
            [copy.material, product.material],
            [copy.serialReference, product.serialReference],
            [copy.contents, product.bundleContents],
            [copy.year, product.year],
            [copy.authenticity, product.authenticity],
            [copy.saleFormat, isAuction ? copy.auction : isTrade ? (copy.trade ?? 'Trade') : copy.fixedPrice],
          ]
      : [
          [copy.category, product.category?.name],
          [copy.franchise, [product.franchise, product.series].filter(Boolean).join(' • ')],
          [copy.condition, product.condition],
          ['Set Name', product.setName],
          ['Card / Item Number', product.cardNumber],
          [copy.year, product.year],
          [copy.language, product.language],
          [copy.grading, product.gradedCompany],
          [copy.grade, product.grade],
          [copy.authenticity, product.authenticity],
          [copy.saleFormat, isAuction ? copy.auction : isTrade ? (copy.trade ?? 'Trade') : copy.fixedPrice],
        ]
  ).filter(([, value]) => value)


  const handleBidSubmit = async () => {
    const result = await placeAuctionBid(product.id, Number(bidAmount))

    if (!result) return

    setBidNotice({
      tone: result.success ? 'success' : 'warning',
      text: result.message,
    })

    if (result.success) {
      setBidAmount(String((result.amount ?? minimumNextBid) + (product.auction?.bidIncrement ?? 0)))
    }
  }

  const handleAddToCart = async () => {
    const result = await addToCart(product.id)

    if (!result) return

    setCartNotice({
      tone: result.success ? 'success' : 'warning',
      text: result.message,
    })
  }

  const handleAddSelectedLotCardsToCart = async () => {
    const result = await addToCart({
      productId: product.id,
      itemMode: 'lot_individual_cards',
      selectedCardIds: selectedLotCardIds,
    })

    if (!result) return

    setCartNotice({
      tone: result.success ? 'success' : 'warning',
      text: result.message,
    })

    if (result.success) {
      setSelectedLotCardIds([])
    }
  }

  const handleSubmitPrivateOffer = async () => {
    if (isOfferSubmitting || !product?.id) {
      return
    }

    const parsedAmount = Number.parseFloat(String(offerTotalAmount).replace(',', '.'))

    if (!Number.isFinite(parsedAmount) || parsedAmount <= 2.5) {
      setOfferNotice({
        tone: 'warning',
        text: privateOfferMinimumHint,
      })
      return
    }

    try {
      setIsOfferSubmitting(true)
      setOfferNotice(null)

      const conversation = await startConversationForListing(product.id)

      if (!conversation?.id) {
        throw new Error(locale === 'en' ? 'Conversation could not be created.' : 'Δεν ήταν δυνατή η δημιουργία συνομιλίας.')
      }

      await createListingOffer({
        conversationId: conversation.id,
        totalAmount: parsedAmount,
        note: offerNote,
      })

      setOfferNotice({
        tone: 'success',
        text: privateOfferSuccess,
      })
      setOfferFormOpen(false)
      setOfferTotalAmount('')
      setOfferNote('')
      navigate(`/minymata?conversation=${conversation.id}`)
    } catch (error) {
      setOfferNotice({
        tone: 'warning',
        text: error.message,
      })
    } finally {
      setIsOfferSubmitting(false)
    }
  }

  return (
    <div className="container pb-14">
      <Breadcrumbs
        items={[
          { label: copy.home, href: '/' },
          { label: product.category?.name, href: `/${product.category?.slug}` },
          { label: product.title },
        ]}
      />

      <div className="mt-5 grid items-start gap-6 xl:grid-cols-[1fr,0.9fr]">
        <CardSurface className="space-y-4" hover={false}>
          <div
            className="relative overflow-hidden rounded-[24px] border border-[#eadab7] bg-[linear-gradient(160deg,rgba(255,251,241,0.82),rgba(247,236,210,0.58))]"
            onMouseMove={handleImagePointerMove}
            onMouseEnter={() => setIsImageHovering(true)}
            onMouseLeave={() => setIsImageHovering(false)}
            onPointerDown={handleImagePointerDown}
            onPointerUp={handleImagePointerUp}
            onPointerCancel={() => setIsTouchZoomDragging(false)}
            onTouchMove={handleImageTouchMove}
            onTouchEnd={() => setIsTouchZoomDragging(false)}
            onTouchCancel={() => setIsTouchZoomDragging(false)}
            style={{ touchAction: isZoomPinned || isTouchZoomDragging ? 'none' : 'pan-y' }}
          >
            {activeMedia?.url ? (
              <>
                <img
                  src={activeMedia.thumbUrl ?? activeMedia.url}
                  alt={activeMedia.alt ?? product.title}
                  className="absolute inset-0 h-full w-full scale-110 object-cover opacity-20 blur-xl"
                  loading="lazy"
                  decoding="async"
                />
                <img
                  src={activeMedia.url}
                  alt={activeMedia.alt ?? product.title}
                  className={`relative z-[1] h-[min(68vh,640px)] w-full object-contain p-3 transition-transform duration-150 ${
                    isZoomActive ? 'scale-[2.05]' : 'scale-100'
                  }`}
                  style={{ transformOrigin: `${zoomOrigin.x}% ${zoomOrigin.y}%` }}
                  loading="lazy"
                  decoding="async"
                />
              </>
            ) : (
              <div className="flex h-[min(68vh,640px)] w-full items-center justify-center bg-[linear-gradient(160deg,rgba(255,251,241,0.92),rgba(247,236,210,0.7))] p-6 text-center text-sm text-[#7a6440]">
                {locale === 'en' ? 'No photos have been uploaded yet for this listing.' : 'Δεν έχουν ανέβει ακόμα φωτογραφίες για αυτή την αγγελία.'}
              </div>
            )}

            <div className="pointer-events-none absolute inset-0 z-[2] bg-[linear-gradient(180deg,rgba(255,248,234,0.04),rgba(215,181,123,0.14)_100%)]" />

            {activeMedia?.url ? (
              <button
                type="button"
                onClick={() => setIsZoomPinned((previous) => !previous)}
                className="absolute right-3 top-3 z-[3] inline-flex items-center gap-1.5 rounded-full border border-[#e3c78f] bg-[rgba(255,250,241,0.9)] px-3 py-1.5 text-xs font-semibold text-[#6b4718] transition hover:border-[#d8b06a] hover:bg-[rgba(255,247,232,0.96)] hover:text-[#8a5a11]"
                aria-pressed={isZoomPinned}
              >
                <ZoomIn className="h-3.5 w-3.5" />
                {isZoomPinned ? (locale === 'en' ? 'Zoom x1' : 'Zoom x1') : (locale === 'en' ? 'Zoom x2' : 'Zoom x2')}
              </button>
            ) : null}

          </div>

          {imageTags.length > 0 ? (
            <div className="flex flex-wrap gap-2">
              {imageTags.map((tag, index) => (
                <Badge key={`${tag}-${index}`} tone="muted">
                  {tag}
                </Badge>
              ))}
            </div>
          ) : null}

          {mediaItems.length > 1 ? (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
              {mediaItems.map((media, index) => (
                <button
                  key={media.id ?? media.url ?? `media-${index}`}
                  type="button"
                  onClick={() => {
                    setActiveMediaIndex(index)
                    setIsZoomPinned(false)
                    setZoomOrigin({ x: 50, y: 50 })
                  }}
                  className={`rounded-[16px] border p-2 transition ${
                    index === activeMediaIndex
                      ? 'border-gold-300/60 bg-gold-300/14'
                      : 'border-[#eadab7] bg-[rgba(255,251,242,0.8)] hover:border-gold-300/40 hover:bg-[rgba(255,247,232,0.92)]'
                  }`}
                >
                  <div className="overflow-hidden rounded-[12px] border border-[#eadab7] bg-[linear-gradient(160deg,rgba(255,251,241,0.86),rgba(247,236,210,0.62))]">
                    <img
                      src={media.thumbUrl ?? media.url}
                      alt={media.alt ?? `${product.title}-${index + 1}`}
                      className="h-24 w-full object-contain p-1.5"
                      loading="lazy"
                    />
                  </div>
                  <p className="hidden">
                    {media.label ?? `${locale === 'en' ? 'Photo' : 'Φωτογραφία'} ${index + 1}`}
                  </p>
                </button>
              ))}
            </div>
          ) : null}

          {activeMedia?.url ? (
            <p className="text-xs text-mist">
              {locale === 'en'
                ? 'Move the mouse over the main image for close inspection. On mobile, enable zoom and drag your finger across the image.'
                : 'Μετακίνησε το ποντίκι πάνω στην κύρια φωτογραφία για λεπτομέρεια. Πάτησε το κουμπί zoom για κλείδωμα ή ξεκλείδωμα.'}
            </p>
          ) : null}
        </CardSurface>

        <div className="space-y-5">
          <CardSurface hover={false}>
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone="gold">{product.rarity}</Badge>
              <Badge tone={product.condition === 'Sealed' ? 'success' : 'muted'}>{product.condition}</Badge>
              <Badge tone="info">{product.category?.name}</Badge>
              {isAuction ? <Badge tone="warning">{copy.auction}</Badge> : null}
              {isTrade ? <Badge tone="info">{copy.trade ?? 'Trade'}</Badge> : null}
              {product.lot ? <Badge tone="info">{copy.lot}</Badge> : null}
            </div>

            <h1 className="mt-4 font-display text-4xl text-white">{product.title}</h1>
            <p className="mt-3.5 text-base leading-7 text-mist">{product.shortDescription}</p>

            <div className="mt-5 flex items-center gap-5">
              <div>
                <p className="text-[11px] uppercase tracking-[0.28em] text-white/45">
                  {isAuction ? copy.currentBid : isTrade ? (copy.trade ?? 'Trade') : copy.sellingPrice}
                </p>
                <p className="mt-1 text-3xl font-semibold text-white">{formatCurrency(product.price)}</p>
                {product.oldPrice && !isAuction && !isTrade ? (
                  <p className="mt-1 text-sm text-white/45 line-through">{formatCurrency(product.oldPrice)}</p>
                ) : null}
              </div>
              <div className="rounded-xl border border-white/10 bg-white/5 px-3.5 py-2.5 text-[13px] text-mist">
                {isAuction ? `${product.auction?.bidCount ?? 0} ${copy.bids}` : `${product.stock} ${copy.available}`}
              </div>
            </div>

            {isAuction && product.auction ? (
              <div className="mt-5 grid gap-3 md:grid-cols-4">
                <div className="rounded-2xl border border-white/8 bg-white/5 p-3">
                  <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.reserve}</p>
                  <p className="mt-2 text-sm font-semibold text-white">
                    {product.auction.reservePrice ? formatCurrency(product.auction.reservePrice) : copy.noReserve}
                  </p>
                </div>
                <div className="rounded-2xl border border-white/8 bg-white/5 p-3">
                  <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.bidStep}</p>
                  <p className="mt-2 text-sm font-semibold text-white">{formatCurrency(product.auction.bidIncrement)}</p>
                </div>
                <div className="rounded-2xl border border-white/8 bg-white/5 p-3">
                  <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.ends}</p>
                  <p className="mt-2 text-sm font-semibold text-white">{formatShortDateTime(product.auction.endsAt)}</p>
                </div>
                <div className="rounded-2xl border border-white/8 bg-white/5 p-3">
                  <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.watchers}</p>
                  <p className="mt-2 text-sm font-semibold text-white">{product.auction.watchers}</p>
                </div>
              </div>
            ) : null}

            {isAuction ? (
              <div className="mt-5 space-y-4">
                {buyGateMessage ? (
                  <div className="rounded-xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-800">
                    {buyGateMessage}
                    <div className="mt-3 flex flex-wrap gap-3">
                      <Button as={Link} to="/epalithefsi-logariasmou" size="sm" variant="secondary">
                        {locale === 'en' ? 'Open verification center' : 'Άνοιγμα verification center'}
                      </Button>
                      <Button as={Link} to="/dashboard-politi" size="sm">
                        {locale === 'en' ? 'Create Stripe account' : 'Δημιουργία Stripe account'}
                      </Button>
                    </div>
                  </div>
                ) : null}
                <div className="grid gap-3 md:grid-cols-[1fr,auto]">
                  <Input
                    type="number"
                    min={minimumNextBid}
                    step={product.auction?.bidIncrement ?? 1}
                    value={bidAmount}
                    onChange={(event) => setBidAmount(event.target.value)}
                    placeholder={`${copy.minimumBid} ${formatCurrency(minimumNextBid)}`}
                  />
                  <Button size="lg" onClick={handleBidSubmit} disabled={Boolean(buyGateMessage)}>
                    <Gavel className="h-4 w-4" />
                    {copy.placeBid}
                  </Button>
                </div>
                <div className="flex flex-wrap gap-2.5">
                  <Button
                    variant={isFavorite ? 'subtle' : 'secondary'}
                    size="lg"
                    onClick={() => toggleFavorite(product.id)}
                  >
                    <Heart className={`h-4 w-4 ${isFavorite ? 'fill-current' : ''}`} />
                    {copy.favorites}
                  </Button>
                  {product.auction?.buyoutPrice ? (
                    <div className="rounded-xl border border-gold-300/20 bg-gold-300/10 px-4 py-3 text-sm text-gold-50">
                      {copy.buyout}: {formatCurrency(product.auction.buyoutPrice)}
                    </div>
                  ) : null}
                </div>
                {bidNotice ? (
                  <div
                    className={`rounded-xl border px-4 py-3 text-sm ${
                      bidNotice.tone === 'success'
                        ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-800'
                        : 'border-amber-400/20 bg-amber-500/10 text-amber-800'
                    }`}
                  >
                    {bidNotice.text}
                  </div>
                ) : null}
              </div>
            ) : isTrade ? (
              <div className="mt-5 flex flex-wrap gap-2.5">
                <div className="w-full rounded-xl border border-gold-300/20 bg-gold-300/10 px-4 py-3 text-sm text-gold-50">
                  {locale === 'en'
                    ? 'This listing is available through Cardora Trades. Open the Trades hub to send your card proposal.'
                    : 'Αυτό το listing είναι διαθέσιμο μέσω Cardora Trades. Άνοιξε το Trades hub για να στείλεις πρόταση με τη δική σου κάρτα.'}
                </div>
                <Button as={Link} to="/dashboard-politi/kliroseis" size="lg">
                  <Tag className="h-4 w-4" />
                  {locale === 'en' ? 'Open Trades' : 'Άνοιγμα Trades'}
                </Button>
                <Button
                  variant={isFavorite ? 'subtle' : 'secondary'}
                  size="lg"
                  onClick={() => toggleFavorite(product.id)}
                >
                  <Heart className={`h-4 w-4 ${isFavorite ? 'fill-current' : ''}`} />
                  {copy.favorites}
                </Button>
              </div>
            ) : (
              <div className="mt-5 flex flex-wrap gap-2.5">
                {buyGateMessage ? (
                  <div className="w-full rounded-xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-800">
                    {buyGateMessage}
                    <div className="mt-3 flex flex-wrap gap-3">
                      <Button as={Link} to="/epalithefsi-logariasmou" size="sm" variant="secondary">
                        {locale === 'en' ? 'Open verification center' : 'Άνοιγμα verification center'}
                      </Button>
                      <Button as={Link} to="/dashboard-politi" size="sm">
                        {locale === 'en' ? 'Create Stripe account' : 'Δημιουργία Stripe account'}
                      </Button>
                    </div>
                  </div>
                ) : null}
                {wholeLotUnavailable ? (
                  <div className="w-full rounded-xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-800">
                    {wholeLotUnavailableMessage}
                  </div>
                ) : null}
                <Button
                  size="lg"
                  onClick={handleAddToCart}
                  disabled={isOwnListing || Boolean(buyGateMessage) || wholeLotUnavailable}
                >
                  <ShoppingCart className="h-4 w-4" />
                  {wholeLotButtonLabel}
                </Button>
                <Button
                  variant="secondary"
                  size="lg"
                  disabled={Boolean(buyGateMessage) || wholeLotUnavailable}
                >
                  {copy.buyNow}
                </Button>
                {allowsPrivateOffers ? (
                  <Button
                    variant="secondary"
                    size="lg"
                    onClick={() => {
                      setOfferFormOpen((previous) => !previous)
                      setOfferNotice(null)
                      setOfferTotalAmount(
                        String(
                          Number(product.minimumOffer ?? product.price ?? 2.5).toFixed(2),
                        ),
                      )
                    }}
                    disabled={privateOfferMessageButtonDisabled}
                  >
                    <Tag className="h-4 w-4" />
                    {privateOfferButtonLabel}
                  </Button>
                ) : null}
                <Button
                  variant={isFavorite ? 'subtle' : 'secondary'}
                  size="lg"
                  onClick={() => toggleFavorite(product.id)}
                >
                  <Heart className={`h-4 w-4 ${isFavorite ? 'fill-current' : ''}`} />
                  {copy.favorites}
                </Button>
              </div>
            )}

            {cartNotice ? (
              <div
                className={`mt-4 rounded-xl border px-4 py-3 text-sm ${
                  cartNotice.tone === 'success'
                    ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-800'
                    : 'border-amber-400/20 bg-amber-500/10 text-amber-800'
                }`}
              >
                {cartNotice.text}
              </div>
            ) : null}

            {offerFormOpen ? (
              <div className="mt-4 rounded-[24px] border border-gold-300/20 bg-gold-300/10 p-4">
                <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{privateOfferButtonLabel}</p>
                <h3 className="mt-3 text-lg font-semibold text-white">{privateOfferTitle}</h3>
                <p className="mt-2 text-sm leading-7 text-gold-50">{privateOfferDescription}</p>

                <div className="mt-4 grid gap-4 md:grid-cols-[220px,1fr]">
                  <div>
                    <label className="mb-2 block text-sm text-mist">{privateOfferAmountLabel}</label>
                    <Input
                      type="number"
                      min="2.51"
                      step="0.01"
                      value={offerTotalAmount}
                      onChange={(event) => setOfferTotalAmount(event.target.value)}
                      placeholder="0.00"
                    />
                    <p className="mt-2 text-xs leading-6 text-mist">{privateOfferAmountHint}</p>
                  </div>
                  <div>
                    <label className="mb-2 block text-sm text-mist">{privateOfferNoteLabel}</label>
                    <Input
                      value={offerNote}
                      onChange={(event) => setOfferNote(event.target.value)}
                      placeholder={privateOfferNotePlaceholder}
                    />
                  </div>
                </div>

                <div className="mt-4 flex flex-wrap gap-3">
                  <Button type="button" onClick={handleSubmitPrivateOffer} disabled={isOfferSubmitting}>
                    <Euro className="h-4 w-4" />
                    {privateOfferSubmitLabel}
                  </Button>
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={() => {
                      setOfferFormOpen(false)
                      setOfferNotice(null)
                    }}
                  >
                    {privateOfferCancelLabel}
                  </Button>
                </div>
              </div>
            ) : null}

            {offerNotice ? (
              <div
                className={`mt-4 rounded-xl border px-4 py-3 text-sm ${
                  offerNotice.tone === 'success'
                    ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-800'
                    : 'border-amber-400/20 bg-amber-500/10 text-amber-800'
                }`}
              >
                {offerNotice.text}
              </div>
            ) : null}

            <div className="mt-5 rounded-[20px] border border-gold-300/20 bg-gold-300/10 p-3.5">
              <div className="flex items-start gap-3">
                <ShieldCheck className="mt-1 h-5 w-5 text-gold-100" />
                <div>
                  <p className="font-semibold text-white">{copy.paymentTitle}</p>
                  <p className="mt-2 text-sm leading-7 text-gold-50">
                    {copy.paymentText}
                  </p>
                </div>
              </div>
            </div>
          </CardSurface>

          <SellerCard seller={product.seller} hover={false} />

          <CardSurface hover={false}>
            <div className="flex items-center gap-3">
              <Truck className="h-5 w-5 text-gold-100" />
              <div>
                <p className="font-semibold text-white">{copy.shippingTitle}</p>
                <p className="mt-1 text-sm leading-7 text-mist">{product.shippingInfo}</p>
              </div>
            </div>
            <p className="mt-3.5 text-sm leading-7 text-mist">{product.returns}</p>
            {product.lot?.allowsIndividualPurchase ? (
              <div className="mt-4 rounded-xl border border-gold-300/15 bg-gold-300/10 px-4 py-3 text-sm leading-7 text-gold-50">
                {lotSelectionDescription}
              </div>
            ) : null}
          </CardSurface>
        </div>
      </div>

      <div className="mt-8 grid items-start gap-6 xl:grid-cols-[1fr,0.9fr]">
        <CardSurface hover={false}>
          <SectionHeader title={copy.descriptionTitle} description={product.description} className="mb-6" />
          <div className="flex flex-wrap gap-2">
            {productHighlights.map((item) => (
              <Badge key={item} tone="muted">
                {item}
              </Badge>
            ))}
          </div>
        </CardSurface>

        <CardSurface hover={false}>
          <SectionHeader
            title={copy.specsTitle}
            description={copy.specsDescription}
            className="mb-6"
          />
          <div className="space-y-3">
            {specifications.map(([label, value]) => (
              <div key={label} className="flex items-start justify-between gap-4 rounded-xl border border-white/8 bg-white/5 px-3.5 py-2.5">
                <span className="text-[13px] text-mist">{label}</span>
                <span className="text-right text-[13px] font-semibold text-white">{value}</span>
              </div>
            ))}
          </div>
        </CardSurface>
      </div>

      {product.lot ? (
        <section className="mt-10">
          <CardSurface hover={false}>
            <SectionHeader
              eyebrow={copy.lotEyebrow}
              title={copy.lotTitle}
              description={copy.lotDescription}
            />
            <div className="flex flex-wrap gap-2">
              <Badge tone="gold">{product.lot.totalCards} {copy.totalCards}</Badge>
              {product.lot.guaranteedHits ? <Badge tone="info">{product.lot.guaranteedHits} {copy.guaranteedHits}</Badge> : null}
              {lotThemes.map((item) => (
                <Badge key={item} tone="muted">
                  {item}
                </Badge>
              ))}
            </div>
            <div className="mt-5 flex flex-wrap gap-2">
              {lotPreviewCards.map((item) => (
                <span key={item} className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-white/76">
                  {item}
                </span>
              ))}
              {product.lot.overflowCount > 0 ? (
                <span className="rounded-full border border-gold-300/20 bg-gold-300/12 px-3 py-1.5 text-sm text-gold-100">
                  +{product.lot.overflowCount} {copy.moreCards}
                </span>
              ) : null}
            </div>
            {product.lot.note ? <p className="mt-5 text-sm leading-7 text-mist">{product.lot.note}</p> : null}
            {product.lot.allowsIndividualPurchase ? (
              <div className="mt-6 rounded-[24px] border border-gold-300/18 bg-gold-300/10 p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">
                      {locale === 'en' ? 'Individual cards' : 'Ξεχωριστές κάρτες'}
                    </p>
                    <h3 className="mt-3 text-2xl font-semibold text-white">{lotSelectionTitle}</h3>
                    <p className="mt-2 max-w-3xl text-sm leading-7 text-gold-50">
                      {lotSelectionDescription}
                    </p>
                  </div>
                  <Badge tone="gold">
                    {selectedLotCardsCount} {locale === 'en' ? 'selected' : 'επιλεγμένες'}
                  </Badge>
                </div>

                <div className="mt-5 grid gap-3 md:grid-cols-2">
                  {lotIndividualCards.length ? lotIndividualCards.map((card) => {
                    const isSelected = selectedLotCardIds.includes(card.id)

                    return (
                      <button
                        key={card.id}
                        type="button"
                        onClick={() => toggleLotCardSelection(card.id)}
                        className={`rounded-[18px] border px-4 py-3 text-left transition ${
                          isSelected
                            ? 'border-gold-300/40 bg-gold-300/12'
                            : 'border-white/10 bg-white/5 hover:border-white/20'
                        }`}
                      >
                        <div className="flex items-start justify-between gap-3">
                          <div>
                            <p className="text-sm font-semibold text-white">{card.title}</p>
                            <p className="mt-1 text-xs text-mist">
                              {isSelected
                                ? locale === 'en'
                                  ? 'Selected for cart'
                                  : 'Επιλεγμένη για το καλάθι'
                                : locale === 'en'
                                  ? 'Tap to include'
                                  : 'Πάτησε για επιλογή'}
                            </p>
                          </div>
                          <span className="text-sm font-semibold text-gold-100">
                            {formatCurrency(card.price)}
                          </span>
                        </div>
                      </button>
                    )
                  }) : (
                    <p className="text-sm text-mist">
                      {locale === 'en'
                        ? 'No single-card options are available for this lot right now.'
                        : 'Δεν υπάρχουν αυτή τη στιγμή διαθέσιμες κάρτες για ξεχωριστή αγορά σε αυτό το lot.'}
                    </p>
                  )}
                </div>

                <div className="mt-5 rounded-[20px] border border-white/10 bg-black/10 p-4">
                  <div className="grid gap-4 md:grid-cols-4">
                    <div>
                      <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">
                        {locale === 'en' ? 'Cards' : 'Κάρτες'}
                      </p>
                      <p className="mt-2 text-lg font-semibold text-white">{selectedLotCardsCount}</p>
                    </div>
                    <div>
                      <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">
                        {locale === 'en' ? 'Cards total' : 'Σύνολο καρτών'}
                      </p>
                      <p className="mt-2 text-lg font-semibold text-white">{formatCurrency(selectedLotCardsSubtotal)}</p>
                    </div>
                    <div>
                      <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">
                        {locale === 'en' ? 'Shipping + insurance' : 'Μεταφορικά + ασφάλιση'}
                      </p>
                      <p className="mt-2 text-lg font-semibold text-white">{formatCurrency(selectedLotCardsShipping)}</p>
                    </div>
                    <div>
                      <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">
                        {locale === 'en' ? 'Total' : 'Τελικό σύνολο'}
                      </p>
                      <p className="mt-2 text-lg font-semibold text-gold-100">{formatCurrency(selectedLotCardsTotal)}</p>
                    </div>
                  </div>

                  <div className="mt-4 flex flex-wrap gap-3">
                    <Button
                      type="button"
                      onClick={handleAddSelectedLotCardsToCart}
                      disabled={!selectedLotCardsCount || isOwnListing || Boolean(buyGateMessage)}
                    >
                      <ShoppingCart className="h-4 w-4" />
                      {lotSelectionButtonLabel}
                    </Button>
                    {selectedLotCardsCount ? (
                      <Button
                        type="button"
                        variant="secondary"
                        onClick={() => setSelectedLotCardIds([])}
                      >
                        {locale === 'en' ? 'Clear selection' : 'Καθαρισμός επιλογής'}
                      </Button>
                    ) : null}
                  </div>
                </div>
              </div>
            ) : null}
          </CardSurface>
        </section>
      ) : null}

      <section className="mt-16">
        <SectionHeader
          eyebrow={copy.similarEyebrow}
          title={copy.similarTitle}
          description={copy.similarDescription}
        />
        <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {similarProducts.map((item) => (
            <ProductCard key={item.id} product={item} />
          ))}
        </div>
      </section>

      <section className="mt-16">
        <SectionHeader
          eyebrow={copy.recentEyebrow}
          title={copy.recentTitle}
          description={copy.recentDescription}
        />
        <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {recentlyViewed.map((item) => (
            <ProductCard key={item.id} product={item} />
          ))}
        </div>
      </section>
    </div>
  )
}

export default ProductDetailPage
