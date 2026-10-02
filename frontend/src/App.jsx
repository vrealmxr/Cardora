import { AuthProvider } from '@/context/AuthContext'
import { I18nProvider } from '@/context/I18nContext'
import { MarketplaceProvider } from '@/context/MarketplaceContext'
import { PageLoaderProvider } from '@/context/PageLoaderContext'
import { SeoProvider } from '@/context/SeoContext'
import CookieBanner from '@/components/legal/CookieBanner'
import AppLoader from '@/components/ui/AppLoader'
import AppRoutes from '@/routes/AppRoutes'
import PageViewTracker from '@/routes/PageViewTracker'
import ScrollToTop from '@/routes/ScrollToTop'

function App() {
  return (
    <I18nProvider>
      <SeoProvider>
        <AuthProvider>
          <MarketplaceProvider>
            <PageLoaderProvider>
              <ScrollToTop />
              <AppLoader />
              <CookieBanner />
              <AppRoutes />
              {/*
                Mounted after AppRoutes on purpose: React flushes a subtree's
                passive effects bottom-up before moving to the next sibling,
                so placing this last guarantees it reads document.title only
                after the route's own <PageSeo> effect has already set it.
                Mounting it earlier (even a sibling before AppRoutes) meant
                it fired first and captured the PREVIOUS page's title on
                every same-commit navigation -- i.e. whenever the new route's
                SEO data was already cached, which is almost always.
              */}
              <PageViewTracker />
            </PageLoaderProvider>
          </MarketplaceProvider>
        </AuthProvider>
      </SeoProvider>
    </I18nProvider>
  )
}

export default App
