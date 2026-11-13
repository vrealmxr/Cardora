import { createRoot } from 'react-dom/client'
import { act } from 'react'
import { useState } from 'react'
import { usePublicListing } from '@/hooks/usePublicListing'
import { MemoryRouter, useNavigate, useLocation } from 'react-router-dom'
import OrderCard from '@/components/orders/OrderCard'
import MessagesPage from '@/pages/MessagesPage'
import { AuthProvider, useAuthContext } from '@/context/AuthContext'
import { I18nProvider, useI18nContext } from '@/context/I18nContext'
import { MarketplaceSession, useMarketplaceContext } from '@/context/MarketplaceContext'
import CreateListingPage from '@/pages/CreateListingPage'
import ProductDetailPage from '@/pages/ProductDetailPage'
import { Routes, Route } from 'react-router-dom'
import { SeoProvider } from '@/context/SeoContext'
export { apiClient, setStoredAuthToken, getStoredAuthToken } from '@/services/apiClient'
export { act }

let controls
function Probe() {
  controls = { auth: useAuthContext(), market: useMarketplaceContext(), language: useI18nContext(), navigate: useNavigate(), location: useLocation() }
  return <div>{controls.auth.currentUser?.id || 'guest'}</div>
}
export const getControls = () => controls
export function mount(container, order = null, messages = false) {
  const root = createRoot(container)
  root.render(<MemoryRouter initialEntries={['/el']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
    <I18nProvider><AuthProvider><MarketplaceSession><Probe />{order && <OrderCard order={order} />}{messages && <MessagesPage />}</MarketplaceSession></AuthProvider></I18nProvider>
  </MemoryRouter>)
  return root
}

export function mountListingForm(container, data) {
  const root = createRoot(container)
  root.render(<MemoryRouter initialEntries={['/en/dimiourgia-aggelias?edit=1']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
    <I18nProvider><AuthProvider><MarketplaceSession initialPublicData={data}><Probe /><CreateListingPage /></MarketplaceSession></AuthProvider></I18nProvider>
  </MemoryRouter>)
  return root
}

export function mountAuctionPage(container) {
  const root = createRoot(container)
  root.render(<MemoryRouter initialEntries={['/en/proion/fixture-auction']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
    <I18nProvider><AuthProvider><MarketplaceSession><SeoProvider initialSettings={{}}><Probe /><Routes><Route path="/:locale/proion/:slug" element={<ProductDetailPage />} /></Routes></SeoProvider></MarketplaceSession></AuthProvider></I18nProvider>
  </MemoryRouter>)
  return root
}

let listingControls
function ListingProbe({ seed }) {
  const [route, setRoute] = useState({ slug: seed.slug, locale: seed.locale })
  const listing = usePublicListing(route.slug, route.locale, seed)
  listingControls = { ...listing, setRoute }
  return <output>{JSON.stringify(listing.result)}</output>
}
export const getListingControls = () => listingControls
export function mountListing(container, seed) {
  const root = createRoot(container)
  root.render(<ListingProbe seed={seed} />)
  return root
}
