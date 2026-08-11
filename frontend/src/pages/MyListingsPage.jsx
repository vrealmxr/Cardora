import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import ListingCard from '@/components/listings/ListingCard'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input } from '@/components/ui/Input'
import SectionHeader from '@/components/ui/SectionHeader'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { cardoraService } from '@/services/cardoraService'
import { formatCurrency, formatShortDateTime } from '@/utils/formatters'

const STATUS_FILTERS = [
  { key: 'all', label: 'ALL' },
  { key: 'published', label: 'PUBLISHED' },
  { key: 'sold', label: 'SOLD' },
  { key: 'draft', label: 'DRAFT' },
  { key: 'pending_review', label: 'PENDING' },
]

const CATEGORY_FILTERS = [
  { key: 'all', labelEl: 'Όλες', labelEn: 'All' },
  { key: 'cards', labelEl: 'Κάρτες', labelEn: 'Cards' },
  { key: 'figures', labelEl: 'Φιγούρες', labelEn: 'Figures' },
  { key: 'comics', labelEl: 'Κόμικς/Βιβλία', labelEn: 'Comics/Books' },
  { key: 'misc', labelEl: 'Διάφορα', labelEn: 'Misc' },
]

const normalizeStatus = (status) => {
  const normalized = String(status || '').trim().toLowerCase()

  if (normalized === 'active') return 'published'
  if (normalized === 'pending') return 'pending_review'

  return normalized
}

const normalizeCategory = (listing) => {
  const raw =
    listing.categoryId ??
    listing.category_id ??
    listing.category?.slug ??
    listing.category?.key ??
    listing.category?.name ??
    ''

  const normalized = String(raw || '').trim().toLowerCase()

  if (normalized.includes('card')) return 'cards'
  if (normalized.includes('figure') || normalized.includes('statue')) return 'figures'
  if (normalized.includes('comic') || normalized.includes('book')) return 'comics'
  if (normalized.includes('misc') || normalized.includes('other') || normalized.includes('διαφορα')) return 'misc'

  return normalized
}

const resolveListingAmount = (listing) => {
  const saleFormat = String(listing.saleFormat ?? listing.sale_format ?? '').toLowerCase()
  if (saleFormat === 'auction') {
    return Number(
      listing.currentBid ??
        listing.current_bid ??
        listing.startingBid ??
        listing.starting_bid ??
        listing.price ??
        0,
    )
  }

  return Number(listing.price ?? 0)
}

const resolveListingTurnoverUnits = (listing) => {
  const status = normalizeStatus(listing.status)

  if (status === 'sold') {
    return Math.max(
      Number(
        listing.quantity ??
          listing.totalQuantity ??
          listing.total_quantity ??
          listing.originalQuantity ??
          listing.original_quantity ??
          listing.stock ??
          1,
      ) || 1,
      1,
    )
  }

  return Math.max(
    Number(
      listing.availableQuantity ??
        listing.available_quantity ??
        listing.stock ??
        listing.quantity ??
        1,
    ) || 1,
    1,
  )
}

