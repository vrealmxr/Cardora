import { AuthProvider } from '@/context/AuthContext'
import { I18nProvider } from '@/context/I18nContext'
import { MarketplaceSession } from '@/context/MarketplaceContext'
import { PageLoaderProvider } from '@/context/PageLoaderContext'
import { SeoProvider } from '@/context/SeoContext'
import CookieBanner from '@/components/legal/CookieBanner'
import AppLoader from '@/components/ui/AppLoader'
import AppRoutes from '@/routes/AppRoutes'
import PageViewTracker from '@/routes/PageViewTracker'
import ScrollToTop from '@/routes/ScrollToTop'
import SupportChat from '@/components/support/SupportChat'

function App({ initialPublicData, initialSeo, serverHead }) {
  return (
    <I18nProvider>
      <SeoProvider initialSettings={initialSeo} serverHead={serverHead}>
        <AuthProvider>
          <MarketplaceSession initialPublicData={initialPublicData}>
            <PageLoaderProvider>
              <ScrollToTop />
              <AppLoader />
              <CookieBanner />
              <AppRoutes />
              {/* Waits for route metadata, including asynchronously loaded pages. */}
              <PageViewTracker />
              <SupportChat />
            </PageLoaderProvider>
          </MarketplaceSession>
        </AuthProvider>
      </SeoProvider>
    </I18nProvider>
  )
}

export default App
