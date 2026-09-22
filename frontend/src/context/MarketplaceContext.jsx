import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react'
import { useAuthContext } from '@/context/AuthContext'
import { useI18nContext } from '@/context/I18nContext'
import { cardoraService } from '@/services/cardoraService'
import { formatCurrency, formatNumber } from '@/utils/formatters'
import { calculateLowValueFee } from '@/utils/fees'
import { priceInRange, toSlug } from '@/utils/helpers'
import {
  calculateLotSelectionDomesticShipping,
  getDomesticShippingConfig,
  normalizePackageDetails,
  normalizeParcelType,
  parseAmountInput,
  resolveCyprusShippingFee,
  resolveDhlDomesticShippingFee,
  resolveDomesticShippingFee,
} from '@/utils/listingShipping'

const MarketplaceContext = createContext(null)
const CART_ITEM_TYPE_LISTING = 'listing'
const CART_ITEM_TYPE_DRAW = 'draw_entry'
const CART_ITEM_MODE_LOT_SELECTION = 'lot_individual_cards'
const RECENTLY_VIEWED_STORAGE_KEY = 'cardora.recentlyViewedByUser'
const MARKETPLACE_BUYER_FEE_RATE = Number(import.meta.env.VITE_MARKETPLACE_BUYER_FEE_RATE ?? 0.00)

const initialState = {
  isReady: false,
  categories: [],
  users: [],
  products: [],
  shippingSettings: {
    dhl: { enabled: false },
    boxnow: { enabled: false, listing_edit_enabled: false },
  },
  orders: [],
  conversations: [],
  reviews: [],
  supportArticles: [],
  faqGroups: [],
  blogCategories: [],
  blogPosts: [],
  drawCampaigns: [],
  drawParticipations: [],
  drawHighlights: [],
  drawHowItWorks: [],
  drawFaq: [],
  collectorProfiles: [],
  myCollectionEntries: [],
  marketplaceAccess: null,
  accountVerification: null,
  favoriteProductIds: [],
  cartItems: [],
  notifications: [],
  myListings: [],
  supportTickets: [],
  sellerDashboard: { stats: [], performance: [] },
  recentlyViewedIds: [],
  platformStats: [],
  trustHighlights: [],
  howItWorksSteps: [],
}

const normalizeUser = (user, profileConfig) => ({
  ...user,
  name: user.name ?? user.display_name ?? '',
  displayName:
    user.displayName ??
    user.display_name ??
    user.nickname ??
    profileConfig?.nickname ??
    user.handle ??
    user.name ??
    '',
  handle:
    user.handle ??
    profileConfig?.handle ??
    toSlug(user.displayName ?? user.display_name ?? user.name ?? 'collector'),
  favoriteCategories: user.favoriteCategories ?? user.favorite_categories ?? [],
  specializations: user.specializations ?? [],
  recentActivity: user.recentActivity ?? [],
  verified: user.verified ?? user.is_verified_seller ?? false,
  salesCount: Number(user.salesCount ?? user.sales_count ?? 0),
  purchaseCount: Number(user.purchaseCount ?? user.purchase_count ?? 0),
  responseTime: user.responseTime ?? '',
})

const normalizeAuction = (auction) => {
  if (!auction) return null

  const currentBid = Number(auction.currentBid ?? auction.startingBid ?? 0)
  const bidIncrement = Number(auction.bidIncrement ?? 1)
  const reservePrice = Number(auction.reservePrice ?? 0)

  return {
    ...auction,
    startingBid: Number(auction.startingBid ?? 0),
    currentBid,
    reservePrice,
    bidIncrement,
    bidCount: Number(auction.bidCount ?? 0),
    watchers: Number(auction.watchers ?? 0),
    buyoutPrice: auction.buyoutPrice ? Number(auction.buyoutPrice) : null,
    reserveMet: reservePrice > 0 ? currentBid >= reservePrice : true,
  }
}

const normalizeLot = (lot) => {
  if (!lot) return null

  const previewCards = Array.isArray(lot.previewCards) ? lot.previewCards.filter(Boolean) : []
  const totalCards = Number(lot.totalCards ?? previewCards.length ?? 0)
  const individualCards = Array.isArray(lot.individualCards)
    ? lot.individualCards
        .map((card) => ({
          id: String(card?.id ?? '').trim(),
          title: String(card?.title ?? '').trim(),
          price: roundMoney(card?.price ?? 0),
        }))
        .filter((card) => card.id && card.title && card.price > 0)
    : []

  return {
    ...lot,
    totalCards,
    guaranteedHits: Number(lot.guaranteedHits ?? 0),
    previewCards,
    themes: Array.isArray(lot.themes) ? lot.themes.filter(Boolean) : [],
    overflowCount: Math.max(totalCards - previewCards.length, 0),
    allowsIndividualPurchase: Boolean(lot.allowsIndividualPurchase),
    wholeLotPurchaseAvailable:
      lot.wholeLotPurchaseAvailable == null ? true : Boolean(lot.wholeLotPurchaseAvailable),
    individualCards,
    availableIndividualCardsCount: Number(
      lot.availableIndividualCardsCount ?? individualCards.length,
    ),
  }
}

const normalizeCollectionEntry = (entry) => {
  if (!entry) return null

  return {
    id: Number(entry.id ?? 0),
    userId: Number(entry.userId ?? entry.user_id ?? 0),
    productId:
      entry.productId != null
        ? Number(entry.productId)
        : entry.product_id != null
          ? Number(entry.product_id)
          : null,
    title: entry.title ?? '',
    caption: entry.caption ?? '',
    media: Array.isArray(entry.media) ? entry.media.filter(Boolean) : [],
    sortOrder: Number(entry.sortOrder ?? entry.sort_order ?? 0),
    isFeatured: Boolean(entry.isFeatured ?? entry.is_featured),
    visibility: entry.visibility ?? 'public',
    metadata: entry.metadata ?? {},
    createdAt: entry.createdAt ?? entry.created_at ?? null,
    updatedAt: entry.updatedAt ?? entry.updated_at ?? null,
  }
}

const normalizeAccountVerification = (accountVerification) => {
  if (!accountVerification) return null

  const progress = accountVerification.progress ?? {}
  const completed = Number(progress.completed ?? 0)
  const total = Number(progress.total ?? 0)
  const progressPercentage = Number(
    accountVerification.progressPercentage ?? progress.ratio ?? 0,
  )

  return {
    ...accountVerification,
    progress: {
      ...progress,
      completed,
      reviewing: Number(progress.reviewing ?? 0),
      attention: Number(progress.attention ?? 0),
      pending: Number(progress.pending ?? 0),
      total,
      ratio: progressPercentage,
    },
    progressPercentage,
    completedLabel:
      accountVerification.completedLabel ??
      `${completed}/${total}`,
  }
}

const ALL_FILTER_VALUE = 'all'
const GREEK_ALL = '\u038c\u03bb\u03b5\u03c2'

const PRICE_FILTER_OPTIONS = [
  { value: '0-50', min: 0, max: 50, labels: { en: 'Up to \u20ac50', el: '\u0388\u03c9\u03c2 50\u20ac' } },
  { value: '50-150', min: 50, max: 150, labels: { en: '\u20ac50 - \u20ac150', el: '50\u20ac - 150\u20ac' } },
  {
    value: '150-500',
    min: 150,
    max: 500,
    labels: { en: '\u20ac150 - \u20ac500', el: '150\u20ac - 500\u20ac' },
  },
  {
    value: '500-1500',
    min: 500,
    max: 1500,
    labels: { en: '\u20ac500 - \u20ac1,500', el: '500\u20ac - 1.500\u20ac' },
  },
  { value: '1500+', min: 1500, max: Number.POSITIVE_INFINITY, labels: { en: '\u20ac1,500+', el: '1.500\u20ac+' } },
]

const SELLER_RATING_OPTIONS = [
  { value: '4.9+', threshold: 4.9 },
  { value: '4.7+', threshold: 4.7 },
  { value: '4.5+', threshold: 4.5 },
  { value: '4.0+', threshold: 4 },
]

const DEFAULT_SEARCH_FILTERS = {
  search: '',
  price: ALL_FILTER_VALUE,
  condition: ALL_FILTER_VALUE,
  rarity: ALL_FILTER_VALUE,
  franchise: ALL_FILTER_VALUE,
  brand: ALL_FILTER_VALUE,
  productType: ALL_FILTER_VALUE,
  graded: ALL_FILTER_VALUE,
  availability: ALL_FILTER_VALUE,
  sellerRating: ALL_FILTER_VALUE,
}

const FACET_FILTER_KEYS = [
  'price',
  'condition',
  'rarity',
  'franchise',
  'brand',
  'productType',
  'graded',
  'availability',
  'sellerRating',
]

const isAllOption = (value) => {
  if (value == null) return true

  const normalized = String(value).trim().toLowerCase()

  return (
    normalized === '' ||
    normalized === ALL_FILTER_VALUE ||
    normalized === 'all' ||
    normalized === GREEK_ALL.toLowerCase()
  )
}

const normalizeSearchText = (value) =>
  String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^\p{L}\p{N}\s]+/gu, ' ')
    .replace(/\s+/g, ' ')
    .trim()

const tokenizeSearchText = (value) => normalizeSearchText(value).split(' ').filter(Boolean)
const roundMoney = (value) => Math.round(Number(value ?? 0) * 100) / 100

const addWeightedInterest = (bucket, key, weight) => {
  if (!key || !weight) return

  const normalizedKey = normalizeSearchText(key)
  if (!normalizedKey) return

  bucket.set(normalizedKey, (bucket.get(normalizedKey) ?? 0) + weight)
}

const buildProductInterestProfile = (product, weight, scores) => {
  if (!product || !weight) return

  addWeightedInterest(scores.categories, product.categoryId, weight * 1.8)
  addWeightedInterest(scores.categories, product.category?.name, weight * 1.2)
  addWeightedInterest(scores.franchises, product.franchise, weight * 1.7)
  addWeightedInterest(scores.series, product.series, weight * 1.35)
  addWeightedInterest(scores.brands, product.brand, weight * 1.1)
  addWeightedInterest(scores.types, product.typeLabel, weight * 1.1)

  if (Array.isArray(product.tags)) {
    product.tags.forEach((tag) => addWeightedInterest(scores.tags, tag, weight * 0.7))
  }
}

const scoreProductAgainstInterestProfile = (product, profile) => {
  if (!product || !profile) return 0

  let score = 0
  score += profile.categories.get(normalizeSearchText(product.categoryId)) ?? 0
  score += profile.categories.get(normalizeSearchText(product.category?.name)) ?? 0
  score += profile.franchises.get(normalizeSearchText(product.franchise)) ?? 0
  score += profile.series.get(normalizeSearchText(product.series)) ?? 0
  score += profile.brands.get(normalizeSearchText(product.brand)) ?? 0
  score += profile.types.get(normalizeSearchText(product.typeLabel)) ?? 0

  if (Array.isArray(product.tags)) {
    product.tags.forEach((tag) => {
      score += profile.tags.get(normalizeSearchText(tag)) ?? 0
    })
  }

  return score
}

const compareFeaturedProducts = (left, right) => {
  const featuredDelta = Number(Boolean(right?.featured)) - Number(Boolean(left?.featured))
  if (featuredDelta !== 0) return featuredDelta

  const leftFeaturedUntil = new Date(left?.featuredUntil ?? 0).getTime()
  const rightFeaturedUntil = new Date(right?.featuredUntil ?? 0).getTime()

  if (rightFeaturedUntil !== leftFeaturedUntil) {
    return rightFeaturedUntil - leftFeaturedUntil
  }

  return 0
}

const createSearchFilters = (overrides = {}) => ({
  ...DEFAULT_SEARCH_FILTERS,
  ...Object.fromEntries(
    Object.entries(overrides).map(([key, value]) => [key, value == null ? DEFAULT_SEARCH_FILTERS[key] : value]),
  ),
})

