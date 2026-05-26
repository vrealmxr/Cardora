import { Outlet } from 'react-router-dom'
import Footer from '@/components/layout/Footer'
import Navbar from '@/components/layout/Navbar'

function MainLayout() {
  return (
    <div className="hero-bg relative min-h-screen overflow-x-hidden">
      <div className="hero-content">
        <Navbar />
        <main className="relative pt-[104px] sm:pt-[112px]">
          <Outlet />
        </main>
        <Footer />
      </div>
    </div>
  )
}

export default MainLayout
