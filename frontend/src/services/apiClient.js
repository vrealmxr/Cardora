import { normalizeTextTree } from '@/utils/textEncoding'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '/index.php/api'
const ABSOLUTE_URL_PATTERN = /^https?:\/\//i

export const AUTH_TOKEN_KEY = 'cardora-auth-token'
export const LOCALE_STORAGE_KEY = 'cardora-locale'
export const AUTH_EXPIRED_EVENT = 'cardora:auth-expired'

const getStoredLocale = () => window.localStorage.getItem(LOCALE_STORAGE_KEY) || 'el'

export const getStoredAuthToken = () => window.localStorage.getItem(AUTH_TOKEN_KEY) || ''

export const setStoredAuthToken = (token) => {
  if (!token) {
    window.localStorage.removeItem(AUTH_TOKEN_KEY)
    return
  }

  window.localStorage.setItem(AUTH_TOKEN_KEY, token)
}

export const clearStoredAuthToken = () => {
  window.localStorage.removeItem(AUTH_TOKEN_KEY)
}

const normalizeErrorMessage = (payload, fallback) => {
  if (payload?.message) return payload.message

  const firstValidationError = payload?.errors
    ? Object.values(payload.errors).flat().find(Boolean)
    : null

  return firstValidationError ?? fallback
}

export const resolveApiUrl = (endpoint = '') => {
  const baseUrl = API_BASE_URL.endsWith('/') ? API_BASE_URL.slice(0, -1) : API_BASE_URL
  const path = endpoint ? (endpoint.startsWith('/') ? endpoint : `/${endpoint}`) : ''
  const url = `${baseUrl}${path}`

  if (ABSOLUTE_URL_PATTERN.test(url)) {
    return url
  }

  return new URL(url, window.location.origin).toString()
}

const request = async (endpoint, options = {}) => {
  const isFormData = options.body instanceof FormData
  const token = getStoredAuthToken()
  const locale = getStoredLocale()

  const response = await fetch(resolveApiUrl(endpoint), {
    ...options,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Locale': locale,
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...(options.headers ?? {}),
    },
  })

  const rawText = await response.text()
  let payload = null

  if (rawText) {
    try {
      payload = normalizeTextTree(JSON.parse(rawText))
    } catch (error) {
      payload = null
    }
  }

  if (!response.ok) {
    if (response.status === 401) {
      clearStoredAuthToken()

      if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent(AUTH_EXPIRED_EVENT))
      }
    }

    const error = new Error(
      normalizeErrorMessage(payload, `API request failed for ${endpoint}`),
    )

    error.status = response.status
    error.payload = payload
    error.errors = payload?.errors ?? null
    error.raw = rawText

    throw error
  }

  return payload
}

export const apiClient = {
  get: (endpoint) => request(endpoint),
  post: (endpoint, body) =>
    request(endpoint, {
      method: 'POST',
      body: body instanceof FormData ? body : JSON.stringify(body ?? {}),
    }),
  put: (endpoint, body) =>
    request(endpoint, {
      method: 'PUT',
      body: body instanceof FormData ? body : JSON.stringify(body ?? {}),
    }),
  delete: (endpoint) =>
    request(endpoint, {
      method: 'DELETE',
    }),
  upload: (endpoint, { files, collection = 'general' }) => {
    const formData = new FormData()
    formData.append('collection', collection)
    files.forEach((file) => formData.append('files[]', file))

    return request(endpoint, {
      method: 'POST',
      body: formData,
    })
  },
}