const formatSearchLabel = (locale, key) => {
  if (key === 'price') {
    return locale === 'en' ? 'Price' : '\u03a4\u03b9\u03bc\u03ae'
  }

  if (key === 'condition') {
    return locale === 'en' ? 'Condition' : '\u039a\u03b1\u03c4\u03ac\u03c3\u03c4\u03b1\u03c3\u03b7'
  }

  if (key === 'rarity') {
    return locale === 'en' ? 'Rarity' : '\u03a3\u03c0\u03b1\u03bd\u03b9\u03cc\u03c4\u03b7\u03c4\u03b1'
  }

  if (key === 'franchise') {
    return locale === 'en' ? 'Franchise / Series' : 'Franchise / \u03a3\u03b5\u03b9\u03c1\u03ac'
  }

  if (key === 'brand') {
    return locale === 'en' ? 'Brand' : '\u0395\u03c4\u03b1\u03b9\u03c1\u03b5\u03af\u03b1 / Brand'
  }

  if (key === 'productType') {
    return locale === 'en' ? 'Product type' : '\u03a4\u03cd\u03c0\u03bf\u03c2 \u03c0\u03c1\u03bf\u03ca\u03cc\u03bd\u03c4\u03bf\u03c2'
  }

  if (key === 'graded') {
    return locale === 'en' ? 'Graded status' : '\u039a\u03b1\u03c4\u03ac\u03c3\u03c4\u03b1\u03c3\u03b7 grading'
  }

  if (key === 'availability') {
    return locale === 'en' ? 'Availability' : '\u0394\u03b9\u03b1\u03b8\u03b5\u03c3\u03b9\u03bc\u03cc\u03c4\u03b7\u03c4\u03b1'
  }

  if (key === 'sellerRating') {
    return locale === 'en' ? 'Seller rating' : '\u0392\u03b1\u03b8\u03bc\u03bf\u03bb\u03bf\u03b3\u03af\u03b1 \u03c0\u03c9\u03bb\u03b7\u03c4\u03ae'
  }

  return key
}

const isProductGraded = (product) => {
  const company = normalizeSearchText(product?.gradedCompany)
  const grade = normalizeSearchText(product?.grade)

  if (!company && !grade) return false
  if (company.includes('ungraded') || company.includes('raw')) return false
  if (grade === 'raw' || grade === 'ungraded') return false

  return Boolean(company || grade)
}

const matchesPriceFilter = (price, selectedPrice) => {
  if (isAllOption(selectedPrice)) return true

  const explicitOption = PRICE_FILTER_OPTIONS.find((option) => option.value === selectedPrice)

  if (explicitOption) {
    return Number(price) >= explicitOption.min && Number(price) <= explicitOption.max
  }

  return priceInRange(Number(price), selectedPrice)
}

const parseSellerThreshold = (value) => {
  if (isAllOption(value)) return null

  const normalized = String(value).replace(/[^\d.]+/g, '')
  const threshold = Number(normalized)

  return Number.isFinite(threshold) ? threshold : null
}

const extractFilterValues = (product, key) => {
  if (key === 'condition') return [product.condition].filter(Boolean)
  if (key === 'rarity') return [product.rarity].filter(Boolean)
  if (key === 'franchise') return [product.franchise].filter(Boolean)
  if (key === 'brand') return [product.brand].filter(Boolean)
  if (key === 'productType') return [product.typeLabel].filter(Boolean)
  if (key === 'availability') return [product.availability].filter(Boolean)
  if (key === 'graded') return [isProductGraded(product) ? 'graded' : 'ungraded']

  return []
}

const ensureSelectedOption = (options, selectedValue, selectedLabel) => {
  if (isAllOption(selectedValue)) return options
  if (options.some((option) => option.value === selectedValue)) return options

  return [
    ...options,
    {
      value: selectedValue,
      label: selectedLabel ?? selectedValue,
      count: 0,
      selected: true,
      disabled: false,
    },
  ]
}

const getPriceFilterLabel = (value, locale) => {
  if (isAllOption(value)) return locale === 'en' ? 'All' : GREEK_ALL

  const option = PRICE_FILTER_OPTIONS.find((item) => item.value === value)
  return option ? option.labels[locale] : value
}

const getGradedFilterLabel = (value, locale) => {
  if (value === 'graded') return locale === 'en' ? 'Graded' : '\u0394\u03b9\u03b1\u03b2\u03b1\u03b8\u03bc\u03b9\u03c3\u03bc\u03ad\u03bd\u03bf'
  if (value === 'ungraded') return locale === 'en' ? 'Ungraded' : '\u039c\u03b7 \u03b4\u03b9\u03b1\u03b2\u03b1\u03b8\u03bc\u03b9\u03c3\u03bc\u03ad\u03bd\u03bf'
  return value
}

