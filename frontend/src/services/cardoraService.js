import { apiClient } from '@/services/apiClient'

const unwrapData = (payload) => payload?.data ?? payload ?? null

export const cardoraService = {
  getBootstrap: async () => unwrapData(await apiClient.get('/bootstrap')),

  register: async (payload) => {
    const response = await apiClient.post('/auth/register', payload)
    return {
      token: response?.token ?? '',
      user: unwrapData(response),
      message: response?.message ?? '',
      verificationEmailSent: response?.verification_email_sent !== false,
    }
  },

  login: async (payload) => {
    const response = await apiClient.post('/auth/login', payload)
    return {
      token: response?.token ?? '',
      user: unwrapData(response),
      message: response?.message ?? '',
    }
  },

  me: async () => unwrapData(await apiClient.get('/auth/me')),
  logout: async () => apiClient.post('/auth/logout', {}),
  resendVerificationEmail: async () =>
    unwrapData(await apiClient.post('/auth/email/verification-notification', {})),
  forgotPassword: async (payload) =>
    unwrapData(await apiClient.post('/auth/forgot-password', payload)),
  resetPassword: async (payload) =>
    unwrapData(await apiClient.post('/auth/reset-password', payload)),

  addToCart: async (itemOrListingId, quantity = 1) => {
    const payload =
      itemOrListingId && typeof itemOrListingId === 'object'
        ? itemOrListingId
        : { listing_id: itemOrListingId, quantity }

    return unwrapData(await apiClient.post('/cart', payload))
  },
  updateCartItem: async (cartItemId, quantity) =>
    unwrapData(await apiClient.put(`/cart/${cartItemId}`, { quantity })),
  removeCartItem: async (cartItemId) => apiClient.delete(`/cart/${cartItemId}`),

  addFavorite: async (listingId) =>
    unwrapData(await apiClient.post('/favorites', { listing_id: listingId })),
  getFavorites: async () => unwrapData(await apiClient.get('/favorites')),
  removeFavorite: async (favoriteId) => apiClient.delete(`/favorites/${favoriteId}`),

  createOrder: async (payload) => unwrapData(await apiClient.post('/orders', payload)),
  updateOrder: async (orderId, payload) => unwrapData(await apiClient.put(`/orders/${orderId}`, payload)),
  deleteOrder: async (orderId) => apiClient.delete(`/orders/${orderId}`),
  startCheckoutSession: async (payload) =>
    unwrapData(await apiClient.post('/checkout/session', payload)),
  startFeaturedListingCheckout: async (payload) =>
    unwrapData(await apiClient.post('/featured-listings/checkout', payload ?? {})),
  confirmFeaturedListingPayment: async (sessionId, listingId = null) =>
    unwrapData(
      await apiClient.post('/featured-listings/confirm', {
        session_id: sessionId,
        ...(listingId ? { listing_id: Number(listingId) } : {}),
      }),
    ),
  confirmOrderReceived: async (orderId) =>
    unwrapData(await apiClient.post(`/orders/${orderId}/confirm-received`, {})),
  getSellerConnectAccount: async () => unwrapData(await apiClient.get('/seller/connect/account')),
  startSellerOnboarding: async () =>
    unwrapData(await apiClient.post('/seller/connect/onboarding/start', {})),
  createSellerDashboardLoginLink: async () =>
    unwrapData(await apiClient.post('/seller/connect/dashboard-link', {})),
  getSellerBalanceSummary: async () =>
    unwrapData(await apiClient.get('/seller/balance/summary')),
  getSellerPayoutHistory: async () =>
    unwrapData(await apiClient.get('/seller/payouts/history')),

  createConversation: async (payload) =>
    unwrapData(await apiClient.post('/messages/conversations', payload)),
  createListingOffer: async (conversationId, payload) =>
    unwrapData(await apiClient.post(`/messages/conversations/${conversationId}/offers`, payload)),
  counterListingOffer: async (offerId, payload) =>
    unwrapData(await apiClient.post(`/listing-offers/${offerId}/counter`, payload)),
  acceptListingOffer: async (offerId, payload = {}) =>
    unwrapData(await apiClient.post(`/listing-offers/${offerId}/accept`, payload)),
  rejectListingOffer: async (offerId, payload = {}) =>
    unwrapData(await apiClient.post(`/listing-offers/${offerId}/reject`, payload)),
  markConversationRead: async (conversationId) =>
    unwrapData(await apiClient.post(`/messages/conversations/${conversationId}/read`, {})),
  sendMessage: async (payload) => unwrapData(await apiClient.post('/messages', payload)),

  getReviews: async (filters = {}) => {
    const query = new URLSearchParams()

    Object.entries(filters).forEach(([key, value]) => {
      if (value == null || value === '') return
      query.set(key, String(value))
    })

    const suffix = query.toString() ? `?${query.toString()}` : ''
    return unwrapData(await apiClient.get(`/reviews${suffix}`))
  },
  createReview: async (payload) => unwrapData(await apiClient.post('/reviews', payload)),
  updateReview: async (reviewId, payload) =>
    unwrapData(await apiClient.put(`/reviews/${reviewId}`, payload)),

  markNotificationRead: async (notificationId) =>
    unwrapData(await apiClient.put(`/notifications/${notificationId}`, { read: true })),
  markAllNotificationsRead: async () => apiClient.post('/notifications/mark-all-read', {}),

  createListing: async (payload) => unwrapData(await apiClient.post('/listings', payload)),
  getListing: async (listingId) => unwrapData(await apiClient.get(`/listings/${listingId}`)),
  updateListing: async (listingId, payload) =>
    unwrapData(await apiClient.put(`/listings/${listingId}`, payload)),
  deleteListing: async (listingId) => apiClient.delete(`/listings/${listingId}`),

  submitSupportTicket: async (payload) => unwrapData(await apiClient.post('/support', payload)),

  createDrawCampaign: async (payload) => unwrapData(await apiClient.post('/draws', payload)),
  updateDrawCampaign: async (drawId, payload) =>
    unwrapData(await apiClient.put(`/draws/${drawId}`, payload)),
  deleteDrawCampaign: async (drawId) => apiClient.delete(`/draws/${drawId}`),
  createDrawEntry: async (drawId, payload) =>
    unwrapData(await apiClient.post(`/draws/${drawId}/entries`, payload)),

  getTradeRequests: async (filters = {}) => {
    const query = new URLSearchParams()
    Object.entries(filters).forEach(([key, value]) => {
      if (value == null || value === '') return
      query.set(key, String(value))
    })
    const suffix = query.toString() ? `?${query.toString()}` : ''
    return unwrapData(await apiClient.get(`/trades/requests${suffix}`))
  },
  createTradeRequest: async (payload) =>
    unwrapData(await apiClient.post('/trades/requests', payload)),
  acceptTradeRequest: async (tradeRequestId) =>
    unwrapData(await apiClient.post(`/trades/requests/${tradeRequestId}/accept`, {})),
  rejectTradeRequest: async (tradeRequestId) =>
    unwrapData(await apiClient.post(`/trades/requests/${tradeRequestId}/reject`, {})),
  cancelTradeRequest: async (tradeRequestId) =>
    unwrapData(await apiClient.post(`/trades/requests/${tradeRequestId}/cancel`, {})),
  getTradeDeals: async (filters = {}) => {
    const query = new URLSearchParams()
    Object.entries(filters).forEach(([key, value]) => {
      if (value == null || value === '') return
      query.set(key, String(value))
    })
    const suffix = query.toString() ? `?${query.toString()}` : ''
    return unwrapData(await apiClient.get(`/trades/deals${suffix}`))
  },
  createTradeCheckoutSession: async (tradeDealId) =>
    unwrapData(await apiClient.post(`/trades/deals/${tradeDealId}/checkout-session`, {})),
  confirmTradeRelease: async (tradeDealId) =>
    unwrapData(await apiClient.post(`/trades/deals/${tradeDealId}/release`, {})),
  openTradeDispute: async (tradeDealId, payload) =>
    unwrapData(await apiClient.post(`/trades/deals/${tradeDealId}/dispute`, payload)),
  resolveTradeDispute: async (tradeDealId, payload) =>
    unwrapData(await apiClient.post(`/trades/admin/deals/${tradeDealId}/resolve`, payload)),

  createAuctionBid: async (listingId, amount) =>
    unwrapData(await apiClient.post(`/auctions/listings/${listingId}/bids`, { amount })),

  likeProfile: async (handle) => unwrapData(await apiClient.post(`/profiles/${handle}/like`, {})),
  unlikeProfile: async (handle) => unwrapData(await apiClient.delete(`/profiles/${handle}/like`)),
  followProfile: async (handle) => unwrapData(await apiClient.post(`/profiles/${handle}/follow`, {})),
  unfollowProfile: async (handle) => unwrapData(await apiClient.delete(`/profiles/${handle}/follow`)),

  updateProfile: async (payload) => unwrapData(await apiClient.put('/profile', payload)),
  updateAccountEmail: async (payload) => {
    const response = await apiClient.put('/profile/account/email', payload)
    return {
      user: unwrapData(response),
      message: response?.message ?? '',
      verificationEmailSent: response?.verification_email_sent !== false,
    }
  },
  updateAccountPassword: async (payload) => {
    const response = await apiClient.put('/profile/account/password', payload)
    return {
      message: response?.message ?? '',
    }
  },
  getProfileCollection: async () => unwrapData(await apiClient.get('/profile/collection')),
  createProfileCollectionEntry: async (payload) =>
    unwrapData(await apiClient.post('/profile/collection', payload)),
  updateProfileCollectionEntry: async (entryId, payload) =>
    unwrapData(await apiClient.put(`/profile/collection/${entryId}`, payload)),
  deleteProfileCollectionEntry: async (entryId) =>
    apiClient.delete(`/profile/collection/${entryId}`),

  uploadFiles: async (files, collection = 'general') =>
    unwrapData(await apiClient.upload('/uploads', { files, collection })),

  submitVerification: async (payload) =>
    unwrapData(await apiClient.post('/profile/verification', payload)),
  updateVerification: async (submissionId, payload) =>
    unwrapData(await apiClient.put(`/profile/verification/${submissionId}`, payload)),
}
