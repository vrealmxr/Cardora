import { Suspense, lazy } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import LocaleRedirect from '@/routes/LocaleRedirect'

const MainLayout = lazy(() => import('@/layouts/MainLayout'))
const AboutPage = lazy(() => import('@/pages/AboutPage'))
const AccountSettingsPage = lazy(() => import('@/pages/AccountSettingsPage'))
const AuthPage = lazy(() => import('@/pages/AuthPage'))
const BlogArticlePage = lazy(() => import('@/pages/BlogArticlePage'))
const ComingSoonPage = lazy(() => import('@/pages/ComingSoonPage'))
const BlogPage = lazy(() => import('@/pages/BlogPage'))
const CardoraProSuccessPage = lazy(() => import('@/pages/CardoraProSuccessPage'))
const CartPage = lazy(() => import('@/pages/CartPage'))
const CategoryPage = lazy(() => import('@/pages/CategoryPage'))
const CheckoutPage = lazy(() => import('@/pages/CheckoutPage'))
const CheckoutSuccessPage = lazy(() => import('@/pages/CheckoutSuccessPage'))
const CollectorProfilePage = lazy(() => import('@/pages/CollectorProfilePage'))
const ContactPage = lazy(() => import('@/pages/ContactPage'))
const CookiePolicyPage = lazy(() => import('@/pages/CookiePolicyPage'))
const CreateListingPage = lazy(() => import('@/pages/CreateListingPage'))
const DsaNoticeActionPage = lazy(() => import('@/pages/DsaNoticeActionPage'))
const DrawsPage = lazy(() => import('@/pages/DrawsPage'))
const EmailVerificationPage = lazy(() => import('@/pages/EmailVerificationPage'))
const FavoritesPage = lazy(() => import('@/pages/FavoritesPage'))
const FaqPage = lazy(() => import('@/pages/FaqPage'))
const ForgotPasswordPage = lazy(() => import('@/pages/ForgotPasswordPage'))
const GoogleAuthCallbackPage = lazy(() => import('@/pages/GoogleAuthCallbackPage'))
const HomePage = lazy(() => import('@/pages/HomePage'))
const MessagesPage = lazy(() => import('@/pages/MessagesPage'))
const MyListingsPage = lazy(() => import('@/pages/MyListingsPage'))
const NotFoundPage = lazy(() => import('@/pages/NotFoundPage'))
const OrdersPage = lazy(() => import('@/pages/OrdersPage'))
const NotificationPreferencesPage = lazy(() => import('@/pages/NotificationPreferencesPage'))
const ProductDetailPage = lazy(() => import('@/pages/ProductDetailPage'))
const PrivacyPage = lazy(() => import('@/pages/PrivacyPage'))
const ProhibitedItemsPage = lazy(() => import('@/pages/ProhibitedItemsPage'))
const ProfilePage = lazy(() => import('@/pages/ProfilePage'))
const RaffleStudioPage = lazy(() => import('@/pages/RaffleStudioPage'))
const ResetPasswordPage = lazy(() => import('@/pages/ResetPasswordPage'))
const RefundsDisputesPage = lazy(() => import('@/pages/RefundsDisputesPage'))
const SearchResultsPage = lazy(() => import('@/pages/SearchResultsPage'))
const SellerDashboardPage = lazy(() => import('@/pages/SellerDashboardPage'))
const SupportCenterPage = lazy(() => import('@/pages/SupportCenterPage'))
const TermsPage = lazy(() => import('@/pages/TermsPage'))
const VerificationPage = lazy(() => import('@/pages/VerificationPage'))

