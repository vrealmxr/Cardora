import { Filter } from 'lucide-react'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import FilterSidebar from '@/components/catalog/FilterSidebar'
import ProductCard from '@/components/catalog/ProductCard'
import TradeSwapStudio from '@/components/draws/TradeSwapStudio'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import Drawer from '@/components/ui/Drawer'
import { Input } from '@/components/ui/Input'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { cardoraService } from '@/services/cardoraService'
import { formatCurrency, formatNumber } from '@/utils/formatters'

function DrawsPage({ studioMode = false }) {
  const { locale } = useI18n()
  const { currentUser, isAuthenticated } = useAuth()
  const {
    searchProducts,
    getDynamicFacets,
    defaultSearchFilters,
    allFilterValue,
  } = useMarketplace()
  const [searchParams] = useSearchParams()

  const [submittingTradeRequest, setSubmittingTradeRequest] = useState(false)
  const [tradeActionError, setTradeActionError] = useState('')
  const [tradeActionSuccess, setTradeActionSuccess] = useState('')
  const tradeFeedbackRef = useRef(null)

  const [incomingRequests, setIncomingRequests] = useState([])
  const [sentRequests, setSentRequests] = useState([])
  const [myTradeDeals, setMyTradeDeals] = useState([])
  const [loadingTradeData, setLoadingTradeData] = useState(false)
  const [selectedTradeRequest, setSelectedTradeRequest] = useState(null)
  const [selectedTradeDealId, setSelectedTradeDealId] = useState(null)
  const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false)
  const [sort, setSort] = useState('newest')
  const [tradeFilters, setTradeFilters] = useState(() => ({
    ...defaultSearchFilters,
    search: '',
  }))
  const tradeRequestIdFromQuery = Number(searchParams.get('trade_request') || 0)
  const tradeRequestScopeFromQuery = String(searchParams.get('trade_scope') || '').toLowerCase()

  const copy =
    locale === 'en'
      ? {
          badge: 'Card Swaps',
          noAutoRelease: 'Manual release',
          title: 'Collector-to-collector card trades',
          description:
            'Swap cards safely with other collectors and track every step in one place.',
          listingValue: 'Declared listing value',
          seller: 'Seller',
          sendRequest: 'Send swap request',
          ownListing: 'Your listing',
          loginToTrade: 'Sign in to send trade requests',
          noTradeListings: 'No active swap listings yet.',
          submitTradeRequest: 'Send swap request',
          offeredTitle: 'Your card title',
          offeredDescription: 'Your card details',
          offeredCondition: 'Condition',
          offeredValue: 'Your declared value (EUR)',
          offeredDescriptionShort: 'Description',
          offeredImages: 'Image URLs (one per line)',
          offeredImagesFiles: 'Upload images',
          requestMessage: 'Message to the collector',
          terms:
            'I understand the swap completes only after release from both sides and includes a 5% fee per side when completed successfully.',
          incoming: 'Incoming swap requests',
          sent: 'Your sent requests',
          filters: 'Filters',
          searchTradeListings: 'Search swap listings',
          sortNewest: 'Newest first',
          sortPriceAsc: 'Price: low to high',
          sortPriceDesc: 'Price: high to low',
          sortRating: 'Seller rating',
          clearFilters: 'Clear filters',
          viewDetails: 'View details',
          details: 'Swap request details',
          offeredBy: 'Offered by',
          targetListing: 'Target listing',
          yourCard: 'Offered card',
          notes: 'Message',
          photos: 'Photos',
          noPhotos: 'No photos attached.',
          noNotes: 'No message attached.',
          submitted: 'Submitted',
          deals: 'Your active swaps',
          accept: 'Accept',
          reject: 'Reject',
          cancel: 'Cancel',
          openStripe: 'Pay deposit',
          release: 'Confirm release',
          dispute: 'Report issue',
          viewDeal: 'Deal details',
          refreshDeals: 'Refresh swaps',
          role: 'Role',
          you: 'You',
          owner: 'Owner',
          proposer: 'Proposer',
          paymentState: 'My payment',
          ownerPayment: 'Owner payment',
          proposerPayment: 'Proposer payment',
          releaseState: 'My release',
          paid: 'Paid',
          unpaid: 'Unpaid',
          releasedState: 'Released',
          waiting: 'Waiting',
          counterparty: 'Counterparty',
          status: 'Status',
          deposit: 'Deposit per side',
          ownerNet: 'Owner net payout',
          proposerNet: 'Proposer net payout',
          noIncoming: 'No incoming requests.',
          noSent: 'No sent requests.',
          noDeals: 'No active swaps yet.',
          connectedRequired:
            'Both participants need a fully connected Stripe account before the swap can start.',
          paymentSuccess: 'Your deposit was paid successfully.',
          paymentCancelled: 'Deposit payment was cancelled.',
          close: 'Close',
          loading: 'Loading...',
          safetyRules:
            'When both sides press release, the swap is completed. If anything comes up, the Cardora team steps in right away.',
        }
      : {
          badge: 'Ανταλλαγές καρτών',
          noAutoRelease: 'Χειροκίνητο release',
          title: 'Ανταλλαγές καρτών μεταξύ συλλεκτών',
          description:
            'Κάνε ασφαλείς ανταλλαγές καρτών με άλλους συλλέκτες και παρακολούθησε όλη τη διαδικασία σε ένα σημείο.',
          listingValue: 'Δηλωμένη αξία listing',
          seller: 'Πωλητής',
          sendRequest: 'Στείλε πρόταση ανταλλαγής',
          ownListing: 'Δική σου αγγελία',
          loginToTrade: 'Σύνδεση για trade αιτήματα',
          noTradeListings: 'Δεν υπάρχουν ενεργές αγγελίες ανταλλαγής ακόμα.',
          submitTradeRequest: 'Αποστολή πρότασης ανταλλαγής',
          offeredTitle: 'Τίτλος κάρτας σου',
          offeredDescription: 'Πληροφορίες κάρτας σου',
          offeredCondition: 'Κατάσταση',
          offeredValue: 'Δηλωμένη αξία σου (EUR)',
          offeredDescriptionShort: 'Περιγραφή',
          offeredImages: 'URL φωτογραφιών (μία ανά γραμμή)',
          offeredImagesFiles: 'Ανέβασμα φωτογραφιών',
          requestMessage: 'Μήνυμα προς τον συλλέκτη',
          terms:
            'Καταλαβαίνω ότι η ανταλλαγή ολοκληρώνεται μόνο με release και από τις δύο πλευρές και ότι κρατείται 5% ανά πλευρά στην επιτυχημένη ολοκλήρωση.',
          incoming: 'Εισερχόμενες προτάσεις',
          sent: 'Προτάσεις που έστειλες',
          filters: 'Φίλτρα',
          searchTradeListings: 'Αναζήτηση σε αγγελίες ανταλλαγής',
          sortNewest: 'Νεότερα πρώτα',
          sortPriceAsc: 'Τιμή: χαμηλή σε υψηλή',
          sortPriceDesc: 'Τιμή: υψηλή σε χαμηλή',
          sortRating: 'Βαθμολογία πωλητή',
          clearFilters: 'Καθαρισμός φίλτρων',
          viewDetails: 'Προβολή λεπτομερειών',
          details: 'Λεπτομέρειες πρότασης',
          offeredBy: 'Από χρήστη',
          targetListing: 'Listing στόχος',
          yourCard: 'Κάρτα που προτάθηκε',
          notes: 'Μήνυμα',
          photos: 'Φωτογραφίες',
          noPhotos: 'Δεν έχουν προστεθεί φωτογραφίες.',
          noNotes: 'Δεν έχει προστεθεί μήνυμα.',
          submitted: 'Υποβλήθηκε',
          deals: 'Οι ενεργές ανταλλαγές σου',
          accept: 'Αποδοχή',
          reject: 'Απόρριψη',
          cancel: 'Ακύρωση',
          openStripe: 'Πληρωμή εγγύησης',
          release: 'Επιβεβαίωση release',
          dispute: 'Δήλωση προβλήματος',
          viewDeal: 'Λεπτομέρειες deal',
          refreshDeals: 'Ανανέωση ανταλλαγών',
          role: 'Ρόλος',
          you: 'Εσύ',
          owner: 'Owner',
          proposer: 'Proposer',
          paymentState: 'Η πληρωμή μου',
          ownerPayment: 'Πληρωμή owner',
          proposerPayment: 'Πληρωμή proposer',
          releaseState: 'Το release μου',
          paid: 'Πληρωμένο',
          unpaid: 'Απλήρωτο',
          releasedState: 'Έγινε release',
          waiting: 'Σε αναμονή',
          counterparty: 'Άλλος χρήστης',
          status: 'Κατάσταση',
          deposit: 'Εγγύηση ανά πλευρά',
          ownerNet: 'Καθαρό ποσό owner',
          proposerNet: 'Καθαρό ποσό proposer',
          noIncoming: 'Δεν υπάρχουν εισερχόμενα αιτήματα.',
          noSent: 'Δεν υπάρχουν απεσταλμένα αιτήματα.',
          noDeals: 'Δεν υπάρχουν ενεργές ανταλλαγές ακόμα.',
          connectedRequired:
            'Για να ξεκινήσει η ανταλλαγή, και οι δύο πλευρές χρειάζονται πλήρως συνδεδεμένο Stripe account.',
          paymentSuccess: 'Η πληρωμή της εγγύησης ολοκληρώθηκε επιτυχώς.',
          paymentCancelled: 'Η πληρωμή της εγγύησης ακυρώθηκε.',
          close: 'Κλείσιμο',
          loading: 'Φόρτωση...',
          safetyRules:
            'Όταν πατήσουν και οι δύο πλευρές release, η ανταλλαγή ολοκληρώνεται. Αν προκύψει οτιδήποτε, η ομάδα της Cardora το αναλαμβάνει άμεσα.',
        }

  const isCardTradeListing = (product) => {
    if (product?.saleFormat !== 'trade') return false
    const categoryKey = String(product?.categoryId ?? product?.category?.slug ?? '').toLowerCase()
    return categoryKey === 'cards' || categoryKey.includes('kart')
  }

  const filteredTradeListings = useMemo(() => {
    const results = searchProducts({
      categoryId: 'cards',
      query: tradeFilters.search,
      price: tradeFilters.price,
      condition: tradeFilters.condition,
      rarity: tradeFilters.rarity,
      franchise: tradeFilters.franchise,
      brand: tradeFilters.brand,
      productType: tradeFilters.productType,
      graded: tradeFilters.graded,
      availability: tradeFilters.availability,
      sellerRating: tradeFilters.sellerRating,
    }).filter((product) => isCardTradeListing(product))

    return [...results].sort((left, right) => {
      if (sort === 'price-asc') return Number(left.price ?? 0) - Number(right.price ?? 0)
      if (sort === 'price-desc') return Number(right.price ?? 0) - Number(left.price ?? 0)
      if (sort === 'rating') return Number(right.sellerRating ?? 0) - Number(left.sellerRating ?? 0)
      return new Date(right?.listedAt ?? 0) - new Date(left?.listedAt ?? 0)
    })
  }, [searchProducts, sort, tradeFilters])

  const myTradeStudioListings = useMemo(
    () =>
      filteredTradeListings.filter(
        (product) => Number(product?.sellerId ?? 0) === Number(currentUser?.id ?? 0),
      ),
    [currentUser?.id, filteredTradeListings],
  )

  const marketTradeStudioListings = useMemo(
    () =>
      filteredTradeListings.filter(
        (product) => Number(product?.sellerId ?? 0) !== Number(currentUser?.id ?? 0),
      ),
    [currentUser?.id, filteredTradeListings],
  )

  const tradeFacetGroups = useMemo(() => {
    const baseFacetGroups = getDynamicFacets({
      categoryId: 'cards',
      filters: tradeFilters,
    })

    const countWithFilters = (nextFilters) =>
      searchProducts({
        categoryId: 'cards',
        query: nextFilters.search,
        price: nextFilters.price,
        condition: nextFilters.condition,
        rarity: nextFilters.rarity,
        franchise: nextFilters.franchise,
        brand: nextFilters.brand,
        productType: nextFilters.productType,
        graded: nextFilters.graded,
        availability: nextFilters.availability,
        sellerRating: nextFilters.sellerRating,
      }).filter((product) => isCardTradeListing(product)).length

    return baseFacetGroups.map((group) => {
      const options = (group.options ?? [])
        .map((option) => {
          const selected = String(option.value) === String(tradeFilters[group.key])
          const count = countWithFilters({
            ...tradeFilters,
            [group.key]: option.value,
          })

          return {
            ...option,
            count,
            selected,
            disabled: count === 0 && !selected,
          }
        })
        .filter(
          (option) =>
            option.count > 0 ||
            option.selected ||
            String(option.value) === String(allFilterValue),
        )

      return {
        ...group,
        options,
      }
    })
  }, [allFilterValue, getDynamicFacets, searchProducts, tradeFilters])

  const updateTradeFilter = (key, value) => {
    setTradeFilters((previous) => ({
      ...previous,
      [key]: previous[key] === value ? allFilterValue : value,
    }))
  }

  const resetTradeFilters = () => {
    setTradeFilters({
      ...defaultSearchFilters,
      search: '',
    })
    setSort('newest')
  }

  const loadTradeData = useCallback(async () => {
    if (!isAuthenticated) return

    try {
      setLoadingTradeData(true)
      const [incoming, sent, deals] = await Promise.all([
        cardoraService.getTradeRequests({ scope: 'received' }),
        cardoraService.getTradeRequests({ scope: 'sent' }),
        cardoraService.getTradeDeals(),
      ])

      const normalizedIncoming = Array.isArray(incoming) ? incoming : []
      const normalizedSent = Array.isArray(sent) ? sent : []
      const prioritizedPrimary =
        tradeRequestScopeFromQuery === 'sent' ? normalizedSent : normalizedIncoming
      const prioritizedSecondary =
        tradeRequestScopeFromQuery === 'sent' ? normalizedIncoming : normalizedSent
      const queryMatchedTradeRequest =
        tradeRequestIdFromQuery > 0
          ? [...prioritizedPrimary, ...prioritizedSecondary].find(
              (request) => Number(request?.id ?? 0) === tradeRequestIdFromQuery,
            ) ?? null
          : null

      setIncomingRequests(normalizedIncoming)
      setSentRequests(normalizedSent)
      setMyTradeDeals(Array.isArray(deals) ? deals : [])
      setSelectedTradeRequest((previous) => {
        if (queryMatchedTradeRequest) {
          return queryMatchedTradeRequest
        }
        if (!previous?.id) return previous
        const updated = [...normalizedIncoming, ...normalizedSent].find(
          (request) => Number(request?.id ?? 0) === Number(previous.id),
        )
        return updated ?? previous
      })
    } catch (error) {
      setTradeActionError(error?.message || 'Could not load trade data right now.')
    } finally {
      setLoadingTradeData(false)
    }
  }, [isAuthenticated, tradeRequestIdFromQuery, tradeRequestScopeFromQuery])

  useEffect(() => {
    loadTradeData()
  }, [loadTradeData])

  useEffect(() => {
    const status = String(searchParams.get('trade_payment') || '').toLowerCase()
    const tradeDealIdFromQuery = Number(searchParams.get('trade_deal') || 0)
    if (tradeDealIdFromQuery > 0) {
      setSelectedTradeDealId(tradeDealIdFromQuery)
    }

    if (!status) return

    if (status === 'success') {
      setTradeActionSuccess(copy.paymentSuccess)
    }

    if (status === 'cancelled') {
      setTradeActionError(copy.paymentCancelled)
    }

    if (isAuthenticated) {
      loadTradeData()
    }
  }, [searchParams, copy.paymentCancelled, copy.paymentSuccess, isAuthenticated, loadTradeData])

  useEffect(() => {
    if (!tradeActionError && !tradeActionSuccess) return
    if (typeof window === 'undefined') return

    window.requestAnimationFrame(() => {
      tradeFeedbackRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    })
  }, [tradeActionError, tradeActionSuccess])

  const openTradeRequestDetails = (tradeRequest) => {
    setSelectedTradeRequest(tradeRequest ?? null)
  }

  const requestBundleCards = (tradeRequest, key) => {
    if (!tradeRequest) return []
    const metadata = tradeRequest.offered_metadata ?? {}
    const cards = Array.isArray(metadata?.[key]) ? metadata[key] : []
    return cards.filter(Boolean)
  }

  const resolveTradeCardMediaUrl = (mediaItem) => {
    if (!mediaItem) return ''

    const directUrl = String(mediaItem?.url ?? mediaItem?.preview_url ?? '').trim()
    if (directUrl) return directUrl

    const directPath = String(mediaItem?.path ?? '').trim()
    if (!directPath) return ''
    if (/^https?:\/\//i.test(directPath)) return directPath

    const normalized = directPath.replace(/^\/+/, '')
    if (normalized.startsWith('storage/')) return `/${normalized}`
    if (normalized.startsWith('uploads/')) return `/storage/${normalized}`
    return `/${normalized}`
  }

  const tradeCardMediaUrls = (card) => {
    const media = Array.isArray(card?.media) ? card.media : []
    return Array.from(
      new Set(media.map((item) => resolveTradeCardMediaUrl(item)).filter(Boolean)),
    )
  }

  const tradeCardPrimaryMedia = (card) => tradeCardMediaUrls(card)[0] ?? ''

  const requestImages = (tradeRequest) => {
    if (!tradeRequest) return []

    const explicitImages = Array.isArray(tradeRequest.offered_images)
      ? tradeRequest.offered_images.map((item) => String(item).trim()).filter(Boolean)
      : []

    const bundleImages = ['owner_bundle', 'proposer_bundle']
      .flatMap((key) => requestBundleCards(tradeRequest, key))
      .flatMap((card) => tradeCardMediaUrls(card))

    const listingImages = Array.isArray(tradeRequest?.listing?.product?.media)
      ? tradeRequest.listing.product.media
          .map((item) => resolveTradeCardMediaUrl(item))
          .filter(Boolean)
      : []

    return Array.from(new Set([...explicitImages, ...bundleImages, ...listingImages]))
  }

  const tradeRequestCardsPreview = (tradeRequest, key) => {
    const cards = requestBundleCards(tradeRequest, key)
    if (!cards.length) return locale === 'en' ? 'No cards selected yet.' : 'Δεν έχουν επιλεγεί κάρτες ακόμα.'

    const labels = cards
      .slice(0, 2)
      .map((card) => card?.title ?? `Listing #${card?.listing_id ?? '-'}`)
      .join(' • ')

    if (cards.length <= 2) return labels
    return `${labels} +${cards.length - 2}`
  }

  const formatTradeDateTime = (value) => {
    if (!value) return '-'
    return new Date(value).toLocaleString(locale === 'en' ? 'en-US' : 'el-GR')
  }

  const tradeStatusLabel = (status) => {
    const normalized = String(status || '').toLowerCase()
    if (normalized === 'pending') return locale === 'en' ? 'Pending' : 'Σε αναμονή'
    if (normalized === 'accepted') return locale === 'en' ? 'Accepted' : 'Αποδεκτό'
    if (normalized === 'rejected') return locale === 'en' ? 'Rejected' : 'Απορρίφθηκε'
    if (normalized === 'cancelled') return locale === 'en' ? 'Cancelled' : 'Ακυρώθηκε'
    if (normalized === 'pending_funding') return locale === 'en' ? 'Pending funding' : 'Αναμονή πληρωμής'
    if (normalized === 'funded') return locale === 'en' ? 'Funded' : 'Πληρωμένο'
    if (normalized === 'settling') return locale === 'en' ? 'Settling' : 'Σε ολοκλήρωση'
    if (normalized === 'settled') return locale === 'en' ? 'Settled' : 'Ολοκληρώθηκε'
    if (normalized === 'disputed') return locale === 'en' ? 'Disputed' : 'Σε dispute'
    if (normalized === 'resolved') return locale === 'en' ? 'Resolved' : 'Επιλύθηκε'
    return status || '-'
  }

  const tradeStatusTone = (status) => {
    const normalized = String(status || '').toLowerCase()
    if (['accepted', 'funded', 'settled', 'resolved'].includes(normalized)) return 'success'
    if (normalized === 'pending' || normalized === 'pending_funding' || normalized === 'settling') return 'warning'
    if (normalized === 'rejected' || normalized === 'cancelled') return 'muted'
    if (normalized === 'disputed') return 'danger'
    return 'info'
  }

  const handleTradeStudioRequestSubmit = async (payload) => {
    try {
      setSubmittingTradeRequest(true)
      setTradeActionError('')
      setTradeActionSuccess('')
      await cardoraService.createTradeRequest(payload)
      setTradeActionSuccess(
        locale === 'en' ? 'Trade request sent successfully.' : 'Το trade αίτημα στάλθηκε επιτυχώς.',
      )
      await loadTradeData()
    } catch (error) {
      const message = error?.message || 'Could not submit trade request.'
      setTradeActionError(message)
      throw new Error(message)
    } finally {
      setSubmittingTradeRequest(false)
    }
  }

  const handleAcceptRequest = async (tradeRequestId) => {
    try {
      setTradeActionError('')
      setTradeActionSuccess('')
      await cardoraService.acceptTradeRequest(tradeRequestId)
      setTradeActionSuccess(locale === 'en' ? 'Trade request accepted.' : 'Το trade αίτημα έγινε αποδεκτό.')
      await loadTradeData()
    } catch (error) {
      setTradeActionError(error?.message || 'Could not accept trade request.')
    }
  }

  const handleRejectRequest = async (tradeRequestId) => {
    try {
      setTradeActionError('')
      setTradeActionSuccess('')
      await cardoraService.rejectTradeRequest(tradeRequestId)
      setTradeActionSuccess(locale === 'en' ? 'Trade request rejected.' : 'Το trade αίτημα απορρίφθηκε.')
      await loadTradeData()
    } catch (error) {
      setTradeActionError(error?.message || 'Could not reject trade request.')
    }
  }

  const handleCancelRequest = async (tradeRequestId) => {
    try {
      setTradeActionError('')
      setTradeActionSuccess('')
      await cardoraService.cancelTradeRequest(tradeRequestId)
      setTradeActionSuccess(locale === 'en' ? 'Trade request cancelled.' : 'Το trade αίτημα ακυρώθηκε.')
      await loadTradeData()
    } catch (error) {
      setTradeActionError(error?.message || 'Could not cancel trade request.')
    }
  }

  const handleTradeCheckout = async (tradeDealId) => {
    try {
      setTradeActionError('')
      const response = await cardoraService.createTradeCheckoutSession(tradeDealId)
      if (response?.checkout_url) {
        window.location.href = response.checkout_url
        return
      }
      setTradeActionSuccess(
        locale === 'en'
          ? 'Your deposit is already paid for this trade.'
          : 'Το deposit σου είναι ήδη πληρωμένο για αυτό το trade.',
      )
    } catch (error) {
      setTradeActionError(error?.message || 'Could not open Stripe checkout.')
    }
  }

  const handleTradeRelease = async (tradeDealId) => {
    try {
      setTradeActionError('')
      await cardoraService.confirmTradeRelease(tradeDealId)
      setTradeActionSuccess(
        locale === 'en'
          ? 'Release confirmation recorded.'
          : 'Η επιβεβαίωση release καταχωρήθηκε.',
      )
      await loadTradeData()
    } catch (error) {
      setTradeActionError(error?.message || 'Could not confirm release.')
    }
  }

  const handleTradeDispute = async (tradeDealId) => {
    const reason = window.prompt(
      locale === 'en'
        ? 'Describe the issue for Cardora review:'
        : 'Περιέγραψε το πρόβλημα για έλεγχο από την Cardora:',
    )

    if (!reason) return

    try {
      setTradeActionError('')
      await cardoraService.openTradeDispute(tradeDealId, { reason })
      setTradeActionSuccess(
        locale === 'en'
          ? 'Dispute opened. Cardora will review it manually.'
          : 'Άνοιξε dispute. Η Cardora θα το ελέγξει χειροκίνητα.',
      )
      await loadTradeData()
    } catch (error) {
      setTradeActionError(error?.message || 'Could not open dispute.')
    }
  }

  const tradeDealRole = (deal) => {
    const ownerId = Number(deal.owner_user_id ?? deal.owner?.id ?? 0)
    const proposerId = Number(deal.proposer_user_id ?? deal.proposer?.id ?? 0)
    const me = Number(currentUser?.id ?? 0)

    if (ownerId === me) return 'owner'
    if (proposerId === me) return 'proposer'
    return 'viewer'
  }

  const selectedTradeDeal =
    myTradeDeals.find((deal) => Number(deal?.id ?? 0) === Number(selectedTradeDealId ?? 0)) ?? null
  const selectedDealRole = selectedTradeDeal ? tradeDealRole(selectedTradeDeal) : 'viewer'
  const selectedDealIsOwner = selectedDealRole === 'owner'
  const selectedDealIsProposer = selectedDealRole === 'proposer'
  const selectedDealIsPaid = selectedTradeDeal
    ? selectedDealIsOwner
      ? Boolean(selectedTradeDeal.owner_paid_at)
      : Boolean(selectedTradeDeal.proposer_paid_at)
    : false
  const selectedDealIsReleased = selectedTradeDeal
    ? selectedDealIsOwner
      ? Boolean(selectedTradeDeal.owner_released_at)
      : Boolean(selectedTradeDeal.proposer_released_at)
    : false
  const selectedDealCanPay =
    selectedTradeDeal &&
    selectedTradeDeal.status === 'pending_funding' &&
    !selectedDealIsPaid &&
    (selectedDealIsOwner || selectedDealIsProposer)
  const selectedDealIsFunded = selectedTradeDeal
    ? Boolean(
        selectedTradeDeal.is_funded ??
          (selectedTradeDeal.owner_paid_at && selectedTradeDeal.proposer_paid_at),
      )
    : false
  const selectedDealCanRelease =
    selectedTradeDeal &&
    selectedDealIsFunded &&
    !['settled', 'resolved', 'cancelled', 'disputed'].includes(String(selectedTradeDeal.status)) &&
    !selectedDealIsReleased &&
    (selectedDealIsOwner || selectedDealIsProposer)

  const renderTradeBundleCard = (card, keyPrefix, index) => {
    const cardTitle = card?.title ?? `Listing #${card?.listing_id ?? '-'}`
    const cardImage = tradeCardPrimaryMedia(card)
    const cardCondition = card?.condition || '-'
    const cardRarity = card?.rarity || '-'
    const cardSeller = card?.seller_name || '-'

    return (
      <article
        key={`${keyPrefix}-${card?.listing_id ?? card?.product_id ?? cardTitle}-${index}`}
        className="rounded-xl border border-white/10 bg-[#0b1629] p-2.5"
      >
        <div className="relative aspect-[3/4] overflow-hidden rounded-lg border border-white/10 bg-[#081321]">
          {cardImage ? (
            <img
              src={cardImage}
              alt={cardTitle}
              className="h-full w-full object-cover"
              loading="lazy"
            />
          ) : (
            <div className="flex h-full w-full items-center justify-center px-3 text-center text-[11px] text-white/55">
              {locale === 'en' ? 'No image available' : 'Δεν υπάρχει διαθέσιμη εικόνα'}
            </div>
          )}
        </div>
        <p className="mt-2 text-sm font-semibold text-white">{cardTitle}</p>
        <p className="mt-1 text-[11px] text-white/68">{cardCondition} · {cardRarity}</p>
        <p className="mt-1 text-[11px] text-white/58">{cardSeller}</p>
        <p className="mt-1 text-xs font-semibold text-gold-100">
          {formatCurrency(Number(card?.declared_value ?? 0))}
        </p>
      </article>
    )
  }

  const renderListingCard = (listing) => {
    const isOwnListing = Number(listing?.sellerId ?? 0) === Number(currentUser?.id ?? 0)
    const openStudio = () => {
      if (typeof document === 'undefined') return
      const section = document.getElementById('trade-studio')
      if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' })
      }
    }

    return (
      <ProductCard
        key={listing.id}
        product={listing}
        renderFooter={() => (
          <div className="flex flex-wrap gap-2">
            {!isAuthenticated ? (
              <Button as={Link} to="/eisodos" size="sm" variant="secondary">
                {copy.loginToTrade}
              </Button>
            ) : (
              <Button size="sm" onClick={openStudio}>
                {isOwnListing
                  ? locale === 'en'
                    ? 'Add to your side'
                    : 'Πρόσθεσε στη δική σου πλευρά'
                  : locale === 'en'
                    ? 'Add to target side'
                    : 'Πρόσθεσε στην πλευρά στόχου'}
              </Button>
            )}
            <Button as={Link} to={`/proion/${listing.slug}`} size="sm" variant="ghost">
              {locale === 'en' ? 'Open listing' : 'Άνοιγμα listing'}
            </Button>
            {!isOwnListing ? (
              <p className="w-full text-xs text-mist">
                {copy.listingValue}: {formatCurrency(Number(listing.price ?? 0))}
              </p>
            ) : (
              <p className="w-full text-xs text-mist">
                {copy.ownListing}
              </p>
            )}
          </div>
        )}
      />
    )
  }

  const pageTitle = studioMode ? (locale === 'en' ? 'Trades Studio' : 'Trade Studio') : copy.title
  const pageDescription = studioMode
    ? locale === 'en'
      ? 'Manage your swap requests, deposit payments, releases and issue reports.'
      : 'Διαχειρίσου προτάσεις, πληρωμές εγγύησης, release και δηλώσεις προβλήματος.'
    : copy.description

  return (
    <div className="container pb-16">
      <CardSurface className="p-5 sm:p-6">
        <div className="flex flex-wrap items-center gap-2">
          <Badge tone="gold">{copy.badge}</Badge>
          <Badge tone="warning">{copy.noAutoRelease}</Badge>
        </div>
        <h1 className="mt-4 font-display text-3xl text-white sm:text-5xl">{pageTitle}</h1>
        <p className="mt-3 max-w-4xl text-sm leading-7 text-mist">{pageDescription}</p>
        <p className="mt-4 rounded-xl border border-gold-300/20 bg-gold-300/10 px-4 py-3 text-sm text-gold-100">
          {copy.connectedRequired}
        </p>
        <p className="mt-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-white/80">
          {copy.safetyRules}
        </p>
      </CardSurface>

      {tradeActionError || tradeActionSuccess ? (
        <div ref={tradeFeedbackRef} id="trade-feedback" className="mt-6 space-y-3">
          {tradeActionError ? (
            <div className="rounded-xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
              {tradeActionError}
            </div>
          ) : null}

          {tradeActionSuccess ? (
            <div className="rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">
              {tradeActionSuccess}
            </div>
          ) : null}
        </div>
      ) : null}

      {isAuthenticated && !studioMode ? (
        <TradeSwapStudio
          locale={locale}
          myListings={myTradeStudioListings}
          marketListings={marketTradeStudioListings}
          submitting={submittingTradeRequest}
          onSubmit={handleTradeStudioRequestSubmit}
        />
      ) : null}

      {!studioMode ? (
        <section className="mt-10 grid gap-6 xl:grid-cols-[290px,1fr]">
          <div className="hidden xl:block">
            <FilterSidebar
              facetGroups={tradeFacetGroups}
              filters={tradeFilters}
              onChange={updateTradeFilter}
              onReset={resetTradeFilters}
            />
          </div>

          <div>
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
              <h2 className="font-display text-2xl text-white sm:text-3xl">Trade Listings</h2>
              <span className="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-white/72">
                {formatNumber(filteredTradeListings.length)}
              </span>
            </div>

            <CardSurface className="mb-5 p-3.5">
              <div className="flex flex-wrap gap-3">
                <Input
                  value={tradeFilters.search}
                  onChange={(event) =>
                    setTradeFilters((previous) => ({ ...previous, search: event.target.value }))
                  }
                  placeholder={copy.searchTradeListings}
                  className="w-full sm:min-w-[260px] sm:flex-1"
                />
                <Button
                  variant="secondary"
                  size="sm"
                  className="xl:hidden"
                  onClick={() => setMobileFiltersOpen(true)}
                >
                  <Filter className="h-4 w-4" />
                  {copy.filters}
                </Button>
                <select
                  value={sort}
                  onChange={(event) => setSort(event.target.value)}
                  className="w-full rounded-xl border border-white/10 bg-white/5 px-3.5 py-2.5 text-[13px] text-white sm:w-auto"
                >
                  <option value="newest">{copy.sortNewest}</option>
                  <option value="price-asc">{copy.sortPriceAsc}</option>
                  <option value="price-desc">{copy.sortPriceDesc}</option>
                  <option value="rating">{copy.sortRating}</option>
                </select>
                <Button variant="ghost" size="sm" className="w-full sm:w-auto" onClick={resetTradeFilters}>
                  {copy.clearFilters}
                </Button>
              </div>
            </CardSurface>

            {filteredTradeListings.length ? (
              <div className="grid gap-5 lg:grid-cols-2 xl:grid-cols-3">
                {filteredTradeListings.map(renderListingCard)}
              </div>
            ) : (
              <CardSurface>{copy.noTradeListings}</CardSurface>
            )}
          </div>
        </section>
      ) : null}

      {isAuthenticated && studioMode ? (
        <>
          <CardSurface
            className="featured-glow mt-12 border-white/12 bg-[radial-gradient(circle_at_12%_18%,rgba(56,189,248,0.16),transparent_42%),radial-gradient(circle_at_88%_80%,rgba(244,114,182,0.14),transparent_44%),linear-gradient(145deg,rgba(8,20,40,0.95),rgba(9,16,34,0.92))] p-4 sm:p-6"
            hover={false}
          >
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p className="text-[11px] uppercase tracking-[0.24em] text-white/58">
                  {locale === 'en' ? 'Trade inbox' : 'Trade inbox'}
                </p>
                <h2 className="mt-2 font-display text-2xl text-white sm:text-3xl">
                  {locale === 'en' ? 'All requests in one command center' : 'Όλα τα αιτήματα σε ένα command center'}
                </h2>
              </div>
              <Button as={Link} to="/kliroseis" size="sm" variant="secondary">
                {locale === 'en' ? 'Open trades page' : 'Μετάβαση στο trades page'}
              </Button>
            </div>
            <div className="mt-4 grid gap-3 sm:grid-cols-3">
              <div className="rounded-2xl border border-white/12 bg-white/5 px-3.5 py-3">
                <p className="text-[10px] uppercase tracking-[0.22em] text-white/45">{copy.incoming}</p>
                <p className="mt-1 text-2xl font-semibold text-white">{formatNumber(incomingRequests.length)}</p>
              </div>
              <div className="rounded-2xl border border-white/12 bg-white/5 px-3.5 py-3">
                <p className="text-[10px] uppercase tracking-[0.22em] text-white/45">{copy.sent}</p>
                <p className="mt-1 text-2xl font-semibold text-white">{formatNumber(sentRequests.length)}</p>
              </div>
              <div className="rounded-2xl border border-white/12 bg-white/5 px-3.5 py-3">
                <p className="text-[10px] uppercase tracking-[0.22em] text-white/45">{copy.deals}</p>
                <p className="mt-1 text-2xl font-semibold text-white">{formatNumber(myTradeDeals.length)}</p>
              </div>
            </div>
          </CardSurface>

          <section className="mt-6 grid gap-6 xl:grid-cols-2">
          <CardSurface className="border-sky-300/20 bg-[radial-gradient(circle_at_15%_8%,rgba(56,189,248,0.12),transparent_44%),linear-gradient(160deg,rgba(9,20,39,0.98),rgba(9,22,45,0.95))] p-4 sm:p-5">
            <h3 className="font-display text-xl text-white sm:text-2xl">{copy.incoming}</h3>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              {incomingRequests.length ? (
                incomingRequests.map((item) => {
                  const metadata = item?.offered_metadata ?? {}
                  const offeredValue = Number(item?.offered_value ?? 0)
                  const requestedValue = Number(metadata?.target_total ?? item?.listing?.price ?? 0)
                  const valueGap = offeredValue - requestedValue
                  const offeredCardsCount = Array.isArray(item?.offered_listing_ids)
                    ? item.offered_listing_ids.length
                    : 0
                  const targetCardsCount = Array.isArray(item?.target_listing_ids)
                    ? item.target_listing_ids.length
                    : 0
                  const requesterName =
                    item?.requester?.display_name ??
                    item?.requester?.name ??
                    `User #${item?.requester_user_id ?? '-'}`
                  const listingTitle =
                    item?.listing?.product?.title ?? item?.listing?.title ?? `Listing #${item?.listing_id}`

                  return (
                    <div
                      key={item.id}
                      className={`rounded-2xl border p-3.5 ${
                        Number(selectedTradeRequest?.id ?? 0) === Number(item.id)
                          ? 'border-gold-300/45 bg-gold-300/10'
                          : 'border-white/10 bg-white/5'
                      }`}
                    >
                      <div className="flex flex-wrap items-start justify-between gap-2">
                        <p className="text-sm font-semibold text-white">{item.offered_title}</p>
                        <Badge tone={tradeStatusTone(item.status)}>{tradeStatusLabel(item.status)}</Badge>
                      </div>

                      <p className="mt-1 text-xs text-white/72">{listingTitle}</p>
                      <p className="mt-1 text-[11px] text-white/45">
                        {locale === 'en' ? 'Request' : 'Αίτημα'} #{item.id}
                      </p>
                      <p className="mt-2 text-xs text-mist">
                        {copy.offeredBy}: {requesterName}
                      </p>

                      <div className="mt-3 grid grid-cols-2 gap-2">
                        <div className="rounded-xl border border-white/10 bg-[#0c1628] px-2.5 py-2">
                          <p className="text-[10px] uppercase tracking-[0.16em] text-white/45">{copy.offeredValue}</p>
                          <p className="mt-1 text-xs font-semibold text-white">
                            {formatCurrency(offeredValue)}
                          </p>
                        </div>
                        <div className="rounded-xl border border-white/10 bg-[#0c1628] px-2.5 py-2">
                          <p className="text-[10px] uppercase tracking-[0.16em] text-white/45">
                            {locale === 'en' ? 'Requested value' : 'Ζητούμενη αξία'}
                          </p>
                          <p className="mt-1 text-xs font-semibold text-white">
                            {formatCurrency(requestedValue)}
                          </p>
                        </div>
                      </div>

                      <p className="mt-2 text-[11px] text-mist">
                        {locale === 'en' ? 'Cards' : 'Κάρτες'}: {offeredCardsCount} → {targetCardsCount} · {copy.submitted}:{' '}
                        {formatTradeDateTime(item?.created_at)}
                      </p>
                      <p className="mt-2 text-[11px] text-white/70">
                        {locale === 'en' ? 'Your side' : 'Η πλευρά σου'}: {tradeRequestCardsPreview(item, 'owner_bundle')}
                      </p>
                      <p className="mt-1 text-[11px] text-white/70">
                        {locale === 'en' ? 'Other side' : 'Η άλλη πλευρά'}:{' '}
                        {tradeRequestCardsPreview(item, 'proposer_bundle')}
                      </p>
                      <p className="mt-1 text-[11px] text-white/70">
                        {locale === 'en' ? 'Value gap' : 'Διαφορά αξίας'}:{' '}
                        <span className={valueGap >= 0 ? 'text-emerald-200' : 'text-rose-200'}>
                          {valueGap >= 0 ? '+' : ''}
                          {formatCurrency(valueGap)}
                        </span>
                      </p>

                      <div className="mt-3 flex flex-wrap gap-2">
                        <Button size="sm" variant="ghost" onClick={() => openTradeRequestDetails(item)}>
                          {copy.viewDetails}
                        </Button>
                        {item.status === 'pending' ? (
                          <>
                            <Button size="sm" onClick={() => handleAcceptRequest(item.id)}>
                              {copy.accept}
                            </Button>
                            <Button
                              size="sm"
                              variant="ghost"
                              onClick={() => handleRejectRequest(item.id)}
                            >
                              {copy.reject}
                            </Button>
                          </>
                        ) : null}
                      </div>
                    </div>
                  )
                })
              ) : (
                <p className="text-sm text-mist">{copy.noIncoming}</p>
              )}
            </div>
          </CardSurface>

          <CardSurface className="border-fuchsia-300/20 bg-[radial-gradient(circle_at_85%_10%,rgba(217,70,239,0.11),transparent_42%),linear-gradient(160deg,rgba(10,20,42,0.98),rgba(8,18,38,0.94))] p-4 sm:p-5">
            <h3 className="font-display text-xl text-white sm:text-2xl">{copy.sent}</h3>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              {sentRequests.length ? (
                sentRequests.map((item) => {
                  const metadata = item?.offered_metadata ?? {}
                  const offeredValue = Number(item?.offered_value ?? 0)
                  const requestedValue = Number(metadata?.target_total ?? item?.listing?.price ?? 0)
                  const valueGap = offeredValue - requestedValue
                  const offeredCardsCount = Array.isArray(item?.offered_listing_ids)
                    ? item.offered_listing_ids.length
                    : 0
                  const targetCardsCount = Array.isArray(item?.target_listing_ids)
                    ? item.target_listing_ids.length
                    : 0
                  const ownerName =
                    item?.listing_owner?.display_name ??
                    item?.listing_owner?.name ??
                    `User #${item?.listing_owner_user_id ?? '-'}`
                  const listingTitle =
                    item?.listing?.product?.title ?? item?.listing?.title ?? `Listing #${item?.listing_id}`

                  return (
                    <div
                      key={item.id}
                      className={`rounded-2xl border p-3.5 ${
                        Number(selectedTradeRequest?.id ?? 0) === Number(item.id)
                          ? 'border-gold-300/45 bg-gold-300/10'
                          : 'border-white/10 bg-white/5'
                      }`}
                    >
                      <div className="flex flex-wrap items-start justify-between gap-2">
                        <p className="text-sm font-semibold text-white">{item.offered_title}</p>
                        <Badge tone={tradeStatusTone(item.status)}>{tradeStatusLabel(item.status)}</Badge>
                      </div>

                      <p className="mt-1 text-xs text-white/72">{listingTitle}</p>
                      <p className="mt-1 text-[11px] text-white/45">
                        {locale === 'en' ? 'Request' : 'Αίτημα'} #{item.id}
                      </p>
                      <p className="mt-2 text-xs text-mist">
                        {locale === 'en' ? 'To collector' : 'Προς συλλέκτη'}: {ownerName}
                      </p>

                      <div className="mt-3 grid grid-cols-2 gap-2">
                        <div className="rounded-xl border border-white/10 bg-[#0c1628] px-2.5 py-2">
                          <p className="text-[10px] uppercase tracking-[0.16em] text-white/45">{copy.offeredValue}</p>
                          <p className="mt-1 text-xs font-semibold text-white">
                            {formatCurrency(offeredValue)}
                          </p>
                        </div>
                        <div className="rounded-xl border border-white/10 bg-[#0c1628] px-2.5 py-2">
                          <p className="text-[10px] uppercase tracking-[0.16em] text-white/45">
                            {locale === 'en' ? 'Requested value' : 'Ζητούμενη αξία'}
                          </p>
                          <p className="mt-1 text-xs font-semibold text-white">
                            {formatCurrency(requestedValue)}
                          </p>
                        </div>
                      </div>

                      <p className="mt-2 text-[11px] text-mist">
                        {locale === 'en' ? 'Cards' : 'Κάρτες'}: {offeredCardsCount} → {targetCardsCount} · {copy.submitted}:{' '}
                        {formatTradeDateTime(item?.created_at)}
                      </p>
                      <p className="mt-2 text-[11px] text-white/70">
                        {locale === 'en' ? 'You offered' : 'Πρόσφερες'}: {tradeRequestCardsPreview(item, 'proposer_bundle')}
                      </p>
                      <p className="mt-1 text-[11px] text-white/70">
                        {locale === 'en' ? 'You asked for' : 'Ζήτησες'}: {tradeRequestCardsPreview(item, 'owner_bundle')}
                      </p>
                      <p className="mt-1 text-[11px] text-white/70">
                        {locale === 'en' ? 'Value gap' : 'Διαφορά αξίας'}:{' '}
                        <span className={valueGap >= 0 ? 'text-emerald-200' : 'text-rose-200'}>
                          {valueGap >= 0 ? '+' : ''}
                          {formatCurrency(valueGap)}
                        </span>
                      </p>

                      <div className="mt-3 flex flex-wrap gap-2">
                        <Button size="sm" variant="ghost" onClick={() => openTradeRequestDetails(item)}>
                          {copy.viewDetails}
                        </Button>
                        {item.status === 'pending' ? (
                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => handleCancelRequest(item.id)}
                          >
                            {copy.cancel}
                          </Button>
                        ) : null}
                      </div>
                    </div>
                  )
                })
              ) : (
                <p className="text-sm text-mist">{copy.noSent}</p>
              )}
            </div>
          </CardSurface>

          </section>

          <CardSurface className="mt-6 border-emerald-300/18 bg-[radial-gradient(circle_at_50%_0%,rgba(16,185,129,0.1),transparent_45%),linear-gradient(160deg,rgba(9,22,39,0.98),rgba(8,18,36,0.94))] p-4 sm:p-5">
            <h3 className="font-display text-xl text-white sm:text-2xl">{copy.deals}</h3>
            {loadingTradeData ? <p className="mt-4 text-sm text-mist">{copy.loading}</p> : null}
            <div className="mt-3">
              <Button size="sm" variant="ghost" onClick={loadTradeData}>
                {copy.refreshDeals}
              </Button>
            </div>
            <div className="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              {myTradeDeals.length ? (
                myTradeDeals.map((deal) => {
                  const role = tradeDealRole(deal)
                  const isOwner = role === 'owner'
                  const isProposer = role === 'proposer'
                  const isPaid = isOwner ? Boolean(deal.owner_paid_at) : Boolean(deal.proposer_paid_at)
                  const isReleased = isOwner
                    ? Boolean(deal.owner_released_at)
                    : Boolean(deal.proposer_released_at)
                  const isFunded = Boolean(
                    deal.is_funded ?? (deal.owner_paid_at && deal.proposer_paid_at),
                  )
                  const canPay = deal.status === 'pending_funding' && !isPaid && (isOwner || isProposer)
                  const canRelease =
                    isFunded &&
                    !['settled', 'resolved', 'cancelled', 'disputed'].includes(String(deal.status)) &&
                    !isReleased &&
                    (isOwner || isProposer)

                  return (
                    <div
                      key={deal.id}
                      className={`rounded-2xl border p-3.5 ${
                        Number(selectedTradeDealId ?? 0) === Number(deal.id)
                          ? 'border-gold-300/40 bg-gold-300/10'
                          : 'border-white/10 bg-white/5'
                      }`}
                    >
                      <div className="flex flex-wrap items-start justify-between gap-2">
                        <p className="text-sm font-semibold text-white">Deal #{deal.id}</p>
                        <Badge tone={tradeStatusTone(deal.status)}>{tradeStatusLabel(deal.status)}</Badge>
                      </div>

                      <p className="mt-1 text-xs text-mist">
                        {copy.role}: {isOwner ? copy.owner : isProposer ? copy.proposer : copy.you}
                      </p>

                      <div className="mt-3 grid grid-cols-2 gap-2">
                        <div className="rounded-xl border border-white/10 bg-[#0c1628] px-2.5 py-2">
                          <p className="text-[10px] uppercase tracking-[0.16em] text-white/45">{copy.deposit}</p>
                          <p className="mt-1 text-xs font-semibold text-white">
                            {formatCurrency(Number(deal.deposit_amount ?? 0))}
                          </p>
                        </div>
                        <div className="rounded-xl border border-white/10 bg-[#0c1628] px-2.5 py-2">
                          <p className="text-[10px] uppercase tracking-[0.16em] text-white/45">{copy.paymentState}</p>
                          <p className="mt-1 text-xs font-semibold text-white">
                            {isPaid ? copy.paid : copy.unpaid}
                          </p>
                        </div>
                      </div>

                      <div className="mt-2 rounded-xl border border-white/10 bg-[#0c1628] px-2.5 py-2 text-xs text-white/80">
                        <p>{copy.ownerPayment}: {deal.owner_paid_at ? copy.paid : copy.unpaid}</p>
                        <p className="mt-1">{copy.proposerPayment}: {deal.proposer_paid_at ? copy.paid : copy.unpaid}</p>
                        <p className="mt-1">{copy.releaseState}: {isReleased ? copy.releasedState : copy.waiting}</p>
                      </div>

                      <p className="mt-2 text-[11px] text-mist">
                        {copy.ownerNet}: {formatCurrency(Number(deal.owner_net_amount ?? 0))} · {copy.proposerNet}:{' '}
                        {formatCurrency(Number(deal.proposer_net_amount ?? 0))}
                      </p>

                      <div className="mt-3 flex flex-wrap gap-2">
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => setSelectedTradeDealId(Number(deal.id))}
                        >
                          {copy.viewDeal}
                        </Button>
                        {canPay ? (
                          <Button size="sm" onClick={() => handleTradeCheckout(deal.id)}>
                            {copy.openStripe}
                          </Button>
                        ) : null}
                        {canRelease ? (
                          <Button size="sm" onClick={() => handleTradeRelease(deal.id)}>
                            {copy.release}
                          </Button>
                        ) : null}
                        {['funded', 'settling'].includes(deal.status) && (isOwner || isProposer) ? (
                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => handleTradeDispute(deal.id)}
                          >
                            {copy.dispute}
                          </Button>
                        ) : null}
                      </div>
                    </div>
                  )
                })
              ) : (
                <p className="text-sm text-mist">{copy.noDeals}</p>
              )}
            </div>
          </CardSurface>

          <Drawer
            open={Boolean(selectedTradeDeal)}
            title={selectedTradeDeal ? `Deal #${selectedTradeDeal.id}` : copy.viewDeal}
            onClose={() => setSelectedTradeDealId(null)}
            className="max-w-2xl sm:max-w-3xl"
          >
            {selectedTradeDeal ? (
              <div>
                <p className="text-sm text-mist">
                  {copy.status}: {selectedTradeDeal.status}
                </p>

                <div className="mt-5 grid gap-4 md:grid-cols-2">
                  <div className="rounded-xl border border-white/10 bg-white/5 p-4">
                    <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{copy.targetListing}</p>
                    <p className="mt-2 text-sm font-semibold text-white">
                      {selectedTradeDeal.listing?.product?.title ??
                        selectedTradeDeal.listing?.title ??
                        `Listing #${selectedTradeDeal.listing_id}`}
                    </p>
                    <p className="mt-2 text-xs text-mist">
                      {copy.deposit}: {formatCurrency(Number(selectedTradeDeal.deposit_amount ?? 0))}
                    </p>
                    <p className="mt-2 text-xs text-mist">
                      {copy.role}: {selectedDealIsOwner ? copy.owner : selectedDealIsProposer ? copy.proposer : copy.you}
                    </p>
                  </div>
                  <div className="rounded-xl border border-white/10 bg-white/5 p-4">
                    <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{copy.counterparty}</p>
                    <p className="mt-2 text-sm font-semibold text-white">
                      {selectedDealIsOwner
                        ? selectedTradeDeal.proposer?.display_name ?? selectedTradeDeal.proposer?.name ?? '-'
                        : selectedTradeDeal.owner?.display_name ?? selectedTradeDeal.owner?.name ?? '-'}
                    </p>
                    <p className="mt-2 text-xs text-mist">
                      {copy.paymentState}:{' '}
                      {selectedDealIsPaid ? copy.paid : copy.unpaid}
                    </p>
                    <p className="mt-2 text-xs text-mist">
                      {copy.ownerPayment}: {selectedTradeDeal.owner_paid_at ? copy.paid : copy.unpaid}
                    </p>
                    <p className="mt-2 text-xs text-mist">
                      {copy.proposerPayment}: {selectedTradeDeal.proposer_paid_at ? copy.paid : copy.unpaid}
                    </p>
                    <p className="mt-2 text-xs text-mist">
                      {copy.releaseState}:{' '}
                      {selectedDealIsReleased ? copy.releasedState : copy.waiting}
                    </p>
                  </div>
                </div>

                <div className="mt-4 flex flex-wrap gap-2">
                  {selectedDealCanPay ? (
                    <Button size="sm" onClick={() => handleTradeCheckout(selectedTradeDeal.id)}>
                      {copy.openStripe}
                    </Button>
                  ) : null}
                  {selectedDealCanRelease ? (
                    <Button size="sm" onClick={() => handleTradeRelease(selectedTradeDeal.id)}>
                      {copy.release}
                    </Button>
                  ) : null}
                  {['funded', 'settling'].includes(selectedTradeDeal.status) &&
                  (selectedDealIsOwner || selectedDealIsProposer) ? (
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => handleTradeDispute(selectedTradeDeal.id)}
                    >
                      {copy.dispute}
                    </Button>
                  ) : null}
                </div>
              </div>
            ) : null}
          </Drawer>

          <Drawer
            open={Boolean(selectedTradeRequest)}
            title={copy.details}
            onClose={() => setSelectedTradeRequest(null)}
            className="max-w-2xl sm:max-w-3xl"
          >
            {selectedTradeRequest ? (
              <div>
                <p className="text-sm text-mist">
                  {copy.status}: {selectedTradeRequest.status}
                </p>

                <div className="mt-5 grid gap-4 md:grid-cols-2">
                  <div className="rounded-xl border border-white/10 bg-white/5 p-4">
                    <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{copy.targetListing}</p>
                    <p className="mt-2 text-sm font-semibold text-white">
                      {selectedTradeRequest.listing?.product?.title ??
                        selectedTradeRequest.listing?.title ??
                        `Listing #${selectedTradeRequest.listing_id}`}
                    </p>
                    <p className="mt-2 text-xs text-mist">
                      {copy.listingValue}:{' '}
                      {formatCurrency(Number(selectedTradeRequest.listing?.price ?? 0))}
                    </p>
                    <p className="mt-2 text-xs text-mist">
                      {copy.offeredBy}:{' '}
                      {selectedTradeRequest.requester?.display_name ??
                        selectedTradeRequest.requester?.name ??
                        `User #${selectedTradeRequest.requester_user_id}`}
                    </p>
                    <p className="mt-2 text-xs text-mist">
                      {copy.submitted}:{' '}
                      {selectedTradeRequest.created_at
                        ? new Date(selectedTradeRequest.created_at).toLocaleString(
                            locale === 'en' ? 'en-US' : 'el-GR',
                          )
                        : '-'}
                    </p>

                    <div className="mt-3 rounded-xl border border-white/10 bg-[#0c1524] p-3">
                      <p className="text-[10px] uppercase tracking-[0.2em] text-white/50">
                        {locale === 'en' ? 'Cards you receive' : 'Κάρτες που θα πάρεις'} ({requestBundleCards(selectedTradeRequest, 'owner_bundle').length})
                      </p>
                      {requestBundleCards(selectedTradeRequest, 'owner_bundle').length ? (
                        <div className="mt-2 grid gap-2 sm:grid-cols-2">
                          {requestBundleCards(selectedTradeRequest, 'owner_bundle').map((card, index) =>
                            renderTradeBundleCard(card, 'owner-bundle-detail', index),
                          )}
                        </div>
                      ) : (
                        <p className="mt-2 text-xs text-mist">
                          {locale === 'en' ? 'No target cards found for this request.' : 'Δεν βρέθηκαν κάρτες στόχου για αυτό το αίτημα.'}
                        </p>
                      )}
                    </div>
                  </div>

                  <div className="rounded-xl border border-white/10 bg-white/5 p-4">
                    <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{copy.yourCard}</p>
                    <p className="mt-2 text-sm font-semibold text-white">{selectedTradeRequest.offered_title}</p>
                    <p className="mt-2 text-xs text-mist">
                      {copy.offeredCondition}: {selectedTradeRequest.offered_condition || '-'}
                    </p>
                    <p className="mt-2 text-xs text-mist">
                      {copy.offeredValue}: {formatCurrency(Number(selectedTradeRequest.offered_value ?? 0))}
                    </p>
                    <p className="mt-2 text-xs text-mist">
                      {copy.notes}:{' '}
                      {selectedTradeRequest.request_message ||
                        selectedTradeRequest.offered_description ||
                        copy.noNotes}
                    </p>
                    <p className="mt-2 text-xs text-mist">
                      {copy.offeredDescription}:{' '}
                      {selectedTradeRequest.offered_description || '-'}
                    </p>

                    <div className="mt-3 rounded-xl border border-white/10 bg-[#0c1524] p-3">
                      <p className="text-[10px] uppercase tracking-[0.2em] text-white/50">
                        {locale === 'en' ? 'Cards you give' : 'Κάρτες που δίνεις'} ({requestBundleCards(selectedTradeRequest, 'proposer_bundle').length})
                      </p>
                      {requestBundleCards(selectedTradeRequest, 'proposer_bundle').length ? (
                        <div className="mt-2 grid gap-2 sm:grid-cols-2">
                          {requestBundleCards(selectedTradeRequest, 'proposer_bundle').map((card, index) =>
                            renderTradeBundleCard(card, 'proposer-bundle-detail', index),
                          )}
                        </div>
                      ) : (
                        <p className="mt-2 text-xs text-mist">
                          {locale === 'en' ? 'No offered cards found for this request.' : 'Δεν βρέθηκαν προσφερόμενες κάρτες για αυτό το αίτημα.'}
                        </p>
                      )}
                    </div>
                  </div>
                </div>

                <div className="mt-4">
                  <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{copy.photos}</p>
                  {requestImages(selectedTradeRequest).length ? (
                    <div className="mt-3 flex flex-wrap gap-3">
                      {requestImages(selectedTradeRequest).map((imageUrl, imageIndex) => (
                        <a
                          key={imageUrl}
                          href={imageUrl}
                          target="_blank"
                          rel="noreferrer"
                          className="group block w-[132px] sm:w-[156px]"
                        >
                          <div className="relative aspect-[3/4] overflow-hidden rounded-xl border border-white/10 bg-[#091427] p-2 transition group-hover:border-gold-300/35">
                            <img
                              src={imageUrl}
                              alt={selectedTradeRequest.offered_title}
                              className="h-full w-full rounded-lg object-contain"
                              loading="lazy"
                            />
                          </div>
                          <p className="mt-1 text-[11px] text-white/60">
                            {locale === 'en' ? `Photo ${imageIndex + 1}` : `Φωτογραφία ${imageIndex + 1}`}
                          </p>
                        </a>
                      ))}
                    </div>
                  ) : (
                    <p className="mt-2 text-sm text-mist">{copy.noPhotos}</p>
                  )}
                </div>
              </div>
            ) : null}
          </Drawer>
        </>
      ) : null}

      {!studioMode ? (
        <Drawer
          open={mobileFiltersOpen}
          title={copy.filters}
          onClose={() => setMobileFiltersOpen(false)}
        >
          <FilterSidebar
            facetGroups={tradeFacetGroups}
            filters={tradeFilters}
            onChange={updateTradeFilter}
            onReset={resetTradeFilters}
          />
        </Drawer>
      ) : null}
    </div>
  )
}

export default DrawsPage

