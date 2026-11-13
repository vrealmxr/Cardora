const ORIGIN = 'https://app.chatwoot.com'
// Public website-inbox identifier from the existing installation, not an API key.
const WEBSITE_TOKEN = 'SXVsTawAEUBzN2nNkZk9mxiM'

export function createSupportChat(win, doc, { timeoutMs = 20000 } = {}) {
  let loading
  let failedAfterRun = false
  const production = ['cardora.gr', 'www.cardora.gr'].includes(win.location.hostname)
  return {
    open(locale) {
      if (!production) return Promise.reject(new Error('preview'))
      if (win.$chatwoot?.hasLoaded) {
        win.$chatwoot.toggle('open')
        return Promise.resolve()
      }
      if (loading) return loading
      // A timed-out SDK can retain listeners. Do not initialize it twice.
      if (failedAfterRun) return Promise.reject(new Error('reload'))
      loading = new Promise((resolve, reject) => {
        let started = false
        const script = doc.createElement('script')
        const cleanup = () => {
          win.clearTimeout(timer)
          win.removeEventListener('chatwoot:ready', ready)
          win.removeEventListener('chatwoot:error', fail)
          script.onload = null
          script.onerror = null
        }
        const fail = () => {
          cleanup()
          failedAfterRun = started
          script.remove()
          reject(new Error(started ? 'reload' : 'unavailable'))
        }
        const ready = () => {
          if (!win.$chatwoot?.hasLoaded) return
          cleanup()
          try { win.$chatwoot.toggle('open'); resolve() } catch { fail() }
        }
        const timer = win.setTimeout(fail, timeoutMs)
        win.addEventListener('chatwoot:ready', ready)
        win.addEventListener('chatwoot:error', fail)
        win.chatwootSettings = {
          position: 'right', type: 'standard', locale: locale === 'en' ? 'en' : 'el',
          useBrowserLanguage: false, showUnreadMessagesDialog: false,
        }
        script.id = 'cardora-requested-support-chat'
        script.src = `${ORIGIN}/packs/js/sdk.js`
        script.referrerPolicy = 'no-referrer'
        script.async = true
        script.onload = () => {
          try {
            if (!win.chatwootSDK?.run) return fail()
            started = true
            win.chatwootSDK.run({ websiteToken: WEBSITE_TOKEN, baseUrl: ORIGIN })
          } catch { fail() }
        }
        script.onerror = fail
        doc.head.appendChild(script)
      }).finally(() => { loading = undefined })
      return loading
    },
  }
}

let client
export function getSupportChat() {
  if (typeof window === 'undefined') return null
  client ||= createSupportChat(window, document)
  return client
}
