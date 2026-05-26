import { Navigate, Route, Routes } from 'react-router-dom'
import MainLayout from '@/layouts/MainLayout'
import AboutPage from '@/pages/AboutPage'
import AccountSettingsPage from '@/pages/AccountSettingsPage'
import AuthPage from '@/pages/AuthPage'
import BlogArticlePage from '@/pages/BlogArticlePage'
import BlogPage from '@/pages/BlogPage'
import CartPage from '@/pages/CartPage'
import CategoryPage from '@/pages/CategoryPage'
import CheckoutPage from '@/pages/CheckoutPage'
import CheckoutSuccessPage from '@/pages/CheckoutSuccessPage'
import CollectorProfilePage from '@/pages/CollectorProfilePage'
import ContactPage from '@/pages/ContactPage'
import CookiePolicyPage from '@/pages/CookiePolicyPage'
import CreateListingPage from '@/pages/CreateListingPage'
import DsaNoticeActionPage from '@/pages/DsaNoticeActionPage'
import DrawsPage from '@/pages/DrawsPage'
import EmailVerificationPage from '@/pages/EmailVerificationPage'
import FavoritesPage from '@/pages/FavoritesPage'
import FaqPage from '@/pages/FaqPage'
import ForgotPasswordPage from '@/pages/ForgotPasswordPage'
import GoogleAuthCallbackPage from '@/pages/GoogleAuthCallbackPage'
import HomePage from '@/pages/HomePage'
import MessagesPage from '@/pages/MessagesPage'
import MyListingsPage from '@/pages/MyListingsPage'
import NotFoundPage from '@/pages/NotFoundPage'
import OrdersPage from '@/pages/OrdersPage'
import NotificationPreferencesPage from '@/pages/NotificationPreferencesPage'
import ProductDetailPage from '@/pages/ProductDetailPage'
import PrivacyPage from '@/pages/PrivacyPage'
import ProhibitedItemsPage from '@/pages/ProhibitedItemsPage'
import ProfilePage from '@/pages/ProfilePage'
import RaffleStudioPage from '@/pages/RaffleStudioPage'
import ResetPasswordPage from '@/pages/ResetPasswordPage'
import RefundsDisputesPage from '@/pages/RefundsDisputesPage'
import SearchResultsPage from '@/pages/SearchResultsPage'
import SellerDashboardPage from '@/pages/SellerDashboardPage'
import SupportCenterPage from '@/pages/SupportCenterPage'
import TermsPage from '@/pages/TermsPage'
import VerificationPage from '@/pages/VerificationPage'

function AppRoutes() {
  return (
    <Routes>
      <Route element={<MainLayout />}>
        <Route path="/" element={<HomePage />} />
        <Route path="/kartes" element={<CategoryPage categorySlug="kartes" />} />
        <Route path="/figoures" element={<CategoryPage categorySlug="figoures" />} />
        <Route path="/komik-vivlia" element={<CategoryPage categorySlug="komik-vivlia" />} />
        <Route path="/diafora" element={<CategoryPage categorySlug="diafora" />} />
        <Route path="/cardora" element={<AboutPage />} />
        <Route path="/oroi-xrisis" element={<TermsPage />} />
        <Route path="/politiki-aporritou" element={<PrivacyPage />} />
        <Route path="/politiki-cookies" element={<CookiePolicyPage />} />
        <Route path="/dsa-notice-action" element={<DsaNoticeActionPage />} />
        <Route path="/epistrofes-kai-diafores" element={<RefundsDisputesPage />} />
        <Route path="/apagorevmena-antikeimena" element={<ProhibitedItemsPage />} />
        <Route path="/kliroseis" element={<DrawsPage />} />
        <Route path="/koinotikes-kliroseis" element={<Navigate to="/kliroseis" replace />} />
        <Route path="/blog" element={<BlogPage />} />
        <Route path="/blog/:slug" element={<BlogArticlePage />} />
        <Route path="/epikoinonia" element={<ContactPage />} />
        <Route path="/eisodos" element={<AuthPage mode="login" />} />
        <Route path="/eggrafi" element={<AuthPage mode="register" />} />
        <Route path="/auth/google/callback" element={<GoogleAuthCallbackPage />} />
        <Route path="/epivevaiosi-email" element={<EmailVerificationPage />} />
        <Route path="/verify-email" element={<Navigate to="/epivevaiosi-email" replace />} />
        <Route path="/email-verification" element={<Navigate to="/epivevaiosi-email" replace />} />
        <Route path="/email/verification" element={<Navigate to="/epivevaiosi-email" replace />} />
        <Route path="/xechasa-kodiko" element={<ForgotPasswordPage />} />
        <Route path="/epanafora-kodikou" element={<ResetPasswordPage />} />
        <Route path="/profil" element={<ProfilePage />} />
        <Route path="/rythmiseis-logariasmou" element={<AccountSettingsPage />} />
        <Route path="/rythmiseis-eidopoiiseon" element={<NotificationPreferencesPage />} />
        <Route path="/sylloges/:handle" element={<CollectorProfilePage />} />
        <Route path="/epalithefsi-logariasmou" element={<VerificationPage />} />
        <Route path="/dashboard-agorasti" element={<OrdersPage initialTab="buyer" />} />
        <Route path="/dashboard-politi" element={<SellerDashboardPage />} />
        <Route path="/dashboard-politi/kliroseis" element={<RaffleStudioPage />} />
        <Route path="/kalathi" element={<CartPage />} />
        <Route path="/agapimena" element={<FavoritesPage />} />
        <Route path="/proion/:slug" element={<ProductDetailPage />} />
        <Route path="/anazitisi" element={<SearchResultsPage />} />
        <Route path="/checkout" element={<CheckoutPage />} />
        <Route path="/checkout/success" element={<CheckoutSuccessPage />} />
        <Route path="/paraggelies" element={<OrdersPage />} />
        <Route path="/oi-aggelies-mou" element={<MyListingsPage />} />
        <Route path="/dimiourgia-aggelias" element={<CreateListingPage />} />
        <Route path="/minymata" element={<MessagesPage />} />
        <Route path="/kentro-ypostiriksis" element={<SupportCenterPage />} />
        <Route path="/faq" element={<FaqPage />} />
        <Route path="/login" element={<Navigate to="/eisodos" replace />} />
        <Route path="/register" element={<Navigate to="/eggrafi" replace />} />
        <Route path="/search" element={<Navigate to="/anazitisi" replace />} />
        <Route path="/terms" element={<Navigate to="/oroi-xrisis" replace />} />
        <Route path="/privacy" element={<Navigate to="/politiki-aporritou" replace />} />
        <Route path="/cookies" element={<Navigate to="/politiki-cookies" replace />} />
        <Route path="/notice-and-action" element={<Navigate to="/dsa-notice-action" replace />} />
        <Route path="/refunds" element={<Navigate to="/epistrofes-kai-diafores" replace />} />
        <Route path="/prohibited-items" element={<Navigate to="/apagorevmena-antikeimena" replace />} />
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  )
}

export default AppRoutes
