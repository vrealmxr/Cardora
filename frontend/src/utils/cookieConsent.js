export const COOKIE_CONSENT_STORAGE_KEY = 'cardora_cookie_consent'

export const defaultCookieConsent = {
  necessary: true,
  preferences: false,
  analytics: false,
  updated_at: null,
  version: 1,
}

export const normalizeCookieConsent = (input) => {
  const source = input && typeof input === 'object' ? input : {}

  return {
    necessary: true,
    preferences: Boolean(source.preferences),
    analytics: Boolean(source.analytics),
    updated_at: source.updated_at ?? null,
    version: Number(source.version ?? defaultCookieConsent.version),
  }
}

export const readCookieConsent = () => {
  if (typeof window === 'undefined') return null

  try {
    const raw = window.localStorage.getItem(COOKIE_CONSENT_STORAGE_KEY)
    if (!raw) return null

    return normalizeCookieConsent(JSON.parse(raw))
  } catch (error) {
    return null
  }
}

export const writeCookieConsent = (input) => {
  const normalized = normalizeCookieConsent({
    ...input,
    updated_at: new Date().toISOString(),
  })

  if (typeof window !== 'undefined') {
    window.localStorage.setItem(COOKIE_CONSENT_STORAGE_KEY, JSON.stringify(normalized))
  }

  return normalized
}