const normalizeSearchText = (value) =>
  String(value ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()

function MyListingsPage() {
  const { locale } = useI18n()
  const { deleteListing, myListings, updateListingStatus, refreshBootstrap } = useMarketplace()
  const [searchParams] = useSearchParams()
  const [activeFilter, setActiveFilter] = useState('all')
  const [activeCategory, setActiveCategory] = useState('all')
  const [searchQuery, setSearchQuery] = useState('')
  const [featuredListingId, setFeaturedListingId] = useState(null)
  const [featuredSuccessState, setFeaturedSuccessState] = useState(null)
  const featuredConfirmationHandledRef = useRef(false)

  const isEnglish = locale === 'en'
  const featuredStorageKey = 'cardora_featured_listing_id'

  const copy = isEnglish
    ? {
        eyebrow: 'My listings',
        title: 'Manage every listing from one place',
        description:
          'Filter by status, category or search, then track what is live, what sold and what is still in review.',
        empty: 'There are no listings in this filter yet.',
        cta: 'Create listing',
        search: 'Search listings',
        stats: {
          live: 'Live listings',
          sold: 'Sold listings',
          activeRevenue: 'Live turnover',
          soldRevenue: 'Sold turnover',
        },
        featured: {
          title: 'Featured placement',
          price: 'Feature for €2',
          active: 'Featured active',
          activeUntil: 'Active until',
          processing: 'Processing',
          successTitle: 'Featured promotion is now live',
          successBody:
            'Your listing now appears with priority and RGB featured glow across the marketplace for 5 days.',
          cancelled: 'Featured checkout was cancelled.',
          failed: 'Featured payment was completed, but activation failed. Please contact support.',
        },
      }
    : {
        eyebrow: 'Οι αγγελίες μου',
        title: 'Οργάνωσε όλες τις αγγελίες σου σε ένα σημείο',
        description:
          'Φίλτραρε ανά status, κατηγορία ή αναζήτηση και δες τι είναι live, τι πουλήθηκε και τι περιμένει έλεγχο.',
        empty: 'Δεν υπάρχουν αγγελίες σε αυτό το φίλτρο.',
        cta: 'Νέα αγγελία',
        search: 'Αναζήτηση αγγελιών',
        stats: {
          live: 'Αγγελίες online',
          sold: 'Αγγελίες πουλήθηκαν',
          activeRevenue: 'Τζίρος ενεργών',
          soldRevenue: 'Τζίρος sold',
        },
        featured: {
          title: 'Προβεβλημένη προβολή',
          price: 'Προβολή με 2€',
          active: 'Προβολή ενεργή',
          activeUntil: 'Ισχύει έως',
          processing: 'Επεξεργασία',
          successTitle: 'Η προβεβλημένη προώθηση ενεργοποιήθηκε',
          successBody:
            'Η αγγελία σου προβάλλεται πλέον με προτεραιότητα και RGB featured glow σε όλο το marketplace για 5 ημέρες.',
          cancelled: 'Η πληρωμή για featured προβολή ακυρώθηκε.',
          failed: 'Η πληρωμή έγινε, αλλά η ενεργοποίηση απέτυχε. Επικοινώνησε με την υποστήριξη.',
        },
      }

  useEffect(() => {
    const featuredStatus = searchParams.get('featured')
    const sessionId = searchParams.get('session_id')

    if (featuredStatus !== 'success' || !sessionId) {
      if (featuredStatus === 'cancelled') {
        window.history.replaceState({}, '', '/oi-aggelies-mou')
        sessionStorage.removeItem(featuredStorageKey)
        featuredConfirmationHandledRef.current = false
        setFeaturedSuccessState({
          tone: 'cancelled',
          title: copy.featured.cancelled,
          body: null,
          listingId: null,
          featuredUntil: null,
        })
      }
      return
    }

    if (featuredConfirmationHandledRef.current) {
      return
    }

    const listingId = sessionStorage.getItem(featuredStorageKey) ?? searchParams.get('listing_id')

    featuredConfirmationHandledRef.current = true
    window.history.replaceState({}, '', '/oi-aggelies-mou')

    let isActive = true
    setFeaturedListingId(listingId ? Number(listingId) : null)

    cardoraService
      .confirmFeaturedListingPayment(sessionId, listingId ? Number(listingId) : null)
      .then(async (payload) => {
        if (!isActive || !payload?.featured_payment_id) return

        await refreshBootstrap()

        const resolvedListingId = Number(payload?.listing_id ?? listingId ?? 0) || null

        setFeaturedSuccessState({
          tone: 'success',
          title: copy.featured.successTitle,
          body: copy.featured.successBody,
          listingId: resolvedListingId,
          featuredUntil: payload?.featured_until ?? payload?.expires_at ?? null,
        })
      })
      .catch(() => {
        if (!isActive) return
        setFeaturedSuccessState({
          tone: 'cancelled',
          title: copy.featured.failed,
          body: null,
          listingId: null,
          featuredUntil: null,
        })
      })
      .finally(() => {
        if (!isActive) return
        sessionStorage.removeItem(featuredStorageKey)
        setFeaturedListingId(null)
      })

    return () => {
      isActive = false
    }
  }, [refreshBootstrap, searchParams])

  const handleFeaturedCheckout = async (listing) => {
    if (!listing?.databaseId) return

    setFeaturedSuccessState(null)
    setFeaturedListingId(listing.databaseId)
    sessionStorage.setItem(featuredStorageKey, String(listing.databaseId))

    try {
      const checkout = await cardoraService.startFeaturedListingCheckout({
        listing_id: Number(listing.databaseId),
      })
      if (checkout?.checkout_url) {
        window.location.href = checkout.checkout_url
        return
      }

      throw new Error('Featured checkout URL is missing.')
    } catch (error) {
      sessionStorage.removeItem(featuredStorageKey)
      setFeaturedListingId(null)
      throw error
    } finally {
      if (window.location.pathname === '/oi-aggelies-mou') {
        setFeaturedListingId(null)
      }
    }
  }

  const counts = useMemo(() => {
    const summary = {
      all: myListings.length,
      published: 0,
      sold: 0,
      draft: 0,
      pending_review: 0,
    }

    myListings.forEach((listing) => {
      const status = normalizeStatus(listing.status)
      if (status in summary) summary[status] += 1
    })

    return summary
  }, [myListings])

  const stats = useMemo(() => {
    let live = 0
    let sold = 0
    let activeRevenue = 0
    let soldRevenue = 0

    myListings.forEach((listing) => {
      const status = normalizeStatus(listing.status)
      const amount = resolveListingAmount(listing)
      const units = resolveListingTurnoverUnits(listing)
      const turnover = amount * units

      if (status === 'sold') {
        sold += 1
        soldRevenue += turnover
      } else if (status === 'published') {
        live += 1
        activeRevenue += turnover
      }
    })

    return {
      live,
      sold,
      activeRevenue,
      soldRevenue,
    }
  }, [myListings])

  const filteredListings = useMemo(() => {
    const query = normalizeSearchText(searchQuery)

    return myListings
      .filter((listing) => {
        const status = normalizeStatus(listing.status)
        const category = normalizeCategory(listing)
        const searchableText = normalizeSearchText(
          [
            listing.title,
            listing.titleSnapshot,
            listing.title_snapshot,
            listing.subtitle,
            listing.subtitleSnapshot,
            listing.subtitle_snapshot,
            listing.product?.title,
            listing.product?.subtitle,
            listing.product?.franchise,
            listing.franchise,
            listing.category?.name,
            listing.seller?.displayName,
          ]
            .filter(Boolean)
            .join(' '),
        )

        if (activeFilter !== 'all' && status !== activeFilter) return false
        if (activeCategory !== 'all' && category !== activeCategory) return false
        if (query && !searchableText.includes(query)) return false

        return true
      })
      .sort((left, right) => {
        if (Number(Boolean(right.featured)) !== Number(Boolean(left.featured))) {
          return Number(Boolean(right.featured)) - Number(Boolean(left.featured))
        }

        const leftFeaturedUntil = new Date(left.featuredUntil ?? 0).getTime()
        const rightFeaturedUntil = new Date(right.featuredUntil ?? 0).getTime()

        if (rightFeaturedUntil !== leftFeaturedUntil) {
          return rightFeaturedUntil - leftFeaturedUntil
        }

        return new Date(right.updatedAt ?? 0).getTime() - new Date(left.updatedAt ?? 0).getTime()
      })
  }, [activeCategory, activeFilter, myListings, searchQuery])

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />

      <div className="mb-6 grid gap-4 md:grid-cols-4">
        <CardSurface className="p-4">
          <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.stats.live}</p>
          <p className="mt-3 text-3xl font-semibold text-white">{stats.live}</p>
        </CardSurface>
        <CardSurface className="p-4">
          <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.stats.sold}</p>
          <p className="mt-3 text-3xl font-semibold text-white">{stats.sold}</p>
        </CardSurface>
        <CardSurface className="p-4">
          <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.stats.activeRevenue}</p>
          <p className="mt-3 text-3xl font-semibold text-white">{formatCurrency(stats.activeRevenue)}</p>
        </CardSurface>
        <CardSurface className="p-4">
          <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.stats.soldRevenue}</p>
          <p className="mt-3 text-3xl font-semibold text-white">{formatCurrency(stats.soldRevenue)}</p>
        </CardSurface>
      </div>

      {featuredSuccessState ? (
        <CardSurface
          className={[
            'mb-6 p-5',
            featuredSuccessState.tone === 'success'
              ? 'border-emerald-300/30 bg-emerald-400/10'
              : 'border-amber-300/30 bg-amber-400/10',
          ].join(' ')}
        >
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div className="space-y-2">
              <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.featured.title}</p>
              <h2 className="text-xl font-semibold text-white">{featuredSuccessState.title}</h2>
              {featuredSuccessState.body ? <p className="max-w-3xl text-sm leading-7 text-mist">{featuredSuccessState.body}</p> : null}
              {featuredSuccessState.featuredUntil ? (
                <p className="text-sm text-white/82">
                  {copy.featured.activeUntil} {formatShortDateTime(featuredSuccessState.featuredUntil)}
                </p>
              ) : null}
            </div>

            <Button type="button" variant="ghost" size="sm" onClick={() => setFeaturedSuccessState(null)}>
              OK
            </Button>
          </div>
        </CardSurface>
      ) : null}

      <div className="mb-5 flex flex-wrap items-center justify-between gap-4">
        <div className="flex flex-wrap gap-3">
          {STATUS_FILTERS.map((filter) => {
            const isActive = activeFilter === filter.key

            return (
              <button
                key={filter.key}
                type="button"
                onClick={() => setActiveFilter(filter.key)}
                className={[
                  'inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition',
                  isActive
                    ? 'border-gold-300/35 bg-gold-300/12 text-gold-100 shadow-gold-soft'
                    : 'border-white/10 bg-white/5 text-white/72 hover:border-white/20 hover:text-white',
                ].join(' ')}
              >
                <span>{filter.label}</span>
                <Badge tone={isActive ? 'gold' : 'muted'} className="px-2 py-0.5 text-[9px]">
                  {counts[filter.key]}
                </Badge>
              </button>
            )
          })}
        </div>

        <div className="w-full max-w-sm">
          <Input
            value={searchQuery}
            onChange={(event) => setSearchQuery(event.target.value)}
            placeholder={copy.search}
          />
        </div>
      </div>

      <div className="mb-6 flex flex-wrap gap-3">
        {CATEGORY_FILTERS.map((filter) => {
          const isActive = activeCategory === filter.key
          const label = isEnglish ? filter.labelEn : filter.labelEl

          return (
            <button
              key={filter.key}
              type="button"
              onClick={() => setActiveCategory(filter.key)}
              className={[
                'inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition',
                isActive
                  ? 'border-emerald-300/35 bg-emerald-300/12 text-emerald-800 shadow-emerald-soft'
                  : 'border-white/10 bg-white/5 text-white/72 hover:border-white/20 hover:text-white',
              ].join(' ')}
            >
              <span>{label}</span>
            </button>
          )
        })}
      </div>

      {filteredListings.length ? (
        <div className="space-y-5">
          {filteredListings.map((listing) => (
            <ListingCard
              key={listing.databaseId ?? listing.id}
              listing={listing}
              onMarkSold={(listingId) => updateListingStatus(listingId, 'sold')}
              onDelete={deleteListing}
              renderActions={(activeListing) => (
                <div className="flex flex-wrap items-center gap-3">
                  {activeListing.featured ? (
                    <Badge tone="warning">
                      {copy.featured.active}
                      {activeListing.featuredUntil
                        ? ` · ${copy.featured.activeUntil} ${formatShortDateTime(
                            activeListing.featuredUntil,
                          )}`
                        : ''}
                    </Badge>
                  ) : (
                    <Button
                      type="button"
                      variant="secondary"
                      size="sm"
                      onClick={() => handleFeaturedCheckout(activeListing)}
                      disabled={featuredListingId === activeListing.databaseId}
                    >
                      {featuredListingId === activeListing.databaseId
                        ? copy.featured.processing
                        : copy.featured.price}
                    </Button>
                  )}
                </div>
              )}
            />
          ))}
        </div>
      ) : (
        <CardSurface className="flex flex-col items-center justify-center gap-4 py-12 text-center">
          <Badge tone="muted">
            {STATUS_FILTERS.find((filter) => filter.key === activeFilter)?.label ?? 'ALL'}
          </Badge>
          <p className="max-w-xl text-sm leading-7 text-mist">{copy.empty}</p>
          <Button as={Link} to="/dimiourgia-aggelias" size="sm">
            {copy.cta}
          </Button>
        </CardSurface>
      )}
    </div>
  )
}

export default MyListingsPage

