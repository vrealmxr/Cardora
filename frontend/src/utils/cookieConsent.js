export const COOKIE_CONSENT_STORAGE_KEY = 'cardora_cookie_consent'
export const COOKIE_CONSENT_EVENT = 'cardora:cookie-consent'
export const COOKIE_SETTINGS_EVENT = 'cardora:cookie-settings'
let sessionConsent = null
let memoryOnly = false
export const resetSessionCookieConsent = () => { sessionConsent = null; memoryOnly = false }
export const cookieConsentIsPersistent = () => !memoryOnly
export const openCookieSettings = () => window.dispatchEvent(new CustomEvent(COOKIE_SETTINGS_EVENT))

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
    preferences: source.preferences === true,
    analytics: source.analytics === true,
    updated_at: source.updated_at ?? null,
    version: Number(source.version ?? defaultCookieConsent.version),
  }
}

export const readCookieConsent = () => {
  if (typeof window === 'undefined') return null
  if (memoryOnly) return sessionConsent

  try {
    const raw = window.localStorage.getItem(COOKIE_CONSENT_STORAGE_KEY)
    if (!raw) return null

    return normalizeCookieConsent(JSON.parse(raw))
  } catch (error) {
    return sessionConsent
  }
}

export const writeCookieConsent = (input) => {
  const normalized = normalizeCookieConsent({
    ...input,
    updated_at: new Date().toISOString(),
  })

  if (typeof window !== 'undefined') {
    sessionConsent = normalized
    try {
      window.localStorage.setItem(COOKIE_CONSENT_STORAGE_KEY, JSON.stringify(normalized))
      memoryOnly = false
    } catch {
      memoryOnly = true
      // Do not leave a stale opt-in behind if quota blocks replacing it.
      try { window.localStorage.removeItem(COOKIE_CONSENT_STORAGE_KEY) } catch { /* Storage may be disabled entirely. */ }
    }
    window.dispatchEvent(new CustomEvent(COOKIE_CONSENT_EVENT, { detail: normalized }))
  }

  return normalized
}
