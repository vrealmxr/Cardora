import { apiClient } from '@/services/apiClient'

export function fetchBinderGames() {
  return apiClient.get('/binder/games')
}

export function fetchBinderSets(gameSlug, { search = '', page = 1, per_page: perPage } = {}) {
  const params = new URLSearchParams()
  if (search) params.set('search', search)
  if (page > 1) params.set('page', String(page))
  if (perPage) params.set('per_page', String(perPage))
  const qs = params.toString()
  return apiClient.get(`/binder/games/${gameSlug}/sets${qs ? `?${qs}` : ''}`)
}

export function fetchBinderCards(setId, { search = '', rarity = '', type = 'cards', page = 1 } = {}) {
  const params = new URLSearchParams()
  if (search) params.set('search', search)
  if (rarity) params.set('rarity', rarity)
  if (type) params.set('type', type)
  if (page > 1) params.set('page', String(page))
  const qs = params.toString()
  return apiClient.get(`/binder/sets/${setId}/cards${qs ? `?${qs}` : ''}`)
}

export function toggleBinderCardOwned(cardId, price) {
  return apiClient.post(`/binder/cards/${cardId}/toggle`, price != null ? { price } : {})
}

export function updateBinderCardPrice(cardId, price) {
  return apiClient.put(`/binder/cards/${cardId}/price`, { price })
}

export function fetchMyBinderCollection() {
  return apiClient.get('/binder/my-collection')
}

export function fetchBinderPortfolio() {
  return apiClient.get('/binder/portfolio')
}

export function fetchBinderAlertSettings() {
  return apiClient.get('/binder/alerts')
}

export function updateBinderAlertSettings(enabled) {
  return apiClient.put('/binder/alerts', { enabled })
}

export function watchBinderSet(setId) {
  return apiClient.post('/binder/alerts/watch', { set_id: setId })
}

export function unwatchBinderSet(setId) {
  return apiClient.delete(`/binder/alerts/watch/${setId}`)
}
