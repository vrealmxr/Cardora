import { AuthProvider } from '@/context/AuthContext'
import { I18nProvider } from '@/context/I18nContext'
import { MarketplaceProvider } from '@/context/MarketplaceContext'
import { PageLoaderProvider } from '@/context/PageLoaderContext'
import CookieBanner from '@/components/legal/CookieBanner'
import AppLoader from '@/components/ui/AppLoader'
import AppRoutes from '@/routes/AppRoutes'
import ScrollToTop from '@/routes/ScrollToTop'

function App() {
  return (
    <I18nProvider>
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
    </I18nProvider>
  )
}

export default App
