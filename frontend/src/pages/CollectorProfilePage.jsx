import {
  ChevronLeft,
  ChevronRight,
  ExternalLink,
  Heart,
  MapPin,
  Star,
  UserCheck,
  UserPlus,
  X,
} from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { createPortal } from 'react-dom'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import Badge from '@/components/ui/Badge'
import ProBadge from '@/components/ui/ProBadge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import EmptyState from '@/components/ui/EmptyState'
import UserAvatar from '@/components/people/UserAvatar'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { cardoraService } from '@/services/cardoraService'
import { formatCurrency, formatNumber } from '@/utils/formatters'
import { getUserDisplayName } from '@/utils/helpers'

const getMediaUrl = (mediaItem) => {
  if (!mediaItem) return null
  if (typeof mediaItem === 'string') return mediaItem
  if (typeof mediaItem === 'object') return mediaItem.url ?? mediaItem.preview_url ?? mediaItem.path ?? null
  return null
}

const getEntryMediaUrls = (entry) => {
  const ownMedia = Array.isArray(entry?.media) ? entry.media : []
  const ownUrls = ownMedia.map((item) => getMediaUrl(item)).filter(Boolean)
  if (ownUrls.length) return [...new Set(ownUrls)]

  const linkedMedia = Array.isArray(entry?.product?.media) ? entry.product.media : []
  return [...new Set(linkedMedia.map((item) => getMediaUrl(item)).filter(Boolean))]
}

const normalizePublicCollectionEntry = (entry) => {
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
    product: entry.product ?? null,
  }
}

const mapPublicProfileToMarketplaceProfile = (payload, fallbackProfile) => {
  if (!payload) return fallbackProfile

  const collectionEntries = Array.isArray(payload.collection_entries)
    ? payload.collection_entries.map(normalizePublicCollectionEntry).filter(Boolean)
    : []
  const activeListings = Array.isArray(payload.active_listings) ? payload.active_listings : []
  const user = {
    ...(fallbackProfile?.user ?? {}),
    id: Number(payload.id ?? fallbackProfile?.userId ?? 0),
    name: payload.name ?? fallbackProfile?.user?.name ?? '',
    displayName:
      payload.display_name ??
      fallbackProfile?.user?.displayName ??
      fallbackProfile?.nickname ??
      payload.handle ??
      '',
    handle: payload.handle ?? fallbackProfile?.handle ?? '',
    city: payload.city ?? fallbackProfile?.user?.city ?? '',
    bio: payload.bio ?? fallbackProfile?.intro ?? '',
    collectorTagline:
      payload.collector_tagline ??
      fallbackProfile?.user?.collectorTagline ??
      fallbackProfile?.headline ??
      '',
    avatarUrl: payload.avatar_url ?? fallbackProfile?.user?.avatarUrl ?? '',
    rating: Number(payload.rating ?? fallbackProfile?.user?.rating ?? 0),
    salesCount: Number(payload.sales_count ?? fallbackProfile?.user?.salesCount ?? 0),
    purchaseCount: Number(payload.purchase_count ?? fallbackProfile?.user?.purchaseCount ?? 0),
    verified: Boolean(payload.is_verified_seller ?? fallbackProfile?.user?.verified),
    isPro: Boolean(payload.isPro ?? payload.is_pro ?? fallbackProfile?.user?.isPro),
  }

  return {
    ...fallbackProfile,
    userId: Number(payload.id ?? fallbackProfile?.userId ?? 0),
    handle: payload.handle ?? fallbackProfile?.handle ?? '',
    nickname:
      fallbackProfile?.nickname ??
      payload.display_name ??
      payload.handle ??
      '',
    headline:
      fallbackProfile?.headline ??
      payload.collector_tagline ??
      payload.bio ??
      '',
    intro: payload.bio ?? fallbackProfile?.intro ?? '',
    collectionEntries,
    collectionProducts: collectionEntries.map((entry) => entry.product).filter(Boolean),
    activeListings,
    likeCount: Number(payload.profile_likes_count ?? fallbackProfile?.likeCount ?? 0),
    followerCount: Number(payload.followers_count ?? fallbackProfile?.followerCount ?? 0),
    followingCount: Number(payload.following_count ?? fallbackProfile?.followingCount ?? 0),
    collectionCount: Number(payload.collection_entries_count ?? fallbackProfile?.collectionCount ?? 0),
    likedByCurrentUser: Boolean(payload.liked_by_auth_user ?? fallbackProfile?.likedByCurrentUser),
    followedByCurrentUser: Boolean(payload.followed_by_auth_user ?? fallbackProfile?.followedByCurrentUser),
    user,
  }
}

