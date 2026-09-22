import { AuthProvider } from '@/context/AuthContext'
import { I18nProvider } from '@/context/I18nContext'
import { MarketplaceProvider } from '@/context/MarketplaceContext'
import { PageLoaderProvider } from '@/context/PageLoaderContext'
import { SeoProvider } from '@/context/SeoContext'
import CookieBanner from '@/components/legal/CookieBanner'
import AppLoader from '@/components/ui/AppLoader'
import AppRoutes from '@/routes/AppRoutes'
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
            </PageLoaderProvider>
          </MarketplaceProvider>
        </AuthProvider>
      </SeoProvider>
    </I18nProvider>
  )
}

export default App
