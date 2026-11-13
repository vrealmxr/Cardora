import { StrictMode, useState } from 'react'
import { createRoot } from 'react-dom/client'
import { MemoryRouter, useLocation, useNavigate } from 'react-router-dom'
import { I18nProvider } from '@/context/I18nContext'
import { SeoProvider } from '@/context/SeoContext'
import PageSeo from '@/components/PageSeo'
import PageViewTracker from '@/routes/PageViewTracker'
import CookieBanner from '@/components/legal/CookieBanner'
import SupportChat from '@/components/support/SupportChat'
import ProductVisual from '@/components/catalog/ProductVisual'
export { act } from 'react'
export { writeCookieConsent, readCookieConsent, openCookieSettings } from '@/utils/cookieConsent'
export { getListingFormData } from '@/data/listingFormData'
let controls
export const getControls = () => controls
function Page() {
  const location = useLocation()
  const [ready, setReady] = useState(true)
  controls = { navigate: useNavigate(), setReady }
  return ready ? <PageSeo pageKey="qa" fallbackTitle={`Ready ${location.pathname}`} fallbackDescription="QA" /> : <p>Loading</p>
}
export function mount(container) {
  const root = createRoot(container)
  root.render(<StrictMode><MemoryRouter initialEntries={['/el/kartes']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
    <I18nProvider><SeoProvider initialSettings={{}}><CookieBanner /><Page /><PageViewTracker /></SeoProvider></I18nProvider>
  </MemoryRouter></StrictMode>)
  return root
}

export function mountSupport(container) {
  const root = createRoot(container)
  root.render(<StrictMode><MemoryRouter initialEntries={['/el/kartes']}>
    <I18nProvider><CookieBanner /><SupportChat /></I18nProvider>
  </MemoryRouter></StrictMode>)
  return root
}

export function mountVisual(container, product) {
  const root = createRoot(container)
  const render = next => root.render(<StrictMode><MemoryRouter><I18nProvider>
    <div className="group"><ProductVisual product={next} hoverSwap /></div>
  </I18nProvider></MemoryRouter></StrictMode>)
  render(product)
  return { unmount: () => root.unmount(), render }
}
