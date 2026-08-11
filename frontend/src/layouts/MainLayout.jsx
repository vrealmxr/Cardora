import { Outlet } from 'react-router-dom'
import Footer from '@/components/layout/Footer'
import Navbar from '@/components/layout/Navbar'
import { useAuth } from '@/hooks/useAuth'
import { useMarketplace } from '@/hooks/useMarketplace'
import { cn } from '@/utils/helpers'

function MainLayout() {
  const { isAuthenticated } = useAuth()
  const { marketplaceAccess } = useMarketplace()
  const showActivationBanner = Boolean(isAuthenticated && marketplaceAccess && !marketplaceAccess.is_marketplace_ready)

  return (
    <div className="hero-bg relative min-h-screen overflow-x-hidden">
      <div className="hero-content">
        <Navbar />
        <main
          className={cn(
            'relative pt-[104px] sm:pt-[112px]',
            showActivationBanner && 'pt-[218px] sm:pt-[204px] lg:pt-[176px]',
          )}
        >
          <Outlet />
        </main>
        <Footer />
      </div>
    </div>
  )
}

export default MainLayout