function CollectorProfilePage() {
  const { handle } = useParams()
  const [searchParams, setSearchParams] = useSearchParams()
  const { locale } = useI18n()
  const { currentUser, isAuthenticated } = useAuth()
  const { collectorProfilesDetailed, getCollectorProfileByHandle, toggleProfileLike, toggleProfileFollow } = useMarketplace()
  const [hydratedProfile, setHydratedProfile] = useState(null)
  const [actionNotice, setActionNotice] = useState(null)
  const [activeTab, setActiveTab] = useState('gallery')
  const [connectionsView, setConnectionsView] = useState(null)
  const [viewer, setViewer] = useState({
    isOpen: false,
    entry: null,
    mediaUrls: [],
    index: 0,
  })

  const summaryProfile = getCollectorProfileByHandle(handle)
  const profile = useMemo(() => {
    if (!hydratedProfile) return summaryProfile
    if (!summaryProfile) return hydratedProfile

    return {
      ...hydratedProfile,
      ...summaryProfile,
      intro: hydratedProfile.intro ?? summaryProfile.intro,
      collectionEntries:
        hydratedProfile.collectionEntries?.length
          ? hydratedProfile.collectionEntries
          : summaryProfile.collectionEntries ?? [],
      activeListings:
        hydratedProfile.activeListings?.length
          ? hydratedProfile.activeListings
          : summaryProfile.activeListings ?? [],
      collectionProducts:
        hydratedProfile.collectionProducts?.length
          ? hydratedProfile.collectionProducts
          : summaryProfile.collectionProducts ?? [],
      user: {
        ...(hydratedProfile.user ?? {}),
        ...(summaryProfile.user ?? {}),
      },
      likeCount: summaryProfile.likeCount ?? hydratedProfile.likeCount,
      followerCount: summaryProfile.followerCount ?? hydratedProfile.followerCount,
      followingCount: summaryProfile.followingCount ?? hydratedProfile.followingCount,
      likedByCurrentUser: summaryProfile.likedByCurrentUser ?? hydratedProfile.likedByCurrentUser,
      followedByCurrentUser:
        summaryProfile.followedByCurrentUser ?? hydratedProfile.followedByCurrentUser,
    }
  }, [hydratedProfile, summaryProfile])
  const collectionEntries = Array.isArray(profile?.collectionEntries) ? profile.collectionEntries : []
  const listingEntries = Array.isArray(profile?.activeListings) ? profile.activeListings : []

  const viewerActiveUrl = viewer.mediaUrls[viewer.index] ?? null
  const hasViewerThumbs = viewer.mediaUrls.length > 1
  const viewerImageMaxHeight = hasViewerThumbs ? 'calc(86vh - 230px)' : 'calc(86vh - 170px)'

  const copy =
    locale === 'en'
      ? {
          missingTitle: 'Collection not found',
          missingDescription: 'This public profile is not available right now.',
          edit: 'Edit',
          like: 'Like',
          liked: 'Liked',
          follow: 'Follow',
          following: 'Following',
          login: 'Sign in',
          pieces: 'pieces',
          followers: 'followers',
          followingLabel: 'following',
          likes: 'likes',
          sales: 'sales',
          galleryTitle: 'Collector profile',
          galleryDescription: 'Personal collection and active listings in one place.',
          tabGallery: 'Collection',
          tabListings: 'Listings',
          collectionTabDescription: 'Only personal collection items that the collector keeps.',
          listingsTabDescription: 'Items currently available for sale from this collector.',
          noImage: 'No image',
          featured: 'Featured',
          emptyTitle: 'No collection items yet',
          emptyDescription: 'The collector has not added any public collection items yet.',
          emptyListingsTitle: 'No active listings yet',
          emptyListingsDescription: 'This collector has no live listings at the moment.',
          openProduct: 'Open product',
          closeViewer: 'Close',
          linkedProduct: 'Open product page',
          likedNotice: 'You liked this collector profile.',
          unlikedNotice: 'You removed your like from this collector profile.',
          followedNotice: 'You are now following this collector.',
          unfollowedNotice: 'You stopped following this collector.',
          collectionItemFallback: 'Collection item',
          openFollowers: 'See followers',
          openFollowing: 'See following',
          followersTitle: 'Followers',
          followingTitle: 'Following',
          noFollowers: 'No followers yet.',
          noFollowing: 'This collector is not following anyone yet.',
          viewProfile: 'Open profile',
        }
      : {
          missingTitle: 'Η συλλογή δεν βρέθηκε',
          missingDescription: 'Το δημόσιο προφίλ δεν είναι διαθέσιμο αυτή τη στιγμή.',
          edit: 'Επεξεργασία',
          like: 'Μου αρέσει',
          liked: 'Σου αρέσει',
          follow: 'Ακολούθησε',
          following: 'Ακολουθείς',
          login: 'Σύνδεση',
          pieces: 'κομμάτια',
          followers: 'ακόλουθοι',
          followingLabel: 'ακολουθεί',
          likes: 'likes',
          sales: 'πωλήσεις',
          galleryTitle: 'Προφίλ συλλέκτη',
          galleryDescription: 'Προσωπική συλλογή και ενεργές αγγελίες σε μία σελίδα.',
          tabGallery: 'Συλλογή',
          tabListings: 'Αγγελίες',
          collectionTabDescription: 'Εδώ εμφανίζονται μόνο κομμάτια από την προσωπική συλλογή που κρατά ο χρήστης.',
          listingsTabDescription: 'Αντικείμενα που είναι τώρα διαθέσιμα προς πώληση.',
          noImage: 'Χωρίς εικόνα',
          featured: 'Featured',
          emptyTitle: 'Δεν υπάρχουν ακόμη κομμάτια συλλογής',
          emptyDescription: 'Ο συλλέκτης δεν έχει προσθέσει ακόμη δημόσια αντικείμενα στη συλλογή του.',
          emptyListingsTitle: 'Δεν υπάρχουν ενεργές αγγελίες',
          emptyListingsDescription: 'Ο συλλέκτης δεν έχει αυτή τη στιγμή διαθέσιμες αγγελίες.',
          openProduct: 'Άνοιγμα προϊόντος',
          closeViewer: 'Κλείσιμο',
          linkedProduct: 'Σελίδα προϊόντος',
          likedNotice: 'Έκανες like στο προφίλ του συλλέκτη.',
          unlikedNotice: 'Αφαίρεσες το like από το προφίλ του συλλέκτη.',
          followedNotice: 'Ακολουθείς πλέον αυτόν τον συλλέκτη.',
          unfollowedNotice: 'Σταμάτησες να ακολουθείς αυτόν τον συλλέκτη.',
          collectionItemFallback: 'Συλλεκτικό κομμάτι',
          openFollowers: 'Δες ακόλουθους',
          openFollowing: 'Δες ποιους ακολουθεί',
          followersTitle: 'Ακόλουθοι',
          followingTitle: 'Ακολουθεί',
          noFollowers: 'Δεν υπάρχουν ακόμη ακόλουθοι.',
          noFollowing: 'Αυτός ο συλλέκτης δεν ακολουθεί ακόμη κάποιον άλλο.',
          viewProfile: 'Άνοιγμα προφίλ',
        }

  const followerProfiles = useMemo(
    () =>
      (profile?.followedByUserIds ?? [])
        .map((userId) => collectorProfilesDetailed.find((item) => item.userId === userId))
        .filter(Boolean),
    [collectorProfilesDetailed, profile?.followedByUserIds],
  )

  const followingProfiles = useMemo(
    () =>
      collectorProfilesDetailed.filter((item) =>
        Array.isArray(item.followedByUserIds) ? item.followedByUserIds.includes(profile?.userId) : false,
      ),
    [collectorProfilesDetailed, profile?.userId],
  )

  const viewerTitle = useMemo(() => {
    if (!viewer.entry) return ''
    return viewer.entry.title || viewer.entry.product?.title || ''
  }, [viewer.entry])

  useEffect(() => {
    if ((!viewer.isOpen && !connectionsView) || typeof document === 'undefined') {
      return undefined
    }

    const { body, documentElement } = document
    const appRoot = document.getElementById('root')
    const previousOverflow = body.style.overflow
    const previousPaddingRight = body.style.paddingRight
    const previousHtmlOverflow = documentElement.style.overflow
    const previousRootOverflow = appRoot?.style.overflow ?? ''
    const previousRootHeight = appRoot?.style.height ?? ''
    const scrollbarWidth = window.innerWidth - documentElement.clientWidth

    documentElement.style.overflow = 'hidden'
    body.style.overflow = 'hidden'
    if (scrollbarWidth > 0) {
      body.style.paddingRight = `${scrollbarWidth}px`
    }
    if (appRoot) {
      appRoot.style.overflow = 'hidden'
      appRoot.style.height = '100vh'
    }

    return () => {
      body.style.overflow = previousOverflow
      body.style.paddingRight = previousPaddingRight
      documentElement.style.overflow = previousHtmlOverflow
      if (appRoot) {
        appRoot.style.overflow = previousRootOverflow
        appRoot.style.height = previousRootHeight
      }
    }
  }, [connectionsView, viewer.isOpen])

  useEffect(() => {
    const requestedView = searchParams.get('connections')
    if (requestedView === 'followers' || requestedView === 'following') {
      setConnectionsView(requestedView)
      return
    }

    setConnectionsView(null)
  }, [searchParams])

  useEffect(() => {
    let isActive = true

    if (!handle) {
      setHydratedProfile(null)
      return undefined
    }

    const hydrateProfile = async () => {
      try {
        const payload = await cardoraService.getPublicProfile(handle)
        if (!isActive) return
        setHydratedProfile(mapPublicProfileToMarketplaceProfile(payload, getCollectorProfileByHandle(handle)))
      } catch (error) {
        if (!isActive) return
        setHydratedProfile(getCollectorProfileByHandle(handle))
      }
    }

    hydrateProfile()

    return () => {
      isActive = false
    }
  }, [getCollectorProfileByHandle, handle])

  if (!profile) {
    return (
      <div className="container pb-16">
        <EmptyState title={copy.missingTitle} description={copy.missingDescription} />
      </div>
    )
  }

  const isOwnProfile = currentUser?.id === profile.userId

  const openViewer = (entry, startIndex = 0) => {
    const mediaUrls = getEntryMediaUrls(entry)
    if (!mediaUrls.length) return

    const normalizedIndex = startIndex >= 0 && startIndex < mediaUrls.length ? startIndex : 0

    setViewer({
      isOpen: true,
      entry,
      mediaUrls,
      index: normalizedIndex,
    })
  }

  const closeViewer = () => {
    setViewer({
      isOpen: false,
      entry: null,
      mediaUrls: [],
      index: 0,
    })
  }

  const showNextImage = () => {
    setViewer((previous) => {
      if (!previous.mediaUrls.length) return previous
      return {
        ...previous,
        index: (previous.index + 1) % previous.mediaUrls.length,
      }
    })
  }

  const showPreviousImage = () => {
    setViewer((previous) => {
      if (!previous.mediaUrls.length) return previous
      return {
        ...previous,
        index: (previous.index - 1 + previous.mediaUrls.length) % previous.mediaUrls.length,
      }
    })
  }

  const handleLike = async () => {
    const result = await toggleProfileLike(profile.userId)
    if (!result) return

    setActionNotice({
      tone: result.success ? 'success' : 'warning',
      text:
        result.message ??
        (result.success ? (result.liked ? copy.likedNotice : copy.unlikedNotice) : copy.login),
    })
  }

  const handleFollow = async () => {
    const result = await toggleProfileFollow(profile.userId)
    if (!result) return

    setActionNotice({
      tone: result.success ? 'success' : 'warning',
      text:
        result.message ??
        (result.success ? (result.following ? copy.followedNotice : copy.unfollowedNotice) : copy.login),
    })
  }

  const openConnections = (view) => {
    const nextParams = new URLSearchParams(searchParams)
    nextParams.set('connections', view)
    setSearchParams(nextParams, { replace: true })
  }

  const closeConnections = () => {
    const nextParams = new URLSearchParams(searchParams)
    nextParams.delete('connections')
    setSearchParams(nextParams, { replace: true })
  }

  const connectionsProfiles = connectionsView === 'following' ? followingProfiles : followerProfiles

  return (
    <div className="container pb-16">
      <div className="mx-auto max-w-[1120px] space-y-8">
        <CardSurface hover={false} className="overflow-hidden p-0">
          <div
            className="h-24 w-full"
            style={{
              background: `linear-gradient(135deg, ${profile.coverPalette.from}, ${profile.coverPalette.via}, ${profile.coverPalette.to})`,
            }}
          />

          <div className="px-5 pb-5 pt-0 sm:px-6 sm:pb-6">
            <div className="-mt-8 flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
              <div className="flex items-start gap-4">
                <UserAvatar
                  user={profile.user}
                  size="md"
                  className="h-16 w-16 shrink-0 text-lg"
                />

                <div className="pt-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <h1 className="text-2xl font-semibold text-white sm:text-[1.8rem]">
                      {getUserDisplayName(profile.user)}
                    </h1>
                    {profile.user.verified ? <Badge tone="success">Verified</Badge> : null}
                    {profile.user.isPro ? <ProBadge /> : null}
                  </div>
                  <p className="mt-1 text-sm text-mist">@{profile.handle}</p>
                  <p className="mt-3 max-w-2xl text-sm leading-7 text-white/78">{profile.intro}</p>
                </div>
              </div>

              <div className="flex flex-wrap gap-2">
                {isOwnProfile ? (
                  <Button as={Link} to="/profil" variant="secondary" size="sm">
                    {copy.edit}
                  </Button>
                ) : isAuthenticated ? (
                  <>
                    <Button
                      variant={profile.followedByCurrentUser ? 'subtle' : 'primary'}
                      size="sm"
                      onClick={handleFollow}
                    >
                      {profile.followedByCurrentUser ? <UserCheck className="h-4 w-4" /> : <UserPlus className="h-4 w-4" />}
                      {profile.followedByCurrentUser ? copy.following : copy.follow}
                    </Button>
                    <Button
                      variant={profile.likedByCurrentUser ? 'subtle' : 'secondary'}
                      size="sm"
                      onClick={handleLike}
                    >
                      <Heart className={`h-4 w-4 ${profile.likedByCurrentUser ? 'fill-current' : ''}`} />
                      {profile.likedByCurrentUser ? copy.liked : copy.like}
                    </Button>
                  </>
                ) : (
                  <Button as={Link} to="/eisodos" variant="secondary" size="sm">
                    {copy.login}
                  </Button>
                )}
              </div>
            </div>

            <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
              <div className="rounded-2xl border border-[#eadab7] bg-white px-4 py-3">
                <p className="text-[11px] uppercase tracking-[0.24em] text-[#8d7a58]">{copy.likes}</p>
                <p className="mt-2 text-lg font-semibold text-ink">{formatNumber(profile.likeCount)}</p>
              </div>
              <button
                type="button"
                onClick={() => openConnections('followers')}
                className="rounded-2xl border border-[#eadab7] bg-white px-4 py-3 text-left transition hover:border-[#d8b06a] hover:bg-[#fff8ea]"
              >
                <p className="text-[11px] uppercase tracking-[0.24em] text-[#8d7a58]">{copy.followers}</p>
                <p className="mt-2 text-lg font-semibold text-ink">{formatNumber(profile.followerCount ?? 0)}</p>
              </button>
              <button
                type="button"
                onClick={() => openConnections('following')}
                className="rounded-2xl border border-[#eadab7] bg-white px-4 py-3 text-left transition hover:border-[#d8b06a] hover:bg-[#fff8ea]"
              >
                <p className="text-[11px] uppercase tracking-[0.24em] text-[#8d7a58]">{copy.followingLabel}</p>
                <p className="mt-2 text-lg font-semibold text-ink">{formatNumber(profile.followingCount ?? 0)}</p>
              </button>
              <div className="rounded-2xl border border-[#eadab7] bg-white px-4 py-3">
                <p className="text-[11px] uppercase tracking-[0.24em] text-[#8d7a58]">{copy.sales}</p>
                <p className="mt-2 text-lg font-semibold text-ink">{formatNumber(profile.user.salesCount ?? 0)}</p>
              </div>
              <div className="rounded-2xl border border-[#eadab7] bg-white px-4 py-3">
                <p className="text-[11px] uppercase tracking-[0.24em] text-[#8d7a58]">Rating</p>
                <div className="mt-2 flex items-center gap-2">
                  <Star className="h-4 w-4 fill-current text-gold-200" />
                  <span className="text-lg font-semibold text-ink">{profile.user.rating}</span>
                </div>
              </div>
            </div>

            <div className="mt-3 flex items-center gap-1.5 text-mist">
              <MapPin className="h-4 w-4 text-gold-100" />
              {profile.user.city}
            </div>

            {profile.badges.length ? (
              <div className="mt-4 flex flex-wrap gap-2">
                {profile.badges.map((badge) => (
                  <Badge key={badge} tone="gold">
                    {badge}
                  </Badge>
                ))}
              </div>
            ) : null}

            {actionNotice ? (
              <div
                className={`mt-4 rounded-xl border px-4 py-3 text-sm ${
                  actionNotice.tone === 'success'
                    ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-800'
                    : 'border-amber-400/20 bg-amber-500/10 text-amber-800'
                }`}
              >
                {actionNotice.text}
              </div>
            ) : null}
          </div>
        </CardSurface>

        <section>
          <div className="mb-4 flex items-end justify-between gap-3">
            <div>
              <h2 className="font-display text-3xl text-white">{copy.galleryTitle}</h2>
              <p className="mt-2 text-sm text-mist">{copy.galleryDescription}</p>
            </div>
          </div>

          <div className="mb-5 inline-flex rounded-xl border border-white/12 bg-white/5 p-1">
            <button
              type="button"
              onClick={() => setActiveTab('gallery')}
              className={`rounded-lg px-4 py-2 text-sm transition ${
                activeTab === 'gallery' ? 'bg-gold-300 text-slate-950' : 'text-white/80 hover:text-white'
              }`}
            >
              {copy.tabGallery}
            </button>
            <button
              type="button"
              onClick={() => setActiveTab('listings')}
              className={`rounded-lg px-4 py-2 text-sm transition ${
                activeTab === 'listings' ? 'bg-gold-300 text-slate-950' : 'text-white/80 hover:text-white'
              }`}
            >
              {copy.tabListings}
            </button>
          </div>

          {activeTab === 'gallery' ? (
            <>
              <p className="mb-4 text-sm text-mist">{copy.collectionTabDescription}</p>
              {collectionEntries.length ? (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                  {collectionEntries.map((entry) => {
                    const linkedProduct = entry.product
                    const mediaUrls = getEntryMediaUrls(entry)
                    const primaryMedia = mediaUrls[0] ?? null
                    const title = entry.title || linkedProduct?.title || copy.collectionItemFallback
                    const subtitle =
                      entry.caption || [linkedProduct?.franchise, linkedProduct?.condition].filter(Boolean).join(' • ')

                    return (
                      <CardSurface key={entry.id} className="overflow-hidden p-2.5">
                        <button type="button" className="group w-full text-left" onClick={() => openViewer(entry)}>
                          <div className="relative overflow-hidden rounded-[20px] border border-white/10 bg-[#081324] p-2">
                            {primaryMedia ? (
                              <img
                                src={primaryMedia}
                                alt={title}
                                className="h-[220px] w-full rounded-[16px] bg-[#050d1a] object-contain transition duration-300 group-hover:scale-[1.01]"
                                loading="lazy"
                              />
                            ) : (
                              <div className="flex h-[220px] w-full items-center justify-center bg-white/5 text-sm text-mist">
                                {copy.noImage}
                              </div>
                            )}
                            {entry.isFeatured ? (
                              <span className="absolute left-2 top-2 rounded-full border border-gold-300/30 bg-gold-300/20 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-gold-100">
                                {copy.featured}
                              </span>
                            ) : null}
                          </div>
                        </button>
                        <div className="px-1 pb-1 pt-3">
                          <p className="line-clamp-2 text-sm font-semibold text-white">{title}</p>
                          {subtitle ? <p className="mt-1 line-clamp-2 text-xs text-mist">{subtitle}</p> : null}
                          {linkedProduct ? (
                            <p className="mt-2 text-sm font-semibold text-gold-100">{formatCurrency(linkedProduct.price)}</p>
                          ) : null}
                          {linkedProduct?.slug ? (
                            <Link
                              to={`/proion/${linkedProduct.slug}`}
                              className="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-gold-100 hover:text-gold-50"
                            >
                              {copy.openProduct}
                              <ExternalLink className="h-3.5 w-3.5" />
                            </Link>
                          ) : null}
                        </div>
                      </CardSurface>
                    )
                  })}
                </div>
              ) : (
                <CardSurface hover={false}>
                  <EmptyState title={copy.emptyTitle} description={copy.emptyDescription} />
                </CardSurface>
              )}
            </>
          ) : (
            <>
              <p className="mb-4 text-sm text-mist">{copy.listingsTabDescription}</p>
              {listingEntries.length ? (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                  {listingEntries.map((listing) => {
                    const primaryMedia = Array.isArray(listing.media) ? getMediaUrl(listing.media[0]) : null

                    return (
                      <Link key={listing.id} to={`/proion/${listing.slug}`} className="group block">
                        <CardSurface className="overflow-hidden p-3">
                          <div className="overflow-hidden rounded-[18px] border border-white/10 bg-[#081324] p-2">
                            {primaryMedia ? (
                              <img
                                src={primaryMedia}
                                alt={listing.title}
                                className="h-[200px] w-full rounded-[14px] bg-[#050d1a] object-contain transition duration-300 group-hover:scale-[1.01]"
                                loading="lazy"
                              />
                            ) : (
                              <div className="flex h-[200px] w-full items-center justify-center text-sm text-mist">
                                {copy.noImage}
                              </div>
                            )}
                          </div>

                          <div className="mt-3 space-y-2">
                            <div className="flex flex-wrap gap-2">
                              {listing.rarity ? <Badge tone="gold">{listing.rarity}</Badge> : null}
                              {listing.condition ? <Badge tone="muted">{listing.condition}</Badge> : null}
                            </div>
                            <p className="line-clamp-2 text-sm font-semibold text-white transition group-hover:text-gold-100">
                              {listing.title}
                            </p>
                            <p className="line-clamp-1 text-xs text-mist">
                              {[listing.franchise, listing.series].filter(Boolean).join(' • ')}
                            </p>
                            <p className="text-base font-semibold text-gold-100">{formatCurrency(listing.price)}</p>
                          </div>
                        </CardSurface>
                      </Link>
                    )
                  })}
                </div>
              ) : (
                <CardSurface hover={false}>
                  <EmptyState title={copy.emptyListingsTitle} description={copy.emptyListingsDescription} />
                </CardSurface>
              )}
            </>
          )}
        </section>
      </div>

      {viewer.isOpen && typeof document !== 'undefined'
        ? createPortal(
            <div
              style={{
                position: 'fixed',
                inset: 0,
                zIndex: 2147483647,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                overflowY: 'auto',
                background: 'rgba(0, 0, 0, 0.62)',
                padding: '12px',
              }}
              onClick={closeViewer}
            >
              <div
                className="relative overflow-hidden rounded-[18px] border border-white/15 bg-[#081324] p-2.5 shadow-[0_22px_70px_rgba(0,0,0,0.55)] sm:p-3.5"
                style={{
                  width: 'min(92vw, 720px)',
                  maxHeight: '86vh',
                }}
                onClick={(event) => event.stopPropagation()}
              >
                <div className="flex items-center justify-between gap-3">
                  <div className="min-w-0">
                    <p className="truncate text-sm font-semibold text-white">{viewerTitle}</p>
                    <p className="text-xs text-mist">
                      {viewer.index + 1} / {viewer.mediaUrls.length}
                    </p>
                  </div>
                  <button
                    type="button"
                    onClick={closeViewer}
                    className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-white/15 bg-white/6 text-white/85 transition hover:border-gold-300/45 hover:text-gold-100"
                    aria-label={copy.closeViewer}
                  >
                    <X className="h-4 w-4" />
                  </button>
                </div>

                <div
                  className="mt-2.5"
                  style={{
                    display: 'flex',
                    flexDirection: 'column',
                    minHeight: 0,
                  }}
                >
                  <div className="relative overflow-hidden rounded-[16px] border border-white/12 bg-[#050d1a] p-2.5">
                    {viewerActiveUrl ? (
                      <img
                        src={viewerActiveUrl}
                        alt={viewerTitle || 'collection-image'}
                        className="mx-auto w-auto max-w-full object-contain"
                        style={{
                          maxHeight: viewerImageMaxHeight,
                        }}
                      />
                    ) : (
                      <div
                        className="flex w-full items-center justify-center text-sm text-mist"
                        style={{
                          height: viewerImageMaxHeight,
                        }}
                      >
                        {copy.noImage}
                      </div>
                    )}

                    {viewer.mediaUrls.length > 1 ? (
                      <>
                        <button
                          type="button"
                          onClick={showPreviousImage}
                          className="absolute left-3 top-1/2 z-10 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg border border-white/15 bg-[#0b1930]/92 text-white/90 transition hover:border-gold-300/40 hover:text-gold-100"
                          style={{
                            left: 12,
                            top: '50%',
                            transform: 'translateY(-50%)',
                            width: 34,
                            height: 34,
                            zIndex: 10,
                          }}
                        >
                          <ChevronLeft className="h-4 w-4" />
                        </button>
                        <button
                          type="button"
                          onClick={showNextImage}
                          className="absolute right-3 top-1/2 z-10 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg border border-white/15 bg-[#0b1930]/92 text-white/90 transition hover:border-gold-300/40 hover:text-gold-100"
                          style={{
                            right: 12,
                            top: '50%',
                            transform: 'translateY(-50%)',
                            width: 34,
                            height: 34,
                            zIndex: 10,
                          }}
                        >
                          <ChevronRight className="h-4 w-4" />
                        </button>
                      </>
                    ) : null}
                  </div>

                  {viewer.mediaUrls.length > 1 ? (
                    <div
                      className="mt-2 flex gap-1.5 overflow-x-auto rounded-[14px] border border-white/10 bg-white/5 p-1.5"
                      style={{
                        flexShrink: 0,
                      }}
                    >
                      {viewer.mediaUrls.map((url, index) => (
                        <button
                          key={`${url}-${index}`}
                          type="button"
                          onClick={() =>
                            setViewer((previous) => ({
                              ...previous,
                              index,
                            }))
                          }
                          className={`overflow-hidden rounded-lg border p-1 transition ${
                            index === viewer.index
                              ? 'border-gold-300/50 bg-gold-300/10'
                              : 'border-white/15 bg-white/5 hover:border-gold-300/35'
                          }`}
                        >
                          <img
                            src={url}
                            alt={`thumbnail-${index + 1}`}
                            className="h-12 w-14 rounded-md bg-[#050d1a] object-contain"
                          />
                        </button>
                      ))}
                    </div>
                  ) : null}
                </div>

                {viewer.entry?.product?.slug ? (
                  <div className="mt-4 flex justify-end">
                    <Link
                      to={`/proion/${viewer.entry.product.slug}`}
                      className="inline-flex items-center gap-1 rounded-lg border border-gold-300/35 bg-gold-300/12 px-3 py-2 text-xs font-semibold text-gold-100 transition hover:border-gold-300/55 hover:bg-gold-300/20"
                      onClick={closeViewer}
                    >
                      {copy.linkedProduct}
                      <ExternalLink className="h-3.5 w-3.5" />
                    </Link>
                  </div>
                ) : null}
              </div>
            </div>,
            document.body,
          )
        : null}

      {connectionsView && typeof document !== 'undefined'
        ? createPortal(
            <div
              style={{
                position: 'fixed',
                inset: 0,
                zIndex: 2147483646,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                overflowY: 'auto',
                background: 'rgba(0, 0, 0, 0.62)',
                padding: '12px',
              }}
              onClick={closeConnections}
            >
              <div
                className="w-full max-w-[760px] rounded-[24px] border border-white/12 bg-[#081324] p-5 shadow-[0_24px_80px_rgba(0,0,0,0.55)]"
                onClick={(event) => event.stopPropagation()}
              >
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <p className="text-xs uppercase tracking-[0.28em] text-gold-100">
                      @{profile.handle}
                    </p>
                    <h3 className="mt-2 font-display text-3xl text-white">
                      {connectionsView === 'following' ? copy.followingTitle : copy.followersTitle}
                    </h3>
                  </div>
                  <button
                    type="button"
                    onClick={closeConnections}
                    className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/12 bg-white/6 text-white/80 transition hover:border-gold-300/45 hover:text-gold-100"
                    aria-label={copy.closeViewer}
                  >
                    <X className="h-4 w-4" />
                  </button>
                </div>

                <div className="mt-5 grid gap-3 sm:grid-cols-2">
                  {connectionsProfiles.length ? (
                    connectionsProfiles.map((item) => (
                      <CardSurface key={item.userId} className="p-4">
                        <div className="flex items-start gap-3">
                          <UserAvatar
                            user={item.user}
                            size="sm"
                            className="h-11 w-11 shrink-0 text-sm"
                            ringClassName="border-transparent"
                          />
                          <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-semibold text-white">{getUserDisplayName(item.user)}</p>
                            <p className="truncate text-xs text-mist">@{item.handle}</p>
                            {item.headline ? (
                              <p className="mt-2 line-clamp-2 text-xs leading-6 text-white/72">{item.headline}</p>
                            ) : null}
                            <div className="mt-3">
                              <Button as={Link} to={`/sylloges/${item.handle}`} variant="secondary" size="sm" onClick={closeConnections}>
                                {copy.viewProfile}
                              </Button>
                            </div>
                          </div>
                        </div>
                      </CardSurface>
                    ))
                  ) : (
                    <CardSurface hover={false} className="sm:col-span-2">
                      <EmptyState
                        title={connectionsView === 'following' ? copy.followingTitle : copy.followersTitle}
                        description={connectionsView === 'following' ? copy.noFollowing : copy.noFollowers}
                      />
                    </CardSurface>
                  )}
                </div>
              </div>
            </div>,
            document.body,
          )
        : null}
    </div>
  )
}

export default CollectorProfilePage
