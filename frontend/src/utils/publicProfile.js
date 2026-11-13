// Only fields rendered on the public profile may enter the HTML snapshot.
// Do not spread API resources: nested products can contain seller/account data.
const text = value => typeof value === 'string' ? value : ''
const count = value => Math.max(0, Number(value) || 0)
const media = value => (Array.isArray(value) ? value : []).map(item =>
  text(typeof item === 'string' ? item : item?.url ?? item?.preview_url ?? item?.path)).filter(Boolean)
const product = value => value ? {
  id: count(value.id), slug: text(value.slug), title: text(value.title),
  franchise: text(value.franchise), series: text(value.series), condition: text(value.condition), media: media(value.media),
} : null

export function sanitizePublicProfile(value) {
  if (!value || value.profile_visibility === 'private') return null
  if (!value.handle || !value.id) throw new Error('Invalid public profile response')
  return {
    id: count(value.id), handle: text(value.handle), display_name: text(value.display_name) || text(value.handle),
    city: text(value.city).includes('@') ? '' : text(value.city), bio: text(value.bio),
    collector_tagline: text(value.collector_tagline), avatar_url: text(value.avatar_url),
    rating: count(value.rating), sales_count: count(value.sales_count),
    is_verified_seller: Boolean(value.is_verified_seller), isPro: Boolean(value.isPro ?? value.is_pro),
    profile_likes_count: count(value.profile_likes_count), followers_count: count(value.followers_count),
    following_count: count(value.following_count), collection_entries_count: count(value.collection_entries_count),
    // Anonymous snapshots cannot carry viewer-specific relationships.
    collection_entries: (value.collection_entries || []).filter(entry => !entry.visibility || entry.visibility === 'public').map(entry => ({
      id: count(entry.id), title: text(entry.title), caption: text(entry.caption), media: media(entry.media),
      is_featured: Boolean(entry.is_featured), product: product(entry.product),
    })),
    active_listings: (value.active_listings || []).map(listing => ({
      id: count(listing.id), title_snapshot: text(listing.title_snapshot), price: Number(listing.price) || 0,
      rarity: text(listing.rarity), condition: text(listing.condition), product: product(listing.product),
    })),
  }
}

export function mapPublicProfile(payload) {
  const value = sanitizePublicProfile(payload)
  if (!value) return null
  return {
    userId: Number(value.id), handle: value.handle, nickname: value.display_name || value.handle,
    intro: value.bio || '', badges: [], coverPalette: { from: '#f2cb70', via: '#162238', to: '#09111d' },
    collectionEntries: (value.collection_entries || []).map(entry => ({ ...entry, isFeatured: Boolean(entry.is_featured) })),
    activeListings: value.active_listings || [],
    likeCount: count(value.profile_likes_count), followerCount: count(value.followers_count), followingCount: count(value.following_count),
    likedByCurrentUser: Boolean(payload.liked_by_auth_user), followedByCurrentUser: Boolean(payload.followed_by_auth_user),
    user: { id: Number(value.id), handle: value.handle, name: value.display_name || value.handle,
      displayName: value.display_name || value.handle, city: value.city || '', bio: value.bio || '',
      avatarUrl: value.avatar_url || '', rating: count(value.rating), salesCount: count(value.sales_count),
      verified: Boolean(value.is_verified_seller), isPro: Boolean(value.isPro ?? value.is_pro) },
  }
}