export function MarketplaceProvider({ children }) {
  const { currentUser, isAuthReady } = useAuthContext()
  const { locale } = useI18nContext()
  const [state, setState] = useState(initialState)
  const bootstrapRequestRef = useRef(null)

  const commitBootstrapPayload = (payload) => {
    const normalizedPayload = {
      ...payload,
      accountVerification: normalizeAccountVerification(payload.accountVerification),
    }

    setState((previous) => ({
      ...previous,
      ...normalizedPayload,
      isReady: true,
    }))

    return normalizedPayload
  }

  const fetchBootstrap = async ({ silent = false } = {}) => {
    if (bootstrapRequestRef.current) {
      return bootstrapRequestRef.current
    }

    const request = cardoraService.getBootstrap()
    bootstrapRequestRef.current = request

    try {
      const payload = await request
      return commitBootstrapPayload(payload)
    } catch (error) {
      if (!silent) {
        setState((previous) => ({
          ...previous,
          isReady: true,
        }))
      }

      throw error
    } finally {
      if (bootstrapRequestRef.current === request) {
        bootstrapRequestRef.current = null
      }
    }
  }

  useEffect(() => {
    let isMounted = true

    const loadBootstrap = async () => {
      try {
        await fetchBootstrap()
      } catch (error) {
        if (!isMounted) return
      }
    }

    if (isAuthReady) {
      loadBootstrap()
    }

    return () => {
      isMounted = false
    }
  }, [currentUser?.id, isAuthReady, locale])

  useEffect(() => {
    if (!currentUser || typeof window === 'undefined') return

    try {
      const rawState = window.localStorage.getItem(RECENTLY_VIEWED_STORAGE_KEY)
      const parsedState = rawState ? JSON.parse(rawState) : {}
      const storedIds = Array.isArray(parsedState?.[currentUser.id]) ? parsedState[currentUser.id] : []

      if (!storedIds.length) return

      setState((previous) => {
        if (JSON.stringify(previous.recentlyViewedIds) === JSON.stringify(storedIds)) {
          return previous
        }

        return {
          ...previous,
          recentlyViewedIds: storedIds.slice(0, 8),
        }
      })
    } catch (error) {
      // Ignore malformed local state and continue with live browsing data.
    }
  }, [currentUser])

  useEffect(() => {
    if (!currentUser || typeof window === 'undefined') return

    try {
      const rawState = window.localStorage.getItem(RECENTLY_VIEWED_STORAGE_KEY)
      const parsedState = rawState ? JSON.parse(rawState) : {}
      parsedState[currentUser.id] = state.recentlyViewedIds.slice(0, 8)
      window.localStorage.setItem(RECENTLY_VIEWED_STORAGE_KEY, JSON.stringify(parsedState))
    } catch (error) {
      // Ignore local storage write issues.
    }
  }, [currentUser, state.recentlyViewedIds])

  const refreshBootstrap = async () => fetchBootstrap()

  useEffect(() => {
    if (!isAuthReady || typeof window === 'undefined' || typeof document === 'undefined') {
      return undefined
    }

    const syncBootstrap = () => {
      if (document.visibilityState && document.visibilityState !== 'visible') {
        return
      }

      fetchBootstrap({ silent: true }).catch(() => {})
    }

    window.addEventListener('focus', syncBootstrap)
    document.addEventListener('visibilitychange', syncBootstrap)

    return () => {
      window.removeEventListener('focus', syncBootstrap)
      document.removeEventListener('visibilitychange', syncBootstrap)
    }
  }, [currentUser?.id, isAuthReady, locale])

  const mergedUsers = useMemo(() => {
    const getProfileConfig = (userId) =>
      state.collectorProfiles.find((profile) => profile.userId === userId)

    const normalizedUsers = state.users.map((user) =>
      normalizeUser(user, getProfileConfig(user.id)),
    )

    if (!currentUser) return normalizedUsers

    const normalizedCurrentUser = normalizeUser(currentUser, getProfileConfig(currentUser.id))

    return normalizedUsers.some((user) => user.id === normalizedCurrentUser.id)
      ? normalizedUsers.map((user) =>
          user.id === normalizedCurrentUser.id ? { ...user, ...normalizedCurrentUser } : user,
        )
      : [normalizedCurrentUser, ...normalizedUsers]
  }, [currentUser, state.collectorProfiles, state.users])

  const productsWithSellers = useMemo(
    () =>
      state.products.map((product) => ({
        ...product,
        saleFormat: product.saleFormat ?? 'fixed_price',
        auction: normalizeAuction(product.auction),
        lot: normalizeLot(product.lot),
        seller: mergedUsers.find((user) => user.id === product.sellerId) ?? null,
        category: state.categories.find((category) => category.id === product.categoryId) ?? null,
      })),
    [mergedUsers, state.categories, state.products],
  )

  const searchIndexByProductId = useMemo(() => {
    return new Map(
      productsWithSellers.map((product) => {
        const sellerName = product.seller?.displayName ?? product.seller?.name ?? ''
        const searchableChunks = [
          product.title,
          product.subtitle,
          product.franchise,
          product.series,
          product.brand,
          product.typeLabel,
          product.setName,
          product.cardNumber,
          product.description,
          product.shortDescription,
          product.authenticity,
          sellerName,
          product.seller?.handle,
          product.seller?.city,
          product.category?.name,
          ...(Array.isArray(product.tags) ? product.tags : []),
          ...(Array.isArray(product.highlights) ? product.highlights : []),
          ...(Array.isArray(product.lot?.previewCards) ? product.lot.previewCards : []),
          ...(Array.isArray(product.lot?.themes) ? product.lot.themes : []),
        ]

        const titleNormalized = normalizeSearchText(product.title)
        const subtitleNormalized = normalizeSearchText(product.subtitle)
        const franchiseNormalized = normalizeSearchText(product.franchise)
        const seriesNormalized = normalizeSearchText(product.series)
        const brandNormalized = normalizeSearchText(product.brand)
        const sellerNormalized = normalizeSearchText(sellerName)
        const typeNormalized = normalizeSearchText(product.typeLabel)
        const setNameNormalized = normalizeSearchText(product.setName)
        const combined = normalizeSearchText(searchableChunks.filter(Boolean).join(' '))
        const tokenSet = new Set(tokenizeSearchText(combined))

        return [
          product.id,
          {
            title: titleNormalized,
            subtitle: subtitleNormalized,
            franchise: franchiseNormalized,
            series: seriesNormalized,
            brand: brandNormalized,
            seller: sellerNormalized,
            typeLabel: typeNormalized,
            setName: setNameNormalized,
            combined,
            tokenSet,
          },
        ]
      }),
    )
  }, [productsWithSellers])

  const drawCampaignsDetailed = useMemo(
    () =>
      state.drawCampaigns.map((draw) => {
        const host = draw.hostUserId
          ? mergedUsers.find((user) => user.id === draw.hostUserId) ?? null
          : null
        const progressCurrent =
          draw.campaignType === 'platform_volume'
            ? Number(draw.currentAmount ?? 0)
            : Number(draw.soldEntries ?? 0)
        const progressTarget =
          draw.campaignType === 'platform_volume'
            ? Number(draw.targetAmount ?? 0)
            : Number(draw.targetEntries ?? 0)

        return {
          ...draw,
          host,
          progressCurrent,
          progressTarget,
          progressPercentage: progressTarget
            ? Math.min(Math.round((progressCurrent / progressTarget) * 100), 100)
            : 0,
          amountRemaining:
            draw.targetAmount != null
              ? Math.max(Number(draw.targetAmount) - Number(draw.currentAmount ?? 0), 0)
              : 0,
          entriesRemaining:
            draw.targetEntries != null
              ? Math.max(Number(draw.targetEntries) - Number(draw.soldEntries ?? 0), 0)
              : 0,
        }
      }),
    [mergedUsers, state.drawCampaigns],
  )

  const drawCampaignsById = useMemo(
    () => new Map(drawCampaignsDetailed.map((draw) => [draw.id, draw])),
    [drawCampaignsDetailed],
  )

  const cartDetailed = useMemo(
    () =>
      state.cartItems
        .map((item) => {
          if (
            item.itemType === CART_ITEM_TYPE_DRAW ||
            item.drawCampaignId != null
          ) {
            const draw = drawCampaignsById.get(Number(item.drawCampaignId))

            return draw
              ? {
                  ...item,
                  itemType: CART_ITEM_TYPE_DRAW,
                  draw,
                  unitPrice: Number(draw.entryPrice ?? 0),
                  shippingCost: 0,
                }
              : null
          }

          const product = productsWithSellers.find((entry) => entry.id === item.productId)
          const metadata = item.metadata ?? {}

          if (!product) {
            return null
          }

          if (metadata.item_mode === CART_ITEM_MODE_LOT_SELECTION) {
            const selectedCards = Array.isArray(metadata.selected_lot_cards)
              ? metadata.selected_lot_cards.filter((card) => card?.title)
              : []
            const selectedCardsCount = Number(
              metadata.selected_lot_cards_count ?? selectedCards.length ?? 0,
            )
            const domesticShippingTotal = roundMoney(
              metadata.domestic_shipping_total ??
                calculateLotSelectionDomesticShipping(selectedCardsCount),
            )

            return {
              ...item,
              metadata,
              itemMode: CART_ITEM_MODE_LOT_SELECTION,
              itemType: CART_ITEM_TYPE_LISTING,
              product,
              quantity: 1,
              selectedCards,
              selectedCardsCount,
              unitPrice: roundMoney(metadata.selected_lot_cards_total ?? 0),
              shippingCost: domesticShippingTotal,
              displayTitle:
                locale === 'en'
                  ? `${selectedCardsCount} card${selectedCardsCount === 1 ? '' : 's'} from lot`
                  : `${selectedCardsCount} κάρτες από το lot`,
            }
          }

          return {
            ...item,
            metadata,
            itemType: CART_ITEM_TYPE_LISTING,
            product,
            unitPrice: Number(product.price ?? 0),
            shippingCost: Number(product.shippingCost ?? 0),
          }
        })
        .filter(Boolean),
    [drawCampaignsById, locale, productsWithSellers, state.cartItems],
  )

  const favoriteProducts = useMemo(
    () => productsWithSellers.filter((product) => state.favoriteProductIds.includes(product.id)),
    [productsWithSellers, state.favoriteProductIds],
  )

  const userInterestProfile = useMemo(() => {
    const emptyProfile = {
      categories: new Map(),
      franchises: new Map(),
      series: new Map(),
      brands: new Map(),
      types: new Map(),
      tags: new Map(),
    }

    if (!currentUser) return emptyProfile

    state.recentlyViewedIds.forEach((productId, index) => {
      const product =
        productsWithSellers.find((item) => String(item.id) === String(productId)) ?? null

      if (!product) return

      const recencyWeight = Math.max(8 - index, 1)
      buildProductInterestProfile(product, recencyWeight * 2.4, emptyProfile)
    })

    state.favoriteProductIds.forEach((productId) => {
      const product =
        productsWithSellers.find((item) => String(item.id) === String(productId)) ?? null

      if (!product) return

      buildProductInterestProfile(product, 5.5, emptyProfile)
    })

    state.orders.forEach((order) => {
      const product =
        productsWithSellers.find(
          (item) =>
            Number(item.id) === Number(order.productId) ||
            Number(item.databaseId ?? 0) === Number(order.productId ?? 0),
        ) ?? null

      if (!product) return

      const isBuyer = Number(order.buyerId ?? 0) === Number(currentUser.id)
      const isSeller = Number(order.sellerId ?? 0) === Number(currentUser.id)

      if (isBuyer) {
        buildProductInterestProfile(product, 6.5, emptyProfile)
      } else if (isSeller) {
        buildProductInterestProfile(product, 2.25, emptyProfile)
      }
    })

    return emptyProfile
  }, [
    currentUser,
    productsWithSellers,
    state.favoriteProductIds,
    state.orders,
    state.recentlyViewedIds,
  ])

  const getPersonalizationScore = useCallback(
    (product) => scoreProductAgainstInterestProfile(product, userInterestProfile),
    [userInterestProfile],
  )

  const recentProducts = useMemo(
    () => {
      if (state.recentlyViewedIds.length) {
        return state.recentlyViewedIds
          .map((productId) =>
            productsWithSellers.find((product) => String(product.id) === String(productId)) ?? null,
          )
          .filter(Boolean)
          .slice(0, 8)
      }

      return [...productsWithSellers]
        .sort((a, b) => {
          const featuredDelta = compareFeaturedProducts(a, b)
          if (featuredDelta !== 0) return featuredDelta

          return new Date(b.listedAt) - new Date(a.listedAt)
        })
        .slice(0, 8)
    },
    [productsWithSellers, state.recentlyViewedIds],
  )

  const trendingProducts = useMemo(
    () =>
      [...productsWithSellers]
        .sort((a, b) => {
          const featuredDelta = compareFeaturedProducts(a, b)
          if (featuredDelta !== 0) return featuredDelta

          const personalizationDelta = getPersonalizationScore(b) - getPersonalizationScore(a)
          if (personalizationDelta !== 0) return personalizationDelta

          return (b.price || 0) * (b.sellerRating || 0) - (a.price || 0) * (a.sellerRating || 0)
        })
        .slice(0, 8),
    [getPersonalizationScore, productsWithSellers],
  )

  const topSellers = useMemo(
    () =>
      [...mergedUsers]
        .filter((user) => user.verified)
        .sort((a, b) => b.salesCount - a.salesCount)
        .slice(0, 4),
    [mergedUsers],
  )

  const latestBlogPosts = useMemo(
    () =>
      [...state.blogPosts].sort((a, b) => new Date(b.publishedAt ?? 0) - new Date(a.publishedAt ?? 0)),
    [state.blogPosts],
  )

  const featuredBlogPosts = useMemo(
    () => latestBlogPosts.filter((post) => post.featured).slice(0, 2),
    [latestBlogPosts],
  )

  const featuredDraws = useMemo(
    () => drawCampaignsDetailed.filter((draw) => draw.featured).slice(0, 3),
    [drawCampaignsDetailed],
  )

  const platformDraws = useMemo(
    () => drawCampaignsDetailed.filter((draw) => draw.campaignType === 'platform_volume'),
    [drawCampaignsDetailed],
  )

  const communityDraws = useMemo(
    () => drawCampaignsDetailed.filter((draw) => draw.campaignType === 'community_raffle'),
    [drawCampaignsDetailed],
  )

  const myHostedCommunityDraws = useMemo(
    () =>
      currentUser ? communityDraws.filter((draw) => draw.hostUserId === currentUser.id) : [],
    [communityDraws, currentUser],
  )

  const myDrawParticipations = useMemo(
    () =>
      currentUser
        ? state.drawParticipations.filter((entry) => entry.userId === currentUser.id)
        : [],
    [currentUser, state.drawParticipations],
  )

  const drawOverview = useMemo(
    () => ({
      totalMonthlyVolume: platformDraws.reduce((sum, draw) => sum + Number(draw.currentAmount ?? 0), 0),
      totalCommunityRaised: communityDraws.reduce((sum, draw) => sum + Number(draw.currentAmount ?? 0), 0),
      totalEntriesForCurrentUser: myDrawParticipations.reduce((sum, entry) => sum + Number(entry.entries ?? 0), 0),
      totalActiveDraws: drawCampaignsDetailed.filter((draw) => draw.status === 'active').length,
    }),
    [communityDraws, drawCampaignsDetailed, myDrawParticipations, platformDraws],
  )

  const collectorProfilesDetailed = useMemo(
    () =>
      mergedUsers.map((user) => {
        const configuredProfile = state.collectorProfiles.find((profile) => profile.userId === user.id)
        const sellerProducts = productsWithSellers.filter((product) => product.sellerId === user.id)
        const collectionEntries = (configuredProfile?.collectionEntries ?? [])
          .map((entry) => normalizeCollectionEntry(entry))
          .filter(Boolean)
          .map((entry) => ({
            ...entry,
            product: entry.productId
              ? productsWithSellers.find((product) => product.id === entry.productId) ?? null
              : null,
          }))

        const collectionProducts = collectionEntries
          .map((entry) => entry.product)
          .filter(Boolean)

        const pinnedProductIds =
          configuredProfile?.pinnedProductIds?.length
            ? configuredProfile.pinnedProductIds
            : collectionEntries
                .filter((entry) => entry.isFeatured)
                .map((entry) => entry.productId)
                .filter(Boolean)
        const pinnedProducts = pinnedProductIds
          .map((productId) => productsWithSellers.find((product) => product.id === productId))
          .filter(Boolean)
        const likedByUserIds = configuredProfile?.likedByUserIds ?? []
        const followedByUserIds = configuredProfile?.followedByUserIds ?? []

        return {
          userId: user.id,
          handle: configuredProfile?.handle ?? user.handle,
          nickname: configuredProfile?.nickname ?? user.displayName,
          headline: configuredProfile?.headline ?? user.collectorTagline ?? user.bio ?? '',
          intro: configuredProfile?.intro ?? user.bio ?? '',
          heroQuote: configuredProfile?.heroQuote ?? '',
          badges: configuredProfile?.badges ?? user.specializations ?? [],
          collectionMoments: configuredProfile?.collectionMoments ?? user.recentActivity ?? [],
          coverPalette: configuredProfile?.coverPalette ?? {
            from: user.avatar?.from ?? '#f2cb70',
            via: '#162238',
            to: user.avatar?.to ?? '#09111d',
          },
          collectionEntries,
          collectionProducts,
          pinnedProducts,
          activeListings: sellerProducts.filter((product) => Number(product.stock ?? 0) > 0),
          likedByUserIds,
          followedByUserIds,
          likeCount: likedByUserIds.length,
          followerCount: Number(configuredProfile?.followerCount ?? followedByUserIds.length),
          followingCount: Number(configuredProfile?.followingCount ?? 0),
          likedByCurrentUser: currentUser ? likedByUserIds.includes(currentUser.id) : false,
          followedByCurrentUser: currentUser ? followedByUserIds.includes(currentUser.id) : false,
          totalCollectionValue: collectionProducts.reduce((sum, product) => sum + Number(product?.price ?? 0), 0),
          collectionCount: Number(configuredProfile?.collectionEntriesCount ?? collectionEntries.length),
          user,
        }
      }),
    [currentUser, mergedUsers, productsWithSellers, state.collectorProfiles],
  )

  const myCollectionEntriesDetailed = useMemo(
    () =>
      (Array.isArray(state.myCollectionEntries) ? state.myCollectionEntries : [])
        .map((entry) => normalizeCollectionEntry(entry))
        .filter(Boolean)
        .map((entry) => ({
          ...entry,
          product: entry.productId
            ? productsWithSellers.find((product) => product.id === entry.productId) ?? null
            : null,
        })),
    [productsWithSellers, state.myCollectionEntries],
  )

  const marketplaceAccess = useMemo(
    () => state.marketplaceAccess ?? currentUser?.marketplaceAccess ?? null,
    [currentUser?.marketplaceAccess, state.marketplaceAccess],
  )

  const getMarketplaceGateMessage = useCallback(
    (mode = 'trade') => {
      if (!currentUser) return null
      if (!marketplaceAccess || marketplaceAccess.is_marketplace_ready) return null

      const defaultMessage =
        locale === 'en'
          ? 'Complete identity verification, address verification, IBAN verification and Stripe Connect before using the marketplace.'
          : 'Ολοκλήρωσε επαλήθευση ταυτότητας, διεύθυνσης, IBAN και Stripe Connected Account πριν χρησιμοποιήσεις το marketplace.'

      if (mode === 'buy') {
        return (
          marketplaceAccess.blocking_message ??
          (locale === 'en'
            ? 'You must complete verification and Stripe Connect before buying on Cardora.'
            : 'Πρέπει πρώτα να ολοκληρώσεις verification και Stripe Connect για να αγοράσεις στην Cardora.')
        )
      }

      if (mode === 'sell') {
        return (
          marketplaceAccess.blocking_message ??
          (locale === 'en'
            ? 'You must complete verification and Stripe Connect before selling on Cardora.'
            : 'Πρέπει πρώτα να ολοκληρώσεις verification και Stripe Connect για να πουλήσεις στην Cardora.')
        )
      }

      return marketplaceAccess.blocking_message ?? defaultMessage
    },
    [currentUser, locale, marketplaceAccess],
  )

  const notificationsUnread = state.notifications.filter((item) => !item.read).length

  const cartSubtotal = cartDetailed.reduce(
    (sum, item) =>
      sum +
      Number(item.unitPrice ?? 0) *
        Number(item.itemMode === CART_ITEM_MODE_LOT_SELECTION ? 1 : item.quantity ?? 0),
    0,
  )
  const cartShipping = cartDetailed.reduce(
    (sum, item) =>
      sum +
      (item.itemType === CART_ITEM_TYPE_LISTING
        ? Number(item.shippingCost ?? 0) *
          Number(item.itemMode === CART_ITEM_MODE_LOT_SELECTION ? 1 : item.quantity ?? 0)
        : 0),
    0,
  )
  const cartServiceFee = roundMoney(cartSubtotal * MARKETPLACE_BUYER_FEE_RATE)
  // Checkout only allows a single seller per order, so the first listing item's seller
  // is the one whose PRO status (0.75€ vs 1€) applies to the whole cart's low-value fee.
  const cartPrimaryListingItem = cartDetailed.find((item) => item.itemType === CART_ITEM_TYPE_LISTING)
  const cartLowValueFee = calculateLowValueFee(cartSubtotal, Boolean(cartPrimaryListingItem?.product?.sellerIsPro))
  const cartTotalQuantity = cartDetailed.reduce(
    (sum, item) =>
      sum +
      Number(
        item.itemMode === CART_ITEM_MODE_LOT_SELECTION
          ? item.selectedCardsCount ?? 0
          : item.quantity ?? 0,
      ),
    0,
  )
  const cartContainsPhysicalItems = cartDetailed.some((item) => item.itemType === CART_ITEM_TYPE_LISTING)
  const cartContainsDrawEntries = cartDetailed.some((item) => item.itemType === CART_ITEM_TYPE_DRAW)
  const cartTotal = roundMoney(cartSubtotal + cartShipping + cartServiceFee + cartLowValueFee)

  const toggleFavorite = async (productId) => {
    if (!currentUser) return { success: false, message: 'Authentication required.' }

    const isFavorite = state.favoriteProductIds.includes(productId)

    if (isFavorite) {
      const favorites = await cardoraService.getFavorites()
      const targetFavorite = favorites.find(
        (favorite) => favorite.listing_id === productId || favorite.listing?.id === productId,
      )

      if (targetFavorite) {
        await cardoraService.removeFavorite(targetFavorite.id)
      }
    } else {
      await cardoraService.addFavorite(productId)
    }

    await refreshBootstrap()

    return { success: true, liked: !isFavorite }
  }

  const addToCart = async (productOrSelection) => {
    if (!currentUser) {
      return {
        success: false,
        message: locale === 'en' ? 'Please sign in first.' : 'Κάνε πρώτα είσοδο στον λογαριασμό σου.',
      }
    }

    const gateMessage = getMarketplaceGateMessage('buy')
    if (gateMessage) {
      return {
        success: false,
        message: gateMessage,
      }
    }

    const isLotSelectionRequest =
      productOrSelection &&
      typeof productOrSelection === 'object' &&
      productOrSelection.itemMode === CART_ITEM_MODE_LOT_SELECTION
    const productId = isLotSelectionRequest
      ? Number(productOrSelection.productId)
      : Number(productOrSelection)
    const product = productsWithSellers.find((item) => item.id === productId)
    if (!product) {
      return {
        success: false,
        message: locale === 'en' ? 'This listing is no longer available.' : 'Η αγγελία δεν είναι πλέον διαθέσιμη.',
      }
    }

    if (product.saleFormat === 'auction') {
      return {
        success: false,
        message: locale === 'en' ? 'Auction listings cannot be added to the cart.' : 'Οι δημοπρασίες δεν μπαίνουν στο καλάθι.',
      }
    }

    if (Number(product.sellerId ?? product.seller?.id ?? 0) === Number(currentUser.id)) {
      return {
        success: false,
        message: locale === 'en' ? 'You cannot add your own listing.' : 'Δεν μπορείς να προσθέσεις δική σου αγγελία.',
      }
    }

    if (isLotSelectionRequest) {
      const selectedCardIds = Array.isArray(productOrSelection.selectedCardIds)
        ? productOrSelection.selectedCardIds.filter(Boolean)
        : []

      if (!selectedCardIds.length) {
        return {
          success: false,
          message:
            locale === 'en'
              ? 'Select at least one card from the lot first.'
              : 'Επίλεξε πρώτα τουλάχιστον μία κάρτα από το lot.',
        }
      }

      if (!product.lot?.allowsIndividualPurchase) {
        return {
          success: false,
          message:
            locale === 'en'
              ? 'This seller has not enabled individual card purchases for this lot.'
              : 'Ο πωλητής δεν έχει ενεργοποιήσει μεμονωμένη αγορά καρτών για αυτό το lot.',
        }
      }

      const normalizedIds = [...new Set(selectedCardIds.map((value) => String(value).trim()).filter(Boolean))]
      const selectionKey = normalizedIds.slice().sort().join('|')
      const existingSelection = state.cartItems.find(
        (item) =>
          item.itemType !== CART_ITEM_TYPE_DRAW &&
          Number(item.productId) === productId &&
          (item.metadata?.item_mode ?? null) === CART_ITEM_MODE_LOT_SELECTION &&
          [...new Set((item.metadata?.selected_lot_card_ids ?? []).map((value) => String(value).trim()))]
            .sort()
            .join('|') === selectionKey,
      )

      if (existingSelection) {
        return {
          success: false,
          message:
            locale === 'en'
              ? 'This exact card selection is already in your cart.'
              : 'Αυτή η ακριβής επιλογή καρτών υπάρχει ήδη στο καλάθι σου.',
        }
      }

      try {
        await cardoraService.addToCart({
          listing_id: productId,
          quantity: 1,
          metadata: {
            item_mode: CART_ITEM_MODE_LOT_SELECTION,
            lot_card_ids: normalizedIds,
          },
        })
      } catch (error) {
        return {
          success: false,
          message:
            error?.message ??
            (locale === 'en'
              ? 'Unable to add the selected cards right now.'
              : 'Δεν ήταν δυνατή η προσθήκη των επιλεγμένων καρτών αυτή τη στιγμή.'),
        }
      }

      await refreshBootstrap()

      return {
        success: true,
        message:
          locale === 'en'
            ? 'The selected cards were added to the cart.'
            : 'Οι επιλεγμένες κάρτες προστέθηκαν στο καλάθι.',
      }
    }

    if (product.lot?.allowsIndividualPurchase && product.lot?.wholeLotPurchaseAvailable === false) {
      return {
        success: false,
        message:
          locale === 'en'
            ? 'The full lot is no longer available as one bundle.'
            : 'Το πλήρες lot δεν είναι πλέον διαθέσιμο ως ενιαίο πακέτο.',
      }
    }

    const existingItem = state.cartItems.find(
      (item) =>
        item.itemType !== CART_ITEM_TYPE_DRAW &&
        item.productId === productId &&
        (item.metadata?.item_mode ?? null) !== CART_ITEM_MODE_LOT_SELECTION,
    )

    const currentQuantity = Number(existingItem?.quantity ?? 0)
    const availableStock = Number(product.stock ?? 0)

    if (availableStock > 0 && currentQuantity >= availableStock) {
      return {
        success: false,
        message:
          locale === 'en'
            ? 'The maximum available quantity is already in your cart.'
            : 'Έχεις ήδη στο καλάθι τη μέγιστη διαθέσιμη ποσότητα για αυτό το αντικείμενο.',
      }
    }

    try {
      if (existingItem) {
        await cardoraService.updateCartItem(existingItem.id, currentQuantity + 1)
      } else {
        await cardoraService.addToCart(productId, 1)
      }
    } catch (error) {
      return {
        success: false,
        message:
          error?.message ??
          (locale === 'en' ? 'Unable to add this item to the cart right now.' : 'Δεν ήταν δυνατή η προσθήκη στο καλάθι αυτή τη στιγμή.'),
      }
    }

    await refreshBootstrap()

    return {
      success: true,
      message: locale === 'en' ? 'Added to cart.' : 'Προστέθηκε στο καλάθι.',
    }
  }

  const addDrawToCart = async (drawId, entries = 1) => {
    if (!currentUser || entries < 1) return null

    const gateMessage = getMarketplaceGateMessage('buy')
    if (gateMessage) {
      return {
        success: false,
        message: gateMessage,
      }
    }

    const draw = drawCampaignsDetailed.find((item) => item.id === drawId)
    if (!draw || draw.status !== 'active' || draw.campaignType !== 'community_raffle') {
      return {
        success: false,
        message: locale === 'en' ? 'This raffle is no longer available.' : 'Η κλήρωση δεν είναι πλέον διαθέσιμη.',
      }
    }
    if (Number(draw.hostUserId ?? draw.host?.id ?? 0) === Number(currentUser.id)) {
      return {
        success: false,
        message: locale === 'en' ? 'You cannot join your own raffle.' : 'Δεν μπορείς να συμμετάσχεις στη δική σου κλήρωση.',
      }
    }

    const existingItem = state.cartItems.find(
      (item) =>
        (item.itemType === CART_ITEM_TYPE_DRAW || item.drawCampaignId != null) &&
        Number(item.drawCampaignId) === Number(drawId),
    )

    try {
      if (existingItem) {
        await cardoraService.updateCartItem(existingItem.id, Number(existingItem.quantity) + Number(entries))
      } else {
        await cardoraService.addToCart({
          draw_campaign_id: drawId,
          quantity: Number(entries),
        })
      }
    } catch (error) {
      return {
        success: false,
        message:
          error?.message ??
          (locale === 'en'
            ? 'Unable to add raffle entries right now.'
            : 'Δεν ήταν δυνατή η προσθήκη συμμετοχής στην κλήρωση αυτή τη στιγμή.'),
      }
    }

    await refreshBootstrap()
    return {
      success: true,
      message:
        locale === 'en'
          ? 'Your raffle entry was added to the cart.'
          : 'Η συμμετοχή στην κλήρωση προστέθηκε στο καλάθι.',
    }
  }

  const removeFromCart = async (cartItemId) => {
    const targetItem = state.cartItems.find((item) => item.id === cartItemId)
    if (!targetItem) return

    await cardoraService.removeCartItem(targetItem.id)
    await refreshBootstrap()
  }

  const updateCartQuantity = async (cartItemId, quantity) => {
    const targetItem = state.cartItems.find((item) => item.id === cartItemId)
    if (!targetItem) return

    if ((targetItem.metadata?.item_mode ?? null) === CART_ITEM_MODE_LOT_SELECTION) {
      return
    }

    if (quantity < 1) {
      await removeFromCart(cartItemId)
      return
    }

    await cardoraService.updateCartItem(targetItem.id, quantity)
    await refreshBootstrap()
  }

  const moveCartItemToFavorites = async (cartItemId) => {
    if (!currentUser) return

    const targetItem = state.cartItems.find((item) => item.id === cartItemId)
    if (!targetItem || targetItem.itemType === CART_ITEM_TYPE_DRAW || targetItem.productId == null) return

    await cardoraService.addFavorite(targetItem.productId)

    if (targetItem) {
      await cardoraService.removeCartItem(targetItem.id)
    }

    await refreshBootstrap()
  }

  const markNotificationRead = async (notificationId) => {
    const targetNotification = state.notifications.find((item) => item.id === notificationId)
    if (!targetNotification || targetNotification.read) return

    setState((previous) => ({
      ...previous,
      notifications: previous.notifications.map((item) =>
        item.id === notificationId ? { ...item, read: true } : item,
      ),
    }))

    try {
      await cardoraService.markNotificationRead(notificationId)
    } catch (error) {
      setState((previous) => ({
        ...previous,
        notifications: previous.notifications.map((item) =>
          item.id === notificationId ? { ...item, read: false } : item,
        ),
      }))

      throw error
    }
  }

  const markProductViewed = useCallback((productId) => {
    if (!productId) return

    const normalizedProductId = String(productId)

    setState((previous) => {
      if (String(previous.recentlyViewedIds[0] ?? '') === normalizedProductId) {
        return previous
      }

      return {
        ...previous,
        recentlyViewedIds: [
          normalizedProductId,
          ...previous.recentlyViewedIds.filter((id) => String(id) !== normalizedProductId),
        ].slice(0, 8),
      }
    })
  }, [])

  const toggleProfileLike = async (profileUserId) => {
    if (!currentUser) return { success: false, message: 'Authentication required.' }
    if (currentUser.id === profileUserId) return { success: false, message: 'Invalid action.' }

    const profile = collectorProfilesDetailed.find((item) => item.userId === profileUserId)
    if (!profile?.handle) return { success: false, message: 'Profile not found.' }

    if (profile.likedByCurrentUser) {
      await cardoraService.unlikeProfile(profile.handle)
    } else {
      await cardoraService.likeProfile(profile.handle)
    }

    await refreshBootstrap()

    return { success: true, liked: !profile.likedByCurrentUser }
  }

  const toggleProfileFollow = async (profileUserId) => {
    if (!currentUser) return { success: false, message: 'Authentication required.' }
    if (currentUser.id === profileUserId) return { success: false, message: 'Invalid action.' }

    const profile = collectorProfilesDetailed.find((item) => item.userId === profileUserId)
    if (!profile?.handle) return { success: false, message: 'Profile not found.' }

    if (profile.followedByCurrentUser) {
      await cardoraService.unfollowProfile(profile.handle)
    } else {
      await cardoraService.followProfile(profile.handle)
    }

    await refreshBootstrap()

    return { success: true, following: !profile.followedByCurrentUser }
  }

  const sendMessage = async (conversationId, text) => {
    if (!currentUser || !text.trim()) return null

    const response = await cardoraService.sendMessage({
      conversation_id: conversationId,
      body: text.trim(),
    })

    await refreshBootstrap()
    return response
  }

  const markConversationRead = async (conversationId) => {
    if (!currentUser || !conversationId) return false

    await cardoraService.markConversationRead(conversationId)
    await refreshBootstrap()

    return true
  }

  const startConversationForOrder = async (order, initialMessage = '') => {
    if (!currentUser || !order?.productId) return null

    const existingConversation = state.conversations.find((conversation) => {
      const sameListing =
        Number(conversation.productId ?? conversation.listing_id) === Number(order.productId)
      const sameParticipants =
        [Number(conversation.buyerId ?? conversation.buyer_id), Number(conversation.sellerId ?? conversation.seller_id)].includes(Number(order.buyerId)) &&
        [Number(conversation.buyerId ?? conversation.buyer_id), Number(conversation.sellerId ?? conversation.seller_id)].includes(Number(order.sellerId))

      return sameListing && sameParticipants
    })

    if (existingConversation) {
      return existingConversation
    }

    const conversation = await cardoraService.createConversation({
      listing_id: Number(order.productId),
      ...(initialMessage.trim() ? { initial_message: initialMessage.trim() } : {}),
    })

    await refreshBootstrap()

    return {
      id: Number(conversation?.id),
      productId: Number(conversation?.listing_id ?? order.productId),
      buyerId: Number(conversation?.buyer_id ?? order.buyerId),
      sellerId: Number(conversation?.seller_id ?? order.sellerId),
    }
  }

  const startConversationForListing = async (listingId, initialMessage = '') => {
    if (!currentUser || !listingId) return null

    const existingConversation = state.conversations.find(
      (conversation) => Number(conversation.productId ?? conversation.listing_id) === Number(listingId),
    )

    if (existingConversation) {
      return existingConversation
    }

    const conversation = await cardoraService.createConversation({
      listing_id: Number(listingId),
      ...(initialMessage.trim() ? { initial_message: initialMessage.trim() } : {}),
    })

    await refreshBootstrap()

    return {
      id: Number(conversation?.id),
      productId: Number(conversation?.listing_id ?? listingId),
      buyerId: Number(conversation?.buyer_id ?? 0),
      sellerId: Number(conversation?.seller_id ?? 0),
    }
  }

  const createListingOffer = async ({ conversationId, totalAmount, note = '' }) => {
    if (!currentUser || !conversationId) return null

    const response = await cardoraService.createListingOffer(conversationId, {
      total_amount: Number(totalAmount),
      note: note?.trim() || null,
    })

    await refreshBootstrap()
    return response
  }

  const counterListingOffer = async ({ offerId, totalAmount, note = '' }) => {
    if (!currentUser || !offerId) return null

    const response = await cardoraService.counterListingOffer(offerId, {
      total_amount: Number(totalAmount),
      note: note?.trim() || null,
    })

    await refreshBootstrap()
    return response
  }

  const acceptListingOffer = async ({ offerId, note = '' }) => {
    if (!currentUser || !offerId) return null

    const response = await cardoraService.acceptListingOffer(offerId, {
      note: note?.trim() || null,
    })

    await refreshBootstrap()
    return response
  }

  const rejectListingOffer = async ({ offerId, note = '' }) => {
    if (!currentUser || !offerId) return null

    const response = await cardoraService.rejectListingOffer(offerId, {
      note: note?.trim() || null,
    })

    await refreshBootstrap()
    return response
  }

  const getMyReviewForOrder = async (orderId) => {
    if (!currentUser || !orderId) return null

    const reviews = await cardoraService.getReviews({
      order_id: orderId,
      reviewer_id: currentUser.id,
      public_only: 0,
      per_page: 1,
    })

    return Array.isArray(reviews) ? reviews[0] ?? null : null
  }

  const saveOrderReview = async (order, payload, reviewId = null) => {
    if (!currentUser || !order?.databaseId) return null

    const reviewPayload = {
      order_id: Number(order.databaseId),
      rating: Number(payload.rating),
      title: payload.title?.trim() || null,
      body: payload.body?.trim() || null,
      is_public: payload.isPublic ?? true,
    }

    const review = reviewId
      ? await cardoraService.updateReview(reviewId, reviewPayload)
      : await cardoraService.createReview(reviewPayload)

    await refreshBootstrap()

    return review
  }

  const placeAuctionBid = async (productId, amountInput) => {
    if (!currentUser) return { success: false, message: 'Authentication required.' }

    const gateMessage = getMarketplaceGateMessage('buy')
    if (gateMessage) {
      return {
        success: false,
        message: gateMessage,
      }
    }

    try {
      const bid = await cardoraService.createAuctionBid(productId, Number(amountInput))
      await refreshBootstrap()
      return {
        success: true,
        amount: Number(bid?.amount ?? amountInput),
        message: 'Bid submitted successfully.',
      }
    } catch (error) {
      return {
        success: false,
        message: error.message,
      }
    }
  }

  const placeOrder = async ({
    paymentMethod,
    shippingAddress,
    shippingSelections,
    billingAddress,
    acceptedOfferId = null,
  }) => {
    if (!currentUser) return null

    if (!acceptedOfferId && state.cartItems.length === 0) return null

      const gateMessage = getMarketplaceGateMessage('buy')
      if (gateMessage) {
        throw new Error(gateMessage)
      }

      const checkout = await cardoraService.startCheckoutSession({
        accepted_offer_id: acceptedOfferId ?? null,
        payment_method: paymentMethod,
        shipping_address: shippingAddress ?? null,
        shipping_selections: shippingSelections ?? null,
        billing_address: billingAddress ?? shippingAddress ?? null,
      })

    return checkout
  }

  const confirmOrderReceived = async (orderId) => {
    if (!currentUser || !orderId) return null

    const order = await cardoraService.confirmOrderReceived(orderId)
    await refreshBootstrap()

    return order
  }

  const cancelPendingOrder = async (orderId) => {
    if (!currentUser || !orderId) return false

    await cardoraService.deleteOrder(orderId)
    await refreshBootstrap()

    return true
  }

  const updateOrder = async (orderId, payload) => {
    if (!currentUser || !orderId) return null

    const order = await cardoraService.updateOrder(orderId, payload)
    await refreshBootstrap()

    return order
  }

  const getSellerConnectAccount = async () => {
    if (!currentUser) return null

    return cardoraService.getSellerConnectAccount()
  }

  const startSellerOnboarding = async () => {
    if (!currentUser) return null

    return cardoraService.startSellerOnboarding()
  }

  const openSellerStripeDashboard = async () => {
    if (!currentUser) return null

    return cardoraService.createSellerDashboardLoginLink()
  }

  const getSellerAccountManagementSession = async () => {
    if (!currentUser) return null

    return cardoraService.createSellerAccountManagementSession()
  }

  const getSellerBalanceSummary = async () => {
    if (!currentUser) return null

    return cardoraService.getSellerBalanceSummary()
  }

  const getSellerPayoutHistory = async () => {
    if (!currentUser) return null

    return cardoraService.getSellerPayoutHistory()
  }

  const prepareListingUploadMedia = async (payload) => {
    const existingMedia = Array.isArray(payload.uploadedMedia)
      ? payload.uploadedMedia.filter((item) => item?.url || item?.path)
      : []
    const uploadFiles = Array.isArray(payload.mediaFiles) ? payload.mediaFiles.filter(Boolean) : []
    const newlyUploadedMedia = uploadFiles.length
      ? await cardoraService.uploadFiles(uploadFiles, 'listings')
      : []

    return [...existingMedia, ...newlyUploadedMedia]
  }

  const buildListingSubmissionSummary = (response, payload, category, lotConfiguration, listingPayload) => ({
    id: response?.id ?? payload.title,
    title: response?.title_snapshot ?? payload.title,
    status: response?.status ?? listingPayload.status,
    saleFormat: response?.sale_format ?? listingPayload.sale_format,
    price: Number(response?.price ?? listingPayload.price ?? 0),
    categoryName: payload.categoryName || category.name,
    lotSummary: lotConfiguration
      ? {
          totalCards: lotConfiguration.total_cards,
          guaranteedHits: lotConfiguration.guaranteed_hits,
        }
      : null,
  })

  const buildListingRequestPayload = async (payload) => {
    const category = state.categories.find((item) => item.id === payload.categoryId)
    if (!category?.databaseId) return null

    const normalizedSaleFormat = String(payload.saleFormat || '').trim().toLowerCase()
    const saleFormat =
      normalizedSaleFormat === 'auction' || normalizedSaleFormat.includes('δημοπρασ')
        ? 'auction'
        : normalizedSaleFormat === 'trade' || normalizedSaleFormat.includes('trade')
          ? 'trade'
          : 'fixed_price'
    const isAuction = saleFormat === 'auction'
    const isTrade = saleFormat === 'trade'
    const isLootLot = payload.categoryId === 'cards' && payload.cardBundleMode === 'loot_lot'
    const uploadedMedia = await prepareListingUploadMedia(payload)

    const lotConfiguration = isLootLot
      ? {
          total_cards: Number(payload.lotCardCount || 0),
          guaranteed_hits: Number(payload.lotGuaranteedHits || 0),
          allow_individual_purchase: Boolean(payload.allowIndividualLotPurchase),
          named_cards: Array.isArray(payload.lotNamedCards) ? payload.lotNamedCards.filter(Boolean) : [],
          preview_cards:
            Array.isArray(payload.lotNamedCards) && payload.lotNamedCards.length
              ? payload.lotNamedCards.filter(Boolean).slice(0, 5)
              : String(payload.lotHighlights || '')
                  .split(',')
                  .map((item) => item.trim())
                  .filter(Boolean)
                  .slice(0, 5),
          themes: Array.isArray(payload.lotThemeTags)
            ? payload.lotThemeTags.filter(Boolean)
            : Array.isArray(payload.lotThemes)
              ? payload.lotThemes
              : String(payload.lotThemes || '')
                  .split(',')
                  .map((item) => item.trim())
                  .filter(Boolean),
          summary: payload.lotSummary,
          condition_mix: payload.lotCardConditionMix,
          individual_cards: Array.isArray(payload.lotIndividualCards)
            ? payload.lotIndividualCards
                .map((card, index) => ({
                  id: String(card?.id ?? '').trim() || `lot-card-${index + 1}`,
                  title: String(card?.title ?? '').trim(),
                  price: roundMoney(card?.price ?? 0),
                  status: card?.status ?? 'available',
                }))
                .filter((card) => card.title && card.price > 0)
            : [],
        }
      : null

    const tagList = Array.isArray(payload.tags)
      ? payload.tags
      : String(payload.tags || '')
          .split(',')
          .map((item) => item.trim())
          .filter(Boolean)

    const enabledDomesticCarriers = Array.isArray(payload.enabledDomesticCarriers)
      ? payload.enabledDomesticCarriers.filter(Boolean)
      : []
    const hasDhlDomesticCarrier = enabledDomesticCarriers.includes('DHL Express')
    const hasBoxNowDomesticCarrier = enabledDomesticCarriers.includes('BoxNow')
    const usesParcelTypeDomesticShipping = hasBoxNowDomesticCarrier
    const domesticShippingConfig = getDomesticShippingConfig(payload.categoryId)
    const domesticParcelType = usesParcelTypeDomesticShipping
      ? normalizeParcelType(
          payload.domesticParcelType ?? 'small',
          payload.domesticParcelType ?? 'small',
        )
      : null
    const dhlDomesticShippingFee = hasDhlDomesticCarrier
      ? resolveDhlDomesticShippingFee({
          ...payload,
        })
      : 0
    const boxNowDomesticShippingFee = hasBoxNowDomesticCarrier
      ? resolveDomesticShippingFee({
          ...payload,
          domesticShippingMode: 'legacy_boxnow',
          domesticShippingCarrier: 'BoxNow',
          domesticParcelType,
        })
      : 0
    const domesticShippingFee = hasDhlDomesticCarrier
      ? dhlDomesticShippingFee
      : boxNowDomesticShippingFee
    const cyprusShippingFee = usesParcelTypeDomesticShipping
      ? resolveCyprusShippingFee({
          ...payload,
          domesticParcelType,
        })
      : 0
    const packageDetails = normalizePackageDetails(payload)
    const domesticShippingCarrier =
      hasDhlDomesticCarrier
        ? 'DHL Express'
        : payload.domesticShippingCarrier ||
          domesticShippingConfig.carrier ||
          (usesParcelTypeDomesticShipping ? 'BoxNow' : 'DHL Express')
    const shipInternational = Boolean(payload.shipInternational)
    const internationalCarrier = payload.internationalCarrier || 'DHL Express'
    const internationalRates = Object.fromEntries(
      Object.entries(payload.internationalRates ?? {})
        .map(([zoneKey, value]) => [zoneKey, parseAmountInput(value)])
        .filter(([, value]) => value > 0),
    )
    const parcelRateSummary = `GR ${roundMoney(domesticShippingFee)}€ / CY ${
      cyprusShippingFee > 0 ? `${roundMoney(cyprusShippingFee)}€` : 'DHL fallback'
    }`

    const resolveListingMoney = (value) => {
      const numericValue = parseAmountInput(value)
      if (!numericValue || numericValue < 0) return 0
      return usesParcelTypeDomesticShipping && !hasDhlDomesticCarrier ? numericValue + domesticShippingFee : numericValue
    }

    const shippingSummaryEl = hasDhlDomesticCarrier && hasBoxNowDomesticCarrier
      ? shipInternational
        ? `Ελλάδα: DHL Express ${roundMoney(dhlDomesticShippingFee)}€ ή BoxNow (${parcelRateSummary}). Εξωτερικό: DHL με κόστος ανά ζώνη.`
        : `Ελλάδα: DHL Express ${roundMoney(dhlDomesticShippingFee)}€ ή BoxNow (${parcelRateSummary}).`
      : usesParcelTypeDomesticShipping
        ? shipInternational
          ? `Ελλάδα/Κύπρος: BoxNow ανά τύπο δέματος (${parcelRateSummary}). Εξωτερικό: DHL με κόστος ανά ζώνη.`
          : `Ελλάδα/Κύπρος: BoxNow ανά τύπο δέματος (${parcelRateSummary}).`
        : shipInternational
          ? `Ελλάδα: DHL Express με χρέωση ${roundMoney(domesticShippingFee)}€, βάρος ${packageDetails.weightKg}kg και διαστάσεις ${packageDetails.lengthCm}x${packageDetails.widthCm}x${packageDetails.heightCm}cm. Εξωτερικό: DHL με κόστος ανά ζώνη.`
          : `Ελλάδα: DHL Express με χρέωση ${roundMoney(domesticShippingFee)}€, βάρος ${packageDetails.weightKg}kg και διαστάσεις ${packageDetails.lengthCm}x${packageDetails.widthCm}x${packageDetails.heightCm}cm.`
    const shippingSummaryEn = hasDhlDomesticCarrier && hasBoxNowDomesticCarrier
      ? shipInternational
        ? `Domestic Greece: DHL Express ${roundMoney(dhlDomesticShippingFee)}€ or BoxNow (${parcelRateSummary}). International: DHL with zone-based pricing.`
        : `Domestic Greece: DHL Express ${roundMoney(dhlDomesticShippingFee)}€ or BoxNow (${parcelRateSummary}).`
      : usesParcelTypeDomesticShipping
        ? shipInternational
          ? `Greece/Cyprus: BoxNow parcel rates (${parcelRateSummary}). International: DHL with zone-based pricing.`
          : `Greece/Cyprus: BoxNow parcel rates (${parcelRateSummary}).`
        : shipInternational
          ? `Domestic: DHL Express with a ${roundMoney(domesticShippingFee)}€ fee, ${packageDetails.weightKg}kg weight and ${packageDetails.lengthCm}x${packageDetails.widthCm}x${packageDetails.heightCm}cm parcel size. International: DHL with zone-based pricing.`
          : `Domestic: DHL Express with a ${roundMoney(domesticShippingFee)}€ fee, ${packageDetails.weightKg}kg weight and ${packageDetails.lengthCm}x${packageDetails.widthCm}x${packageDetails.heightCm}cm parcel size.`
    const shippingInfoEl = [shippingSummaryEl, payload.shippingNotes].filter(Boolean).join(' ')
    const shippingInfoEn = [shippingSummaryEn, payload.shippingNotes].filter(Boolean).join(' ')
    const shippingProfile = shipInternational
      ? hasDhlDomesticCarrier
        ? 'dhl_domestic_dhl_international'
        : 'boxnow_domestic_dhl_international'
      : hasDhlDomesticCarrier
        ? 'dhl_domestic_only'
        : 'boxnow_domestic_only'
    const shippingMethods = shipInternational
      ? [...new Set([...enabledDomesticCarriers, internationalCarrier].filter(Boolean))]
      : [...new Set(enabledDomesticCarriers.length ? enabledDomesticCarriers : [domesticShippingCarrier])]
    const domesticShippingPayload = {
      carrier: domesticShippingCarrier,
      fee: domesticShippingFee,
      included_in_price: usesParcelTypeDomesticShipping && !hasDhlDomesticCarrier,
      available_carriers: shippingMethods.filter((method) => method === 'DHL Express' || method === 'BoxNow'),
      package: {
        weight_kg: packageDetails.weightKg,
        length_cm: packageDetails.lengthCm,
        width_cm: packageDetails.widthCm,
        height_cm: packageDetails.heightCm,
      },
      ...(hasDhlDomesticCarrier
        ? {
            dhl: {
              fee: dhlDomesticShippingFee,
              package: {
                weight_kg: packageDetails.weightKg,
                length_cm: packageDetails.lengthCm,
                width_cm: packageDetails.widthCm,
                height_cm: packageDetails.heightCm,
              },
            },
          }
        : {}),
      ...(usesParcelTypeDomesticShipping
        ? {
            boxnow: {
              parcel_type: domesticParcelType,
              rates: {
                gr: boxNowDomesticShippingFee,
                cy: cyprusShippingFee,
              },
            },
            parcel_type: domesticParcelType,
            rates: {
              gr: boxNowDomesticShippingFee,
              cy: cyprusShippingFee,
            },
          }
        : {}),
    }

    const listingPayload = {
      category_id: category.databaseId,
      title_snapshot: payload.title,
      price: isAuction ? resolveListingMoney(payload.startingBid) : resolveListingMoney(payload.price),
      old_price: isTrade ? null : payload.oldPrice ? resolveListingMoney(payload.oldPrice) : null,
      minimum_offer: !isAuction && !isTrade && payload.minimumOffer ? resolveListingMoney(payload.minimumOffer) : null,
      quantity: isAuction || isTrade ? 1 : Number(payload.quantity || 1),
      available_quantity: isAuction || isTrade ? 1 : Number(payload.quantity || 1),
      condition: payload.condition || null,
      rarity: payload.rarity || null,
      status: payload.publishAction === 'draft' ? 'draft' : 'pending_review',
      sale_format: saleFormat,
      shipping_cost: domesticShippingFee,
      shipping_profile: shippingProfile,
      shipping_methods: shippingMethods,
      dispatch_time: payload.dispatchTime || null,
      packaging_notes: payload.shippingNotes || payload.mediaNotes || null,
      availability: payload.availability || 'in_stock',
      accept_offers: saleFormat === 'fixed_price' ? Boolean(payload.acceptOffers) : false,
      is_featured: Boolean(payload.featured && payload.featuredPaymentId),
      featured_payment_id: payload.featuredPaymentId ?? null,
      starting_bid: isAuction ? resolveListingMoney(payload.startingBid) : null,
      current_bid: isAuction ? resolveListingMoney(payload.startingBid) : null,
      reserve_price: isAuction && payload.reservePrice ? resolveListingMoney(payload.reservePrice) : null,
      bid_increment: isAuction ? parseAmountInput(payload.bidIncrement || 1) : null,
      buyout_price: isAuction && payload.buyoutPrice ? resolveListingMoney(payload.buyoutPrice) : null,
      auction_ends_at: isAuction ? payload.auctionEndsAt : null,
      lot_snapshot: lotConfiguration,
      compliance_flags: payload.complianceAcknowledgements ?? [],
      attributes: {
        franchise: payload.franchise || null,
        franchise_group: payload.franchiseGroup || null,
        series: payload.series || payload.subcategory || null,
        brand: payload.brand || null,
        publisher: payload.brand || null,
        condition: payload.condition || null,
        rarity: payload.rarity || null,
        type_label: payload.typeLabel || payload.subcategory || null,
        graded_company: payload.gradedCompany || null,
        grade: payload.grade || null,
        set_name: payload.setName || null,
        card_number: payload.cardNumber || null,
        issue_number: payload.issueNumber || null,
        printing: payload.printing || null,
        year: payload.year || null,
        language: payload.language || null,
        character_name: payload.characterName || null,
        manufacturer_line: payload.manufacturerLine || null,
        scale: payload.scale || null,
        figure_height: payload.figureHeight || null,
        material: payload.material || null,
        miniature_subtype: payload.miniatureSubtype || null,
        display_status: payload.displayStatus || null,
        box_condition: payload.boxCondition || null,
        accessories: payload.accessories || null,
        edition: payload.edition || null,
        serial_reference: payload.serialReference || null,
        bundle_contents: payload.bundleContents || null,
        writer: payload.writer || null,
        artist: payload.artist || null,
        cover_artist: payload.coverArtist || null,
        signed_by: payload.signedBy || null,
        page_count: payload.pageCount || null,
        isbn: payload.isbn || null,
        authenticity: payload.authenticity || null,
        returns_policy: payload.shippingNotes || null,
        shipping: {
          package: {
            weight_kg: packageDetails.weightKg,
            length_cm: packageDetails.lengthCm,
            width_cm: packageDetails.widthCm,
            height_cm: packageDetails.heightCm,
          },
          domestic: domesticShippingPayload,
          international: {
            enabled: shipInternational,
            carrier: internationalCarrier,
            rates: internationalRates,
          },
        },
      },
      product: {
        title: payload.title,
        subtitle: payload.subtitle || null,
        franchise: payload.franchise || null,
        series: payload.series || payload.subcategory || null,
        brand: payload.brand || null,
        year: payload.year ? Number(payload.year) : null,
        language: payload.language || null,
        set_name: payload.setName || null,
        item_number: payload.cardNumber || payload.issueNumber || null,
        binder_card_id: payload.binderCardId ? Number(payload.binderCardId) : null,
        product_type: payload.typeLabel || payload.subcategory || null,
        description: payload.description || '',
        specifications: {
          grading_info: payload.gradingInfo || null,
          condition_notes: payload.conditionNotes || null,
          graded_company: payload.gradedCompany || null,
          grade: payload.grade || null,
          franchise_group: payload.franchiseGroup || null,
          publisher: payload.brand || null,
          issue_number: payload.issueNumber || null,
          printing: payload.printing || null,
          character_name: payload.characterName || null,
          manufacturer_line: payload.manufacturerLine || null,
          scale: payload.scale || null,
          figure_height: payload.figureHeight || null,
          material: payload.material || null,
          miniature_subtype: payload.miniatureSubtype || null,
          display_status: payload.displayStatus || null,
          box_condition: payload.boxCondition || null,
          accessories: payload.accessories || null,
          edition: payload.edition || null,
          serial_reference: payload.serialReference || null,
          bundle_contents: payload.bundleContents || null,
          writer: payload.writer || null,
          artist: payload.artist || null,
          cover_artist: payload.coverArtist || null,
          signed_by: payload.signedBy || null,
          page_count: payload.pageCount || null,
          isbn: payload.isbn || null,
        },
        tags: tagList,
        media: uploadedMedia.map((file, index) => ({
          path: file.path ?? file.storage_path ?? null,
          url: file.url ?? null,
          kind:
            file.kind ??
            (String(file.mime_type ?? '').startsWith('image/') ? 'image' : 'image'),
          label:
            file.label ??
            file.original_name ??
            `Image ${index + 1}`,
        })),
        authenticity_notes: payload.authenticity || null,
        is_authenticated: Boolean(payload.gradingInfo || payload.authenticity),
        is_lot: Boolean(lotConfiguration),
        lot_configuration: lotConfiguration,
        metadata: {
          translations: {
            el: {
              description: payload.description || '',
              short_description: payload.subtitle || payload.title,
              shipping_info: shippingInfoEl,
              authenticity: payload.authenticity || '',
              highlights: tagList.slice(0, 5),
            },
            en: {
              description: payload.description || '',
              short_description: payload.subtitle || payload.title,
              shipping_info: shippingInfoEn,
              authenticity: payload.authenticity || '',
              highlights: tagList.slice(0, 5),
            },
          },
          visual: {
            gradient: payload.visualGradient ?? 'from-[#204178] via-[#14223b] to-[#09111d]',
            label: isAuction ? 'Auction' : isTrade ? 'Trade' : isLootLot ? 'Loot Lot' : 'Listing',
          },
          shipping: {
            package: {
              weight_kg: packageDetails.weightKg,
              length_cm: packageDetails.lengthCm,
              width_cm: packageDetails.widthCm,
              height_cm: packageDetails.heightCm,
            },
            domestic: domesticShippingPayload,
            international: {
              enabled: shipInternational,
              carrier: internationalCarrier,
              rates: internationalRates,
            },
          },
        },
      },
    }

    return { category, listingPayload, lotConfiguration }
  }

  const createListing = async (payload) => {
    if (!currentUser) return null

    const gateMessage = getMarketplaceGateMessage('sell')
    if (gateMessage) {
      throw new Error(gateMessage)
    }

    const submission = await buildListingRequestPayload(payload)
    if (!submission) return null

    const { category, listingPayload, lotConfiguration } = submission
    const createdListing = await cardoraService.createListing(listingPayload)
    await refreshBootstrap()

    return buildListingSubmissionSummary(
      createdListing,
      payload,
      category,
      lotConfiguration,
      listingPayload,
    )
  }

  const updateListing = async (listingId, payload) => {
    if (!currentUser || !listingId) return null

    const gateMessage = getMarketplaceGateMessage('sell')
    if (gateMessage) {
      throw new Error(gateMessage)
    }

    const submission = await buildListingRequestPayload(payload)
    if (!submission) return null

    const { category, listingPayload, lotConfiguration } = submission
    const existingListing = state.myListings.find(
      (item) =>
        Number(item.databaseId ?? item.id) === Number(listingId) ||
        Number(item.id) === Number(listingId),
    )
    const targetListingId = Number(existingListing?.databaseId ?? listingId)
    const updatedListing = await cardoraService.updateListing(targetListingId, listingPayload)
    await refreshBootstrap()

    return buildListingSubmissionSummary(
      updatedListing,
      payload,
      category,
      lotConfiguration,
      listingPayload,
    )
  }

  const getListingForEdit = async (listingId) => {
    if (!currentUser || !listingId) return null

    const listing = await cardoraService.getListing(listingId)
    if (Number(listing?.seller_id ?? 0) !== Number(currentUser.id)) {
      throw new Error(
        locale === 'en'
          ? 'You can only edit your own listings.'
          : 'Μπορείς να επεξεργαστείς μόνο δικές σου αγγελίες.',
      )
    }

    return listing
  }

  const toCollectionMediaPayload = (uploads = []) =>
    uploads.map((file) => ({
      disk: file.disk,
      path: file.path,
      url: file.url,
      original_name: file.original_name,
      mime_type: file.mime_type,
      file_size: file.file_size,
      uploaded_at: file.uploaded_at,
    }))

  const createCollectionEntry = async (payload) => {
    if (!currentUser) return null

    const uploadFiles = Array.isArray(payload?.mediaFiles) ? payload.mediaFiles.filter(Boolean) : []
    const uploadedFiles = uploadFiles.length
      ? await cardoraService.uploadFiles(uploadFiles, 'collection')
      : []
    const preservedMedia = Array.isArray(payload?.media) ? payload.media.filter(Boolean) : []
    const media = [...preservedMedia, ...toCollectionMediaPayload(uploadedFiles)]

    const response = await cardoraService.createProfileCollectionEntry({
      title: String(payload?.title ?? '').trim() || null,
      caption: String(payload?.caption ?? '').trim() || null,
      visibility: payload?.visibility === 'private' ? 'private' : 'public',
      is_featured: Boolean(payload?.isFeatured),
      sort_order: Number(payload?.sortOrder ?? 0),
      media,
      product_id: payload?.productId ? Number(payload.productId) : null,
    })

    await refreshBootstrap()
    return response
  }

  const updateCollectionEntry = async (collectionEntryId, payload) => {
    if (!currentUser || !collectionEntryId) return null

    const uploadFiles = Array.isArray(payload?.mediaFiles) ? payload.mediaFiles.filter(Boolean) : []
    const uploadedFiles = uploadFiles.length
      ? await cardoraService.uploadFiles(uploadFiles, 'collection')
      : []
    const preservedMedia = Array.isArray(payload?.media) ? payload.media.filter(Boolean) : []
    const media = [...preservedMedia, ...toCollectionMediaPayload(uploadedFiles)]

    const response = await cardoraService.updateProfileCollectionEntry(collectionEntryId, {
      title: payload?.title != null ? String(payload.title).trim() || null : undefined,
      caption: payload?.caption != null ? String(payload.caption).trim() || null : undefined,
      visibility:
        payload?.visibility != null
          ? payload.visibility === 'private'
            ? 'private'
            : 'public'
          : undefined,
      is_featured: payload?.isFeatured != null ? Boolean(payload.isFeatured) : undefined,
      sort_order: payload?.sortOrder != null ? Number(payload.sortOrder) : undefined,
      media: payload?.media != null ? media : undefined,
      product_id:
        payload?.productId !== undefined
          ? payload.productId != null
            ? Number(payload.productId)
            : null
          : undefined,
    })

    await refreshBootstrap()
    return response
  }

  const deleteCollectionEntry = async (collectionEntryId) => {
    if (!currentUser || !collectionEntryId) return null

    await cardoraService.deleteProfileCollectionEntry(collectionEntryId)
    await refreshBootstrap()
    return true
  }

  const updateListingStatus = async (listingId, status) => {
    const listing = state.myListings.find(
      (item) => item.id === listingId || item.databaseId === listingId,
    )

    if (!listing?.databaseId) return null

    await cardoraService.updateListing(listing.databaseId, { status })
    await refreshBootstrap()
    return true
  }

  const deleteListing = async (listingId) => {
    const listing = state.myListings.find(
      (item) => item.id === listingId || item.databaseId === listingId,
    )

    if (!listing?.databaseId) return null

    await cardoraService.deleteListing(listing.databaseId)
    await refreshBootstrap()
    return true
  }

  const createSupportTicket = async (payload) => {
    const ticket = await cardoraService.submitSupportTicket({
      order_id: payload.orderId || null,
      subject: payload.subject,
      category: payload.category,
      priority: payload.priority || 'normal',
      description: payload.description || payload.message || '',
      status: payload.status || 'open',
      attachments: Array.isArray(payload.attachments) ? payload.attachments : [],
      metadata: payload.metadata || null,
    })

    await refreshBootstrap()
    return ticket
  }

  const createCommunityDraw = async (payload) => {
    if (!currentUser) return null

    const gateMessage = getMarketplaceGateMessage('sell')
    if (gateMessage) {
      throw new Error(gateMessage)
    }

    const targetEntries = Number(payload.targetEntries || 0)
    const entryPrice = Number(payload.entryPrice || 0)
    const uploadImageFiles =
      Array.isArray(payload?.imageFiles) && typeof File !== 'undefined'
        ? payload.imageFiles.filter((file) => file instanceof File)
        : typeof File !== 'undefined' && payload?.imageFile instanceof File
          ? [payload.imageFile]
          : []
    const uploadedImages = uploadImageFiles.length
      ? (await cardoraService.uploadFiles(uploadImageFiles, 'draws')) ?? []
      : []
    const uploadedImageUrls = uploadedImages
      .map((item) => String(item?.url ?? '').trim())
      .filter(Boolean)
    const manualImageUrl = String(payload?.imageUrl ?? '').trim()
    const galleryImageUrls = [...new Set([...uploadedImageUrls, ...(manualImageUrl ? [manualImageUrl] : [])])]
    const imageUrl = galleryImageUrls[0] ?? null

    const draw = await cardoraService.createDrawCampaign({
      campaign_type: 'community_raffle',
      title: payload.title,
      slug: toSlug(payload.title),
      subtitle: `${formatNumber(targetEntries)} x ${formatCurrency(entryPrice)}`,
      description: payload.description,
      prize_title: payload.prizeTitle,
      prize_category: payload.prizeCategory,
      prize_condition: payload.prizeCondition,
      prize_value: Number(payload.prizeValue || 0),
      entry_price: entryPrice,
      target_amount: targetEntries * entryPrice,
      current_amount: 0,
      target_entries: targetEntries,
      entries_issued: 0,
      sold_entries: 0,
      participants_count: 0,
      max_entries_per_user: Number(payload.maxEntriesPerUser || 1),
      status: payload.status === 'draft' ? 'draft' : 'review',
      requires_verification: Boolean(payload.requiresVerification),
      shipping_covered: Boolean(payload.shippingCovered),
      fairness_note: payload.fairnessNote || '',
      dispatch_window: payload.dispatchWindow || '',
      visual: {
        gradient: payload.visualGradient ?? 'from-[#29465d] via-[#182033] to-[#09111d]',
        label: 'Community Draw',
        ...(imageUrl ? { imageUrl } : {}),
        ...(galleryImageUrls.length ? { gallery: galleryImageUrls } : {}),
      },
      ends_at: payload.endsAt,
      draw_at: payload.drawAt,
    })

    await refreshBootstrap()
    return draw
  }

  const updateCommunityDraw = async (drawId, payload) => {
    if (!currentUser || !drawId) return null

    const response = await cardoraService.updateDrawCampaign(drawId, payload)
    await refreshBootstrap()
    return response
  }

  const updateCommunityDrawStatus = async (drawId, status) => {
    if (!status) return null

    return updateCommunityDraw(drawId, { status })
  }

  const deleteCommunityDraw = async (drawId) => {
    if (!currentUser || !drawId) return null

    await cardoraService.deleteDrawCampaign(drawId)
    await refreshBootstrap()
    return true
  }

  const joinDraw = async (drawId, entries = 1) => {
    return addDrawToCart(drawId, entries)
  }

  const submitVerificationSection = async (sectionId, payload) => {
    if (!currentUser || !state.accountVerification) return null

    const section = state.accountVerification.sections.find((item) => item.id === sectionId)
    if (!section) return null

    const fileFields = section.fields.filter((field) => field.type === 'file')
    const filesToUpload = fileFields.flatMap((field) => {
      const value = payload[field.name]

      if (Array.isArray(value)) return value.filter(Boolean)
      return value ? [value] : []
    })

    const uploadedFiles = filesToUpload.length
      ? await cardoraService.uploadFiles(filesToUpload, `verification-${sectionId}`)
      : []

    let uploadIndex = 0
    const documents = fileFields.flatMap((field) => {
      const value = payload[field.name]
      const files = Array.isArray(value) ? value.filter(Boolean) : value ? [value] : []

      return files
        .map(() => {
          const uploaded = uploadedFiles[uploadIndex]
          uploadIndex += 1

          return uploaded
            ? {
                document_type: field.label,
                storage_disk: uploaded.disk,
                storage_path: uploaded.path,
                original_name: uploaded.original_name,
                mime_type: uploaded.mime_type,
                file_size: uploaded.file_size,
                metadata: {
                  field_name: field.name,
                },
                uploaded_at: uploaded.uploaded_at,
              }
            : null
        })
        .filter(Boolean)
    })

    const cleanPayload = Object.fromEntries(
      section.fields
        .filter((field) => field.type !== 'file')
        .map((field) => [field.name, payload[field.name] ?? null]),
    )

    const submission = {
      verification_type: sectionId,
      payload: cleanPayload,
      requirements_snapshot: {
        accepted_documents: section.acceptedDocuments,
        checklist: section.checklist,
      },
      documents,
    }

    if (section.submissionId) {
      await cardoraService.updateVerification(section.submissionId, submission)
    } else {
      await cardoraService.submitVerification(submission)
    }

    await refreshBootstrap()
    return true
  }

  const getBlogPostBySlug = (slug) => state.blogPosts.find((post) => post.slug === slug) ?? null

  const getCollectorProfileByHandle = (handle) =>
    collectorProfilesDetailed.find((profile) => profile.handle === handle) ?? null

  const getCollectorProfileByUserId = (userId) =>
    collectorProfilesDetailed.find((profile) => profile.userId === userId) ?? null

  const getRelatedBlogPosts = (slug, category) =>
    latestBlogPosts
      .filter((post) => post.slug !== slug && (!category || post.category === category))
      .slice(0, 3)

  const scoreProductForQuery = (product, query) => {
    const normalizedQuery = normalizeSearchText(query)
    if (!normalizedQuery) return 0

    const queryTokens = tokenizeSearchText(query)
    const index = searchIndexByProductId.get(product.id)
    if (!index) return 0

    let score = 0

    if (index.title === normalizedQuery) score += 220
    if (index.title.startsWith(normalizedQuery)) score += 160
    if (index.title.includes(normalizedQuery)) score += 110
    if (index.subtitle.includes(normalizedQuery)) score += 80
    if (index.franchise.includes(normalizedQuery)) score += 74
    if (index.series.includes(normalizedQuery)) score += 66
    if (index.brand.includes(normalizedQuery)) score += 62
    if (index.typeLabel.includes(normalizedQuery)) score += 56
    if (index.setName.includes(normalizedQuery)) score += 54
    if (index.seller.includes(normalizedQuery)) score += 52
    if (index.combined.includes(normalizedQuery)) score += 24

    let tokenHits = 0
    queryTokens.forEach((token) => {
      if (index.tokenSet.has(token)) {
        score += 20
        tokenHits += 1
        return
      }

      if (
        index.title.startsWith(token) ||
        index.subtitle.startsWith(token) ||
        index.franchise.startsWith(token) ||
        index.series.startsWith(token) ||
        index.brand.startsWith(token) ||
        index.typeLabel.startsWith(token) ||
        index.setName.startsWith(token)
      ) {
        score += 14
        tokenHits += 1
        return
      }

      if (index.combined.includes(token)) {
        score += 7
        tokenHits += 1
      }
    })

    if (queryTokens.length && tokenHits === queryTokens.length) score += 22

    return score
  }

  const productMatchesFilter = (product, key, selectedValue) => {
    if (isAllOption(selectedValue)) return true

    if (key === 'price') {
      return matchesPriceFilter(product.price, selectedValue)
    }

    if (key === 'sellerRating') {
      const threshold = parseSellerThreshold(selectedValue)
      if (threshold == null) return true
      return Number(product.sellerRating ?? 0) >= threshold
    }

    if (key === 'graded') {
      const normalized = normalizeSearchText(selectedValue)

      if (
        normalized === 'graded' ||
        normalized === '\u03b4\u03b9\u03b1\u03b2\u03b1\u03b8\u03bc\u03b9\u03c3\u03bc\u03b5\u03bd\u03bf'
      ) {
        return isProductGraded(product)
      }

      if (
        normalized === 'ungraded' ||
        normalized === 'raw' ||
        normalized === '\u03bc\u03b7 \u03b4\u03b9\u03b1\u03b2\u03b1\u03b8\u03bc\u03b9\u03c3\u03bc\u03b5\u03bd\u03bf'
      ) {
        return !isProductGraded(product)
      }

      return [product.gradedCompany, product.grade].some(
        (candidate) => normalizeSearchText(candidate) === normalized,
      )
    }

    if (key === 'availability') {
      return normalizeSearchText(product.availability) === normalizeSearchText(selectedValue)
    }

    const values = extractFilterValues(product, key)
    if (!values.length) return false

    return values.some(
      (candidate) => normalizeSearchText(candidate) === normalizeSearchText(selectedValue),
    )
  }

  const getFilteredProductsWithScore = ({ categoryId, filters = {}, ignoreKey = null }) => {
    const normalizedFilters = createSearchFilters(filters)
    const queryText = normalizedFilters.search

    return productsWithSellers
      .filter((product) => !categoryId || product.categoryId === categoryId)
      .map((product) => ({
        product,
        score: scoreProductForQuery(product, queryText),
      }))
      .filter(({ product, score }) => {
        if (ignoreKey !== 'search' && queryText && score <= 0) return false

        return FACET_FILTER_KEYS.every((key) => {
          if (ignoreKey === key) return true
          return productMatchesFilter(product, key, normalizedFilters[key])
        })
      })
  }

  const searchProducts = (criteria = {}) => {
    const filters = createSearchFilters({
      search: criteria.query ?? criteria.search,
      price: criteria.price,
      condition: criteria.condition,
      rarity: criteria.rarity,
      franchise: criteria.franchise,
      brand: criteria.brand,
      productType: criteria.productType,
      graded: criteria.graded,
      availability: criteria.availability,
      sellerRating: criteria.sellerRating,
    })

    return getFilteredProductsWithScore({
      categoryId: criteria.categoryId,
      filters,
    })
      .sort((a, b) => {
        const featuredDelta = compareFeaturedProducts(a.product, b.product)
        if (featuredDelta !== 0) return featuredDelta

        const personalizationDelta =
          getPersonalizationScore(b.product) - getPersonalizationScore(a.product)
        if (personalizationDelta !== 0) return personalizationDelta

        if (filters.search) {
          if (b.score !== a.score) return b.score - a.score
          if (Number(b.product.sellerRating ?? 0) !== Number(a.product.sellerRating ?? 0)) {
            return Number(b.product.sellerRating ?? 0) - Number(a.product.sellerRating ?? 0)
          }
        }

        return new Date(b.product.listedAt ?? 0) - new Date(a.product.listedAt ?? 0)
      })
      .map(({ product }) => product)
  }

  const getDynamicFacets = ({ categoryId, filters = {} }) => {
    const normalizedFilters = createSearchFilters(filters)
    const allLabel = locale === 'en' ? 'All' : GREEK_ALL

    const buildOption = (value, count, selectedValue, label = value) => ({
      value,
      label,
      count,
      selected: String(value) === String(selectedValue),
      disabled: count === 0 && String(value) !== String(selectedValue),
    })

    const groups = FACET_FILTER_KEYS.map((key) => {
      const selectedValue = normalizedFilters[key]
      const scopedProducts = getFilteredProductsWithScore({
        categoryId,
        filters: normalizedFilters,
        ignoreKey: key,
      }).map((entry) => entry.product)

      if (key === 'price') {
        let options = [
          buildOption(ALL_FILTER_VALUE, scopedProducts.length, selectedValue, allLabel),
          ...PRICE_FILTER_OPTIONS.map((option) => {
            const count = scopedProducts.filter(
              (product) => Number(product.price) >= option.min && Number(product.price) <= option.max,
            ).length

            return buildOption(option.value, count, selectedValue, option.labels[locale])
          }).filter((option) => option.count > 0 || option.selected),
        ]

        options = ensureSelectedOption(options, selectedValue, getPriceFilterLabel(selectedValue, locale))

        return {
          key,
          label: formatSearchLabel(locale, key),
          options,
        }
      }

      if (key === 'sellerRating') {
        const options = [
          buildOption(ALL_FILTER_VALUE, scopedProducts.length, selectedValue, allLabel),
          ...SELLER_RATING_OPTIONS.map((option) => {
            const count = scopedProducts.filter(
              (product) => Number(product.sellerRating ?? 0) >= option.threshold,
            ).length

            return buildOption(option.value, count, selectedValue, option.value)
          }).filter((option) => option.count > 0 || option.selected),
        ]

        return {
          key,
          label: formatSearchLabel(locale, key),
          options,
        }
      }

      const counts = new Map()
      scopedProducts.forEach((product) => {
        extractFilterValues(product, key).forEach((value) => {
          if (!value) return
          counts.set(value, (counts.get(value) ?? 0) + 1)
        })
      })

      let options = [
        buildOption(ALL_FILTER_VALUE, scopedProducts.length, selectedValue, allLabel),
        ...[...counts.entries()]
          .sort((left, right) => {
            const countDelta = right[1] - left[1]
            if (countDelta !== 0) return countDelta
            return String(left[0]).localeCompare(String(right[0]))
          })
          .map(([value, count]) => {
            if (key === 'graded') {
              return buildOption(value, count, selectedValue, getGradedFilterLabel(value, locale))
            }

            return buildOption(value, count, selectedValue)
          })
          .filter((option) => option.count > 0 || option.selected),
      ]

      if (key === 'graded') {
        options = ensureSelectedOption(options, selectedValue, getGradedFilterLabel(selectedValue, locale))
      } else {
        options = ensureSelectedOption(options, selectedValue)
      }

      return {
        key,
        label: formatSearchLabel(locale, key),
        options,
      }
    })

    return groups.filter(
      (group) =>
        group.options.length > 1 ||
        (!isAllOption(normalizedFilters[group.key]) && group.options.length > 0),
    )
  }

  const getSearchSuggestions = (query, { categoryId, limit = 5 } = {}) => {
    const normalizedQuery = normalizeSearchText(query)

    const scoredProducts = productsWithSellers
      .filter((product) => !categoryId || product.categoryId === categoryId)
      .map((product) => ({
        product,
        score: normalizedQuery ? scoreProductForQuery(product, normalizedQuery) : 0,
        personalization: getPersonalizationScore(product),
      }))
      .filter(({ score }) => (!normalizedQuery ? true : score > 0))
      .sort((a, b) => {
        if (b.personalization !== a.personalization) return b.personalization - a.personalization
        if (normalizedQuery && b.score !== a.score) return b.score - a.score
        return new Date(b.product.listedAt ?? 0) - new Date(a.product.listedAt ?? 0)
      })
      .slice(0, limit)
      .map(({ product }) => {
        const previewMedia = Array.isArray(product.media)
          ? product.media.find((item) => item?.url)
          : null

        return {
          id: product.id,
          slug: product.slug,
          title: product.title,
          subtitle: product.subtitle,
          price: Number(product.price ?? 0),
          categoryId: product.categoryId,
          categoryName: product.category?.name,
          franchise: product.franchise,
          mediaUrl: previewMedia?.url ?? null,
        }
      })

    return scoredProducts
  }

  const personalizedCategorySummary = useMemo(
    () =>
      [...userInterestProfile.categories.entries()]
        .sort((left, right) => right[1] - left[1])
        .slice(0, 3)
        .map(([value, score]) => ({ value, score })),
    [userInterestProfile],
  )

  return (
    <MarketplaceContext.Provider
      value={{
        ...state,
        users: mergedUsers,
        productsWithSellers,
        cartDetailed,
        favoriteProducts,
        recentProducts,
        trendingProducts,
        topSellers,
        latestBlogPosts,
        featuredBlogPosts,
        drawCampaignsDetailed,
        featuredDraws,
        platformDraws,
        communityDraws,
        myHostedCommunityDraws,
        myDrawParticipations,
        drawOverview,
        collectorProfilesDetailed,
        myCollectionEntries: myCollectionEntriesDetailed,
        personalizedCategorySummary,
        marketplaceAccess,
        getMarketplaceGateMessage,
        notificationsUnread,
        cartSummary: {
          subtotal: cartSubtotal,
          shipping: cartShipping,
          serviceFee: cartServiceFee,
          lowValueFee: cartLowValueFee,
          total: cartTotal,
          totalQuantity: cartTotalQuantity,
          containsPhysicalItems: cartContainsPhysicalItems,
          containsDrawEntries: cartContainsDrawEntries,
          totalLabel: formatCurrency(cartTotal),
        },
        refreshBootstrap,
        toggleFavorite,
        addToCart,
        addDrawToCart,
        removeFromCart,
        updateCartQuantity,
        moveCartItemToFavorites,
        markNotificationRead,
        markProductViewed,
        toggleProfileLike,
          toggleProfileFollow,
          sendMessage,
          markConversationRead,
          startConversationForOrder,
          startConversationForListing,
          createListingOffer,
          counterListingOffer,
          acceptListingOffer,
          rejectListingOffer,
          getMyReviewForOrder,
        saveOrderReview,
        placeAuctionBid,
        placeOrder,
        confirmOrderReceived,
        cancelPendingOrder,
        updateOrder,
        getSellerConnectAccount,
        startSellerOnboarding,
        openSellerStripeDashboard,
        getSellerAccountManagementSession,
        getSellerBalanceSummary,
        getSellerPayoutHistory,
        createListing,
        updateListing,
        getListingForEdit,
        createCollectionEntry,
        updateCollectionEntry,
        deleteCollectionEntry,
        updateListingStatus,
        deleteListing,
        createSupportTicket,
        createCommunityDraw,
        updateCommunityDraw,
        updateCommunityDrawStatus,
        deleteCommunityDraw,
        joinDraw,
        submitVerificationSection,
        getCollectorProfileByHandle,
        getCollectorProfileByUserId,
        getBlogPostBySlug,
        getRelatedBlogPosts,
        searchProducts,
        getDynamicFacets,
        getSearchSuggestions,
        getPersonalizationScore,
        defaultSearchFilters: DEFAULT_SEARCH_FILTERS,
        allFilterValue: ALL_FILTER_VALUE,
      }}
    >
      {children}
    </MarketplaceContext.Provider>
  )
}

export const useMarketplaceContext = () => {
  const context = useContext(MarketplaceContext)

  if (!context) {
    throw new Error('useMarketplaceContext must be used within MarketplaceProvider')
  }

  return context
}