function RouteFallback() {
  return (
    <div className="min-h-screen bg-[#fffdfa] px-5 pb-12 pt-28">
      <div className="container">
        <div className="overflow-hidden rounded-[32px] border border-[#eadab7] bg-white shadow-[0_18px_46px_rgba(193,164,111,0.1)]">
          <div className="h-1.5 w-full bg-[linear-gradient(90deg,rgba(212,170,92,0.82),rgba(250,240,214,0.96),rgba(231,211,171,0.84))]" />
          <div className="space-y-5 p-6 sm:p-8">
            <div className="h-4 w-28 rounded-full bg-[#efe5d2]" />
            <div className="h-14 w-full max-w-2xl rounded-[24px] bg-[#f8f2e6]" />
            <div className="grid gap-4 lg:grid-cols-3">
              <div className="h-40 rounded-[28px] border border-[#f0e5d0] bg-[#fffaf0]" />
              <div className="h-40 rounded-[28px] border border-[#f0e5d0] bg-[#fffaf0]" />
              <div className="h-40 rounded-[28px] border border-[#f0e5d0] bg-[#fffaf0]" />
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

function AppRoutes() {
  const localizedAliases = [
    ['koinotikes-kliroseis', '/kliroseis'],
    ['verify-email', '/epivevaiosi-email'],
    ['email-verification', '/epivevaiosi-email'],
    ['email/verification', '/epivevaiosi-email'],
    ['login', '/eisodos'],
    ['register', '/eggrafi'],
    ['search', '/anazitisi'],
    ['terms', '/oroi-xrisis'],
    ['privacy', '/politiki-aporritou'],
    ['cookies', '/politiki-cookies'],
    ['notice-and-action', '/dsa-notice-action'],
    ['refunds', '/epistrofes-kai-diafores'],
    ['prohibited-items', '/apagorevmena-antikeimena'],
  ]

  const renderLocalizedRoutes = (locale) => (
    <Route path={`/${locale}`} element={<MainLayout />}>
      <Route index element={<HomePage />} />
      <Route path="kartes" element={<CategoryPage categorySlug="kartes" />} />
      <Route path="figoures" element={<CategoryPage categorySlug="figoures" />} />
      <Route path="komik-vivlia" element={<CategoryPage categorySlug="komik-vivlia" />} />
      <Route path="diafora" element={<CategoryPage categorySlug="diafora" />} />
      <Route path="cardora" element={<AboutPage />} />
      <Route path="oroi-xrisis" element={<TermsPage />} />
      <Route path="politiki-aporritou" element={<PrivacyPage />} />
      <Route path="politiki-cookies" element={<CookiePolicyPage />} />
      <Route path="dsa-notice-action" element={<DsaNoticeActionPage />} />
      <Route path="epistrofes-kai-diafores" element={<RefundsDisputesPage />} />
      <Route path="apagorevmena-antikeimena" element={<ProhibitedItemsPage />} />
      <Route path="kliroseis" element={<DrawsPage />} />
      <Route path="blog" element={<BlogPage />} />
      <Route path="blog/:slug" element={<BlogArticlePage />} />
      <Route path="epikoinonia" element={<ContactPage />} />
      <Route path="eisodos" element={<AuthPage mode="login" />} />
      <Route path="eggrafi" element={<AuthPage mode="register" />} />
      <Route path="auth/google/callback" element={<GoogleAuthCallbackPage />} />
      <Route path="epivevaiosi-email" element={<EmailVerificationPage />} />
      <Route path="xechasa-kodiko" element={<ForgotPasswordPage />} />
      <Route path="epanafora-kodikou" element={<ResetPasswordPage />} />
      <Route path="profil" element={<ProfilePage />} />
      <Route path="rythmiseis-logariasmou" element={<AccountSettingsPage />} />
      <Route path="rythmiseis-eidopoiiseon" element={<NotificationPreferencesPage />} />
      <Route path="sylloges/:handle" element={<CollectorProfilePage />} />
      <Route path="epalithefsi-logariasmou" element={<VerificationPage />} />
      <Route path="dashboard-agorasti" element={<OrdersPage initialTab="buyer" />} />
      <Route path="dashboard-politi" element={<SellerDashboardPage />} />
      <Route path="dashboard-politi/kliroseis" element={<RaffleStudioPage />} />
      <Route path="kalathi" element={<CartPage />} />
      <Route path="agapimena" element={<FavoritesPage />} />
      <Route path="proion/:slug" element={<ProductDetailPage />} />
      <Route path="anazitisi" element={<SearchResultsPage />} />
      <Route path="checkout" element={<CheckoutPage />} />
      <Route path="checkout/success" element={<CheckoutSuccessPage />} />
      <Route path="paraggelies" element={<OrdersPage />} />
      <Route path="oi-aggelies-mou" element={<MyListingsPage />} />
      <Route path="dimiourgia-aggelias" element={<CreateListingPage />} />
      <Route path="minymata" element={<MessagesPage />} />
      <Route path="kentro-ypostiriksis" element={<SupportCenterPage />} />
      <Route path="faq" element={<FaqPage />} />
      <Route
        path="cardora-pro"
        element={<ComingSoonPage titleEl="Η Cardora PRO έρχεται σύντομα." titleEn="Cardora PRO is coming soon." />}
      />
      <Route path="cardora-pro/success" element={<CardoraProSuccessPage />} />
      {localizedAliases.map(([source, target]) => (
        <Route key={`${locale}-${source}`} path={source} element={<Navigate to={`/${locale}${target}`} replace />} />
      ))}
      <Route path="*" element={<NotFoundPage />} />
    </Route>
  )

  // Cardora Binder / Cardora Scanner deliberately live outside <MainLayout> —
  // they render their own full-page shell (BinderShell: dark navy chrome,
  // own nav) rather than the marketplace's light Navbar/Footer, since this
  // is meant to read as a distinct sub-application, not another market page.
  const binderComingSoon = (
    <ComingSoonPage titleEl="Το Cardora Binder έρχεται σύντομα." titleEn="Cardora Binder is coming soon." />
  )

  const renderBinderRoutes = (locale) => (
    <>
      <Route path={`/${locale}/cardora-binder`} element={binderComingSoon} />
      <Route path={`/${locale}/cardora-binder/library`} element={binderComingSoon} />
      <Route path={`/${locale}/cardora-binder/alerts`} element={binderComingSoon} />
      <Route path={`/${locale}/cardora-binder/duplicates`} element={binderComingSoon} />
      <Route path={`/${locale}/cardora-binder/games`} element={binderComingSoon} />
      <Route path={`/${locale}/cardora-binder/games/:gameSlug`} element={binderComingSoon} />
      <Route path={`/${locale}/cardora-binder/sets/:setId`} element={binderComingSoon} />
      <Route path={`/${locale}/cardora-scanner`} element={binderComingSoon} />
    </>
  )

  return (
    <Suspense fallback={<RouteFallback />}>
      <Routes>
        {renderBinderRoutes('el')}
        {renderBinderRoutes('en')}
        {renderLocalizedRoutes('el')}
        {renderLocalizedRoutes('en')}
        <Route path="/" element={<LocaleRedirect />} />
        <Route path="*" element={<LocaleRedirect />} />
      </Routes>
    </Suspense>
  )
}

export default AppRoutes
