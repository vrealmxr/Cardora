import { resolvePublicRoute } from '../server/routePolicy.js'

export const ANALYTICS_ID = 'G-Q4WX9WDS2V'
export const PAGE_METADATA_EVENT = 'cardora:page-metadata'
const GTM_ID = 'GTM-K77KBTL8'
const denied = { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' }

// Lazy pages publish after applying their title. Preserve the latest snapshot
// so tracker/page effect ordering cannot lose the initial navigation.
let metadata
export function publishPageMetadata(value) {
  if (typeof window === 'undefined') return
  metadata = value
  window.dispatchEvent(new CustomEvent(PAGE_METADATA_EVENT))
}
export const readPageMetadata = () => metadata

// Tests inject a DOM and reload callback; they never contact Google Analytics.
export function createAnalyticsClient(win, doc, { reload = () => win.location.reload() } = {}) {
  const production = ['cardora.gr', 'www.cardora.gr'].includes(win.location.hostname)
  let consent = false
  let loaded = false
  let initialized = false
  let lastVisit = null
  let previousLocation = ''
  const command = function () { win.dataLayer.push(arguments) }
  const disable = (value) => { win[`ga-disable-${ANALYTICS_ID}`] = value }
  disable(true)
  return {
    setConsent(value, { reloadOnWithdrawal = true } = {}) {
      const allowed = value?.analytics === true
      if (allowed === consent) return
      consent = allowed
      if (!allowed) {
        disable(true)
        if (loaded) {
          command('consent', 'update', denied)
          // Removing a script does not remove its listeners. Reload with the
          // persisted denied choice to unload the third-party runtime.
          if (reloadOnWithdrawal) reload()
        }
      }
    },
    track(page) {
      const publicPage = page && resolvePublicRoute(page.pathname).kind === 'public' && page.status < 400
      disable(!consent || !publicPage)
      if (!production || !consent || !publicPage || !page.title) return false
      const visit = `${page.navigationId}|${page.pathname}|${page.search || ''}`
      if (visit === lastVisit) return false
      // Never pass search text, emails, tokens or arbitrary query parameters.
      const number = new URLSearchParams(page.search).get('page')
      const pagePath = page.pathname + (/^[1-9]\d{0,5}$/.test(number || '') && Number(number) > 1 ? `?page=${Number(number)}` : '')
      const location = `https://cardora.gr${pagePath}`
      const title = /\/anazitisi$/.test(page.pathname) ? 'Cardora search' : page.title
      win.dataLayer = win.dataLayer || []
      if (!initialized) {
        command('consent', 'default', denied)
        command('set', { send_page_view: false, allow_google_signals: false, allow_ad_personalization_signals: false })
        initialized = true
      }
      if (!loaded) {
        command('consent', 'update', { ...denied, analytics_storage: 'granted' })
        win.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' })
        const script = doc.createElement('script')
        script.id = 'cardora-consented-gtm'
        script.async = true
        script.src = `https://www.googletagmanager.com/gtm.js?id=${GTM_ID}`
        doc.head.appendChild(script)
        loaded = true
      }
      command('event', 'page_view', { send_to: ANALYTICS_ID, page_path: pagePath,
        page_location: location, page_title: title, page_referrer: previousLocation })
      lastVisit = visit
      previousLocation = location
      return true
    },
  }
}

let client
export function getAnalyticsClient() {
  if (typeof window === 'undefined') return null
  client ||= createAnalyticsClient(window, document)
  return client
}
