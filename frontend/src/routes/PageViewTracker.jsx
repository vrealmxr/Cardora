import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'
import { COOKIE_CONSENT_EVENT, COOKIE_CONSENT_STORAGE_KEY, readCookieConsent, resetSessionCookieConsent, cookieConsentIsPersistent } from '@/utils/cookieConsent'
import { getAnalyticsClient, PAGE_METADATA_EVENT, readPageMetadata } from '@/utils/analytics'

function PageViewTracker() {
  const { key, pathname, search } = useLocation()

  useEffect(() => {
    const client = getAnalyticsClient()
    const trackReadyPage = () => {
      const page = readPageMetadata()
      if (page?.navigationId === key && page.pathname === pathname && page.search === search) client.track(page)
    }
    const consentChanged = (event) => {
      client.setConsent(event?.type === COOKIE_CONSENT_EVENT ? event.detail : readCookieConsent(), { reloadOnWithdrawal: cookieConsentIsPersistent() })
      trackReadyPage()
    }
    const storageChanged = (event) => {
      if (event.key === null || event.key === COOKIE_CONSENT_STORAGE_KEY) {
        resetSessionCookieConsent()
        consentChanged()
      }
    }
    client.track({ pathname, status: 503 })
    consentChanged()
    window.addEventListener(PAGE_METADATA_EVENT, trackReadyPage)
    window.addEventListener(COOKIE_CONSENT_EVENT, consentChanged)
    window.addEventListener('storage', storageChanged)
    return () => {
      window.removeEventListener(PAGE_METADATA_EVENT, trackReadyPage)
      window.removeEventListener(COOKIE_CONSENT_EVENT, consentChanged)
      window.removeEventListener('storage', storageChanged)
    }
  }, [key, pathname, search])

  return null
}

export default PageViewTracker
