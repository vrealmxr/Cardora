import {
  Album,
  Bell,
  ChevronDown,
  Heart,
  KeyRound,
  LayoutDashboard,
  LogOut,
  Menu,
  MessageSquareMore,
  PlusSquare,
  ShieldCheck,
  ShoppingBag,
  ShoppingCart,
  Ticket,
  UserRound,
} from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, useNavigate } from 'react-router-dom'
import SearchBar from '@/components/catalog/SearchBar'
import UserAvatar from '@/components/people/UserAvatar'
import Drawer from '@/components/ui/Drawer'
import LanguageToggle from '@/components/ui/LanguageToggle'
import { mainNavigation } from '@/data/siteNavigation'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { cn, getCollectorProfileRoute, getUserDisplayName, localizePath } from '@/utils/helpers'

const navClassName = ({ isActive }) =>
  cn(
    'relative px-2.5 py-1.5 text-[13px] font-medium text-slate-700 transition hover:text-[#9d6a17]',
    isActive &&
      'text-[#9d6a17] after:absolute after:bottom-0 after:left-3 after:right-3 after:h-px after:bg-[#d8a84e] after:shadow-[0_0_10px_rgba(216,168,78,0.22)]',
  )

const iconButtonClassName =
  'relative rounded-full border border-[#ead9b1] bg-white p-2 text-[#9d6a17] transition hover:border-[#d8b06a] hover:text-[#7a4d10] hover:shadow-[0_10px_22px_rgba(199,157,98,0.16)]'

const counterBadgeClassName =
  'absolute -right-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full border border-[#d8b980]/75 bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd4a3_52%,#c79d62_100%)] px-1 text-[10px] font-bold text-[#251608] shadow-[0_6px_16px_rgba(199,157,98,0.34)]'

// Deliberately distinct from the rest of the account menu — Cardora Binder will
// grow into its own sub-application, so it gets a premium dark-gold treatment
// (same brand palette, inverted) instead of blending in with the plain links.
const binderMenuItemClassName =
  'group relative flex w-full items-center gap-3 overflow-hidden rounded-xl border border-[#d8b980]/40 bg-[linear-gradient(135deg,#1c1204_0%,#3a2708_45%,#6b4a15_100%)] px-3 py-2.5 text-left text-sm font-semibold text-[#ffedc2] shadow-[0_10px_24px_rgba(120,80,20,0.3)] transition hover:-translate-y-0.5 hover:shadow-[0_14px_30px_rgba(120,80,20,0.42)]'

function BinderMenuItemContent({ badgeLabel }) {
  return (
    <>
      <span className="pointer-events-none absolute inset-0 -translate-x-full bg-[linear-gradient(115deg,transparent_30%,rgba(255,232,180,0.35)_50%,transparent_70%)] transition-transform duration-700 ease-out group-hover:translate-x-full" />
      <Album className="relative h-4 w-4 shrink-0 text-[#f3d385]" />
      <span className="relative flex-1">Cardora Binder</span>
      <span className="relative rounded-full border border-[#f3d385]/40 bg-white/10 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-[#f3d385]">
        {badgeLabel}
      </span>
    </>
  )
}

function Navbar() {
  const { t, locale } = useI18n()
  const { currentUser, isAuthenticated, logout } = useAuth()
  const navigate = useNavigate()
  const {
    cartSummary,
    collectorProfilesDetailed,
    favoriteProductIds,
    marketplaceAccess,
    notifications,
    notificationsUnread,
    markNotificationRead,
    productsWithSellers,
  } = useMarketplace()
  const [mobileOpen, setMobileOpen] = useState(false)
  const [userOpen, setUserOpen] = useState(false)
  const [notificationsOpen, setNotificationsOpen] = useState(false)
  const userMenuRef = useRef(null)
  const notificationsMenuRef = useRef(null)
  const sellerShippingRequirement = marketplaceAccess?.seller_shipping_origin
  const showActivationBanner = Boolean(isAuthenticated && marketplaceAccess && !marketplaceAccess.is_marketplace_ready)
  const unreadNotifications = notifications.filter((item) => !item.read)
  const activationBannerCopy =
    locale === 'en'
      ? {
          title: 'Action required: complete your account details before using Cardora fully.',
          description:
            sellerShippingRequirement?.ready === false
              ? 'Your private shipping details stay private and secure, but they must be completed before listings can stay visible and parcel shipping can be processed safely.'
              : 'You still have required account steps open. Complete them now so marketplace actions can unlock properly.',
          profile: 'Complete shipping details',
          verification: 'Verification center',
          dashboard: 'Stripe setup',
        }
      : {
          title: 'Απαιτείται ενέργεια: συμπλήρωσε τα στοιχεία του λογαριασμού σου για να χρησιμοποιήσεις πλήρως την Cardora.',
          description:
            sellerShippingRequirement?.ready === false
              ? 'Τα ιδιωτικά στοιχεία αποστολής σου παραμένουν private και ασφαλή, αλλά πρέπει να συμπληρωθούν πριν μπορούν να παραμένουν ορατές οι αγγελίες σου και να εκτελείται σωστά η αποστολή δεμάτων.'
              : 'Υπάρχουν ακόμη υποχρεωτικά βήματα λογαριασμού ανοιχτά. Ολοκλήρωσέ τα τώρα για να ξεκλειδώσουν σωστά οι marketplace ενέργειες.',
          profile: 'Στοιχεία αποστολής',
          verification: 'Verification center',
          dashboard: 'Stripe setup',
        }
  const localized = (path) => localizePath(path, locale)
  const iconLabels =
    locale === 'en'
      ? {
          favorites: 'Open favorites',
          cart: 'Open cart',
          notifications: 'Open notifications',
          account: 'Open account menu',
          menu: 'Open menu',
        }
      : {
          favorites: 'Άνοιγμα αγαπημένων',
          cart: 'Άνοιγμα καλαθιού',
          notifications: 'Άνοιγμα ειδοποιήσεων',
          account: 'Άνοιγμα μενού λογαριασμού',
          menu: 'Άνοιγμα μενού',
        }

  const resolveNotificationRoute = (notification) => {
    const data = notification?.data ?? {}

    if (data.conversation_id) {
      return `${localized('/minymata')}?conversation=${data.conversation_id}`
    }

    if (data.order_id) {
      return `${localized('/paraggelies')}?order=${data.order_id}`
    }

    if (data.trade_deal_id) {
      return `${localized('/dashboard-politi/kliroseis')}?trade_deal=${data.trade_deal_id}`
    }

    if (data.trade_request_id) {
      const requestScope = notification?.type === 'trade_request_received' ? 'received' : 'sent'
      return `${localized('/dashboard-politi/kliroseis')}?trade_request=${data.trade_request_id}&trade_scope=${requestScope}`
    }

    if (data.support_ticket_id) {
      return `${localized('/kentro-ypostiriksis')}?ticket=${data.support_ticket_id}`
    }

    if (data.verification_submission_id) {
      return localized('/epalithefsi-logariasmou')
    }

    if (data.draw_campaign_id) {
      return localized('/kliroseis')
    }

    if (data.listing_id) {
      const listing = productsWithSellers.find(
        (item) => Number(item.id) === Number(data.listing_id),
      )

      if (listing?.slug) {
        return localized(`/proion/${listing.slug}`)
      }
    }

    if (data.profile_user_id) {
      const profile = collectorProfilesDetailed.find(
        (item) => Number(item.userId) === Number(data.profile_user_id),
      )

      if (profile?.handle) {
        return getCollectorProfileRoute(profile.handle, locale)
      }

      if (currentUser && Number(currentUser.id) === Number(data.profile_user_id)) {
        return localized('/profil')
      }
    }

    if (notification?.type === 'welcome' || notification?.type === 'review_received') {
      return localized('/profil')
    }

    return null
  }

  const handleNotificationClick = async (notification) => {
    closeMenus()
    await markNotificationRead(notification.id)

    const target = resolveNotificationRoute(notification)
    if (target) {
      navigate(target)
    }
  }

  const closeMenus = () => {
    setUserOpen(false)
    setNotificationsOpen(false)
  }

  useEffect(() => {
    const handlePointerDown = (event) => {
      const target = event.target

      if (notificationsMenuRef.current && !notificationsMenuRef.current.contains(target)) {
        setNotificationsOpen(false)
      }

      if (userMenuRef.current && !userMenuRef.current.contains(target)) {
        setUserOpen(false)
      }
    }

    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        setUserOpen(false)
        setNotificationsOpen(false)
      }
    }

    document.addEventListener('mousedown', handlePointerDown)
    document.addEventListener('keydown', handleKeyDown)

    return () => {
      document.removeEventListener('mousedown', handlePointerDown)
      document.removeEventListener('keydown', handleKeyDown)
    }
  }, [])

  return (
    <>
      <header className="fixed inset-x-0 top-0 z-40 border-b border-[#ecdcb9] bg-[rgba(255,255,255,0.9)] backdrop-blur-xl">
        <div className="container">
          <div className="flex min-h-[82px] items-center gap-3 py-2">
            <Link to={localized('/')} className="flex shrink-0 items-center">
              <img
                src="/asset.php?f=logo-20260716-compact.png"
                alt="Cardora"
                width="420"
                height="150"
                className="h-10 w-auto object-contain sm:h-12"
              />
            </Link>

            <nav className="hidden items-center gap-1 xl:flex">
              {mainNavigation.map((item) => (
                <NavLink
                  key={item.href}
                  to={localized(item.href)}
                  end={item.href === '/'}
                  className={navClassName}
                >
                  {t(`nav.${item.key}`)}
                </NavLink>
              ))}
            </nav>

            <div className="hidden flex-1 xl:block">
              <SearchBar compact />
            </div>

            <div className="ml-auto flex items-center gap-2">
              <div className="hidden lg:block">
                <LanguageToggle compact />
              </div>

              <Link
                to={localized('/agapimena')}
                className={iconButtonClassName}
                aria-label={iconLabels.favorites}
                title={iconLabels.favorites}
              >
                <Heart className="h-[18px] w-[18px]" />
                {favoriteProductIds.length ? (
                  <span className={counterBadgeClassName}>
                    {favoriteProductIds.length}
                  </span>
                ) : null}
              </Link>

              <Link
                to={localized('/kalathi')}
                className={iconButtonClassName}
                aria-label={iconLabels.cart}
                title={iconLabels.cart}
              >
                <ShoppingCart className="h-[18px] w-[18px]" />
                {cartSummary.totalQuantity ? (
                  <span className={counterBadgeClassName}>
                    {cartSummary.totalQuantity}
                  </span>
                ) : null}
              </Link>

              <div ref={notificationsMenuRef} className="relative">
                <button
                  type="button"
                  onClick={() => {
                    setNotificationsOpen((value) => !value)
                    setUserOpen(false)
                  }}
                  className={iconButtonClassName}
                  aria-label={iconLabels.notifications}
                  aria-expanded={notificationsOpen}
                  aria-haspopup="menu"
                  title={iconLabels.notifications}
                >
                  <Bell className="h-[18px] w-[18px]" />
                  {notificationsUnread ? (
                    <span className={cn(counterBadgeClassName, 'w-[18px]')}>
                      {notificationsUnread}
                    </span>
                  ) : null}
                </button>

                {notificationsOpen ? (
                  <div className="absolute right-0 mt-3 w-[300px] rounded-[20px] border border-white/10 bg-[#0b1628]/95 p-2.5 shadow-glass backdrop-blur-xl">
                    <div className="mb-2 flex items-center justify-between px-2">
                      <h3 className="font-display text-[1.65rem] text-white">{t('common.notifications')}</h3>
                      <span className="text-xs uppercase tracking-[0.3em] text-gold-100">{t('common.live')}</span>
                    </div>
                    <div className="max-h-80 space-y-2 overflow-y-auto pr-1">
                      {unreadNotifications.length ? (
                        unreadNotifications.map((item) => (
                          <button
                            key={item.id}
                            type="button"
                            onClick={() => void handleNotificationClick(item)}
                            className="w-full rounded-xl border border-white/8 bg-white/5 p-2.5 text-left transition hover:border-gold-300/30 hover:bg-white/8"
                          >
                            <p className="text-sm font-semibold text-white">{item.title}</p>
                            <p className="mt-1 text-xs leading-6 text-mist">{item.text}</p>
                          </button>
                        ))
                      ) : (
                        <div className="rounded-xl border border-dashed border-white/10 bg-white/5 px-3 py-4 text-sm text-mist">
                          {locale === 'en'
                            ? 'No new notifications right now.'
                            : 'Î”ÎµÎ½ Ï…Ï€Î¬ÏÏ‡Î¿Ï…Î½ Î½Î­ÎµÏ‚ ÎµÎ¹Î´Î¿Ï€Î¿Î¹Î®ÏƒÎµÎ¹Ï‚ Î±Ï…Ï„Î® Ï„Î· ÏƒÏ„Î¹Î³Î¼Î®.'}
                        </div>
                      )}
                    </div>
                  </div>
                ) : null}
              </div>

              {isAuthenticated ? (
                <div ref={userMenuRef} className="relative hidden sm:block">
                  <button
                    type="button"
                    onClick={() => {
                      setUserOpen((value) => !value)
                      setNotificationsOpen(false)
                    }}
                    className="flex items-center gap-2.5 rounded-full border border-[#ead9b1] bg-white px-3 py-1.5 text-sm text-ink transition hover:border-gold-300/40 hover:shadow-[0_10px_22px_rgba(199,157,98,0.12)]"
                    aria-label={iconLabels.account}
                    aria-expanded={userOpen}
                    aria-haspopup="menu"
                    title={iconLabels.account}
                  >
                    <UserAvatar
                      user={currentUser}
                      size="sm"
                      className="h-8 w-8 text-sm"
                      ringClassName="border-transparent"
                    />
                    <span className="hidden lg:block">{getUserDisplayName(currentUser)}</span>
                    <ChevronDown className="h-4 w-4 text-mist" />
                  </button>

                  {userOpen ? (
                    <div className="absolute right-0 mt-3 w-64 rounded-[20px] border border-[#ead9b1] bg-[rgba(255,255,255,0.97)] p-2.5 shadow-[0_20px_44px_rgba(168,139,84,0.18)] backdrop-blur-xl">
                      <div className="rounded-xl border border-[#eedebc] bg-[linear-gradient(155deg,rgba(255,252,245,0.98)_0%,rgba(249,241,222,0.94)_100%)] p-3">
                        <p className="text-sm font-semibold text-ink">{getUserDisplayName(currentUser)}</p>
                        <p className="mt-1 text-sm text-mist">{currentUser?.email}</p>
                      </div>
                      <div className="mt-3 space-y-1">
                        <Link
                          to={localized('/cardora-binder')}
                          onClick={closeMenus}
                          title="Cardora Binder"
                          className={cn(binderMenuItemClassName, 'mb-2')}
                        >
                          <BinderMenuItemContent badgeLabel={locale === 'en' ? 'Soon' : 'Σύντομα'} />
                        </Link>
                        <Link
                          to={localized('/profil')}
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 transition hover:bg-[#fff8ec] hover:text-[#6e4512]"
                        >
                          <UserRound className="h-4 w-4" />
                          {t('common.profile')}
                        </Link>
                        <Link
                          to={localized('/paraggelies')}
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 transition hover:bg-[#fff8ec] hover:text-[#6e4512]"
                        >
                          <ShoppingBag className="h-4 w-4" />
                          {locale === 'en' ? 'Orders' : 'Παραγγελίες'}
                        </Link>
                        <Link
                          to={localized('/dashboard-politi')}
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 transition hover:bg-[#fff8ec] hover:text-[#6e4512]"
                        >
                          <LayoutDashboard className="h-4 w-4" />
                          {t('common.sellerDashboard')}
                        </Link>
                        <Link
                          to={localized('/epalithefsi-logariasmou')}
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 transition hover:bg-[#fff8ec] hover:text-[#6e4512]"
                        >
                          <ShieldCheck className="h-4 w-4" />
                          {t('common.verification')}
                        </Link>
                        <Link
                          to={localized('/dimiourgia-aggelias')}
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl border border-[#d8b06a]/55 bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_48%,#c79d62_100%)] px-3 py-2.5 text-sm font-semibold text-[#231508] shadow-[0_10px_24px_rgba(199,157,98,0.24)] transition hover:-translate-y-0.5 hover:border-[#c99b52] hover:shadow-[0_14px_28px_rgba(199,157,98,0.3)]"
                        >
                          <PlusSquare className="h-4 w-4" />
                          {t('common.newListing')}
                        </Link>
                        <Link
                          to={localized('/dashboard-politi/kliroseis')}
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 transition hover:bg-[#fff8ec] hover:text-[#6e4512]"
                        >
                          <Ticket className="h-4 w-4" />
                          {t('common.raffleStudio')}
                        </Link>
                        <Link
                          to={localized('/minymata')}
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 transition hover:bg-[#fff8ec] hover:text-[#6e4512]"
                        >
                          <MessageSquareMore className="h-4 w-4" />
                          {t('common.messages')}
                        </Link>
                        <Link
                          to={localized('/rythmiseis-eidopoiiseon')}
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 transition hover:bg-[#fff8ec] hover:text-[#6e4512]"
                        >
                          <Bell className="h-4 w-4" />
                          {t('common.notificationSettings')}
                        </Link>
                        <Link
                          to={localized('/rythmiseis-logariasmou')}
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-700 transition hover:bg-[#fff8ec] hover:text-[#6e4512]"
                        >
                          <KeyRound className="h-4 w-4" />
                          {t('common.accountSettings')}
                        </Link>
                        <button
                          type="button"
                          onClick={() => {
                            closeMenus()
                            void logout()
                          }}
                          className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-rose-700 transition hover:bg-rose-50 hover:text-rose-800"
                        >
                          <LogOut className="h-4 w-4" />
                          {t('common.logout')}
                        </button>
                      </div>
                    </div>
                  ) : null}
                </div>
              ) : (
                <div className="hidden items-center gap-2 sm:flex">
                  <Link
                    to={localized('/eisodos')}
                    className="rounded-xl border border-[#ead9b1] bg-white px-3.5 py-2 text-sm font-semibold text-ink transition hover:border-gold-300/40 hover:text-gold-700"
                  >
                    {t('common.login')}
                  </Link>
                <Link
                  to={localized('/eggrafi')}
                  className="rounded-xl border border-[#d7b57b]/70 bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_48%,#c79d62_100%)] px-3.5 py-2 text-sm font-semibold text-[#231508] shadow-[0_10px_26px_rgba(199,157,98,0.36)] transition hover:-translate-y-0.5 hover:shadow-[0_14px_32px_rgba(199,157,98,0.42)]"
                >
                  {t('common.register')}
                </Link>
                </div>
              )}

              <button
                type="button"
                onClick={() => setMobileOpen(true)}
                className={cn(iconButtonClassName, 'xl:hidden')}
                aria-label={iconLabels.menu}
                title={iconLabels.menu}
              >
                <Menu className="h-[18px] w-[18px]" />
              </button>
            </div>
          </div>
        </div>
        {showActivationBanner ? (
          <div className="border-t border-amber-300/18 bg-[linear-gradient(180deg,rgba(120,71,20,0.34)_0%,rgba(33,18,5,0.88)_100%)]">
            <div className="container">
              <div className="flex flex-col gap-3 py-3 lg:flex-row lg:items-center lg:justify-between">
                <div className="max-w-4xl">
                  <p className="text-sm font-semibold text-[#ffe7b3]">{activationBannerCopy.title}</p>
                  <p className="mt-1 text-xs leading-6 text-[#f6e6bf]/86">{activationBannerCopy.description}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Link
                    to={localized('/profil')}
                    className="rounded-xl border border-[#f2d39d]/34 bg-white/8 px-3 py-2 text-xs font-semibold text-[#fff0cf] transition hover:bg-white/12"
                  >
                    {activationBannerCopy.profile}
                  </Link>
                  <Link
                    to={localized('/epalithefsi-logariasmou')}
                    className="rounded-xl border border-[#f2d39d]/24 bg-transparent px-3 py-2 text-xs font-semibold text-[#f0d6a1] transition hover:bg-white/8"
                  >
                    {activationBannerCopy.verification}
                  </Link>
                  <Link
                    to={localized('/dashboard-politi')}
                    className="rounded-xl border border-[#f2d39d]/24 bg-transparent px-3 py-2 text-xs font-semibold text-[#f0d6a1] transition hover:bg-white/8"
                  >
                    {activationBannerCopy.dashboard}
                  </Link>
                </div>
              </div>
            </div>
          </div>
        ) : null}
      </header>

      <Drawer open={mobileOpen} title={t('common.menu')} onClose={() => setMobileOpen(false)}>
        <div className="space-y-6">
          <LanguageToggle />
          <SearchBar compact className="pt-1" />

          <div className="space-y-1">
            {mainNavigation.map((item) => (
              <NavLink
                key={item.href}
                to={localized(item.href)}
                end={item.href === '/'}
                onClick={() => setMobileOpen(false)}
                className={({ isActive }) =>
                  cn(
                    'block rounded-xl px-3.5 py-2.5 text-sm transition',
                    isActive
                      ? 'bg-gold-300/10 text-gold-700'
                      : 'text-slate-700 hover:bg-[#fff8ea] hover:text-gold-700',
                  )
                }
              >
                {t(`nav.${item.key}`)}
              </NavLink>
            ))}
          </div>

          <div className="space-y-2 rounded-[22px] border border-[#ead7ae] bg-white p-3.5">
            {isAuthenticated ? (
              <>
                <Link
                  to={localized('/cardora-binder')}
                  onClick={() => setMobileOpen(false)}
                  title="Cardora Binder"
                  className={binderMenuItemClassName}
                >
                  <BinderMenuItemContent badgeLabel={locale === 'en' ? 'Soon' : 'Σύντομα'} />
                </Link>
                <Link
                  to={localized('/dimiourgia-aggelias')}
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl border border-[#d7b57b]/70 bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_48%,#c79d62_100%)] px-3 py-2.5 text-center text-sm font-semibold text-[#231508] shadow-[0_10px_26px_rgba(199,157,98,0.2)] transition hover:-translate-y-0.5"
                >
                  {t('common.newListing')}
                </Link>
                <Link
                  to={localized('/profil')}
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-slate-700 transition hover:bg-[#fff8ea] hover:text-gold-700"
                >
                  {t('common.userProfile')}
                </Link>
                <Link
                  to={localized('/paraggelies')}
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-slate-700 transition hover:bg-[#fff8ea] hover:text-gold-700"
                >
                  {locale === 'en' ? 'Orders' : 'Παραγγελίες'}
                </Link>
                <Link
                  to={localized('/dashboard-politi')}
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-slate-700 transition hover:bg-[#fff8ea] hover:text-gold-700"
                >
                  {t('common.sellerDashboard')}
                </Link>
                <Link
                  to={localized('/epalithefsi-logariasmou')}
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-slate-700 transition hover:bg-[#fff8ea] hover:text-gold-700"
                >
                  {t('common.verification')}
                </Link>
                <Link
                  to={localized('/rythmiseis-eidopoiiseon')}
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-slate-700 transition hover:bg-[#fff8ea] hover:text-gold-700"
                >
                  {t('common.notificationSettings')}
                </Link>
                <Link
                  to={localized('/rythmiseis-logariasmou')}
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-slate-700 transition hover:bg-[#fff8ea] hover:text-gold-700"
                >
                  {t('common.accountSettings')}
                </Link>
                <Link
                  to={localized('/oi-aggelies-mou')}
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-slate-700 transition hover:bg-[#fff8ea] hover:text-gold-700"
                >
                  {t('common.myListings')}
                </Link>
                <Link
                  to={localized('/dashboard-politi/kliroseis')}
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-slate-700 transition hover:bg-[#fff8ea] hover:text-gold-700"
                >
                  {t('common.raffleStudio')}
                </Link>
                <button
                  type="button"
                  onClick={async () => {
                    setMobileOpen(false)
                    await logout()
                    navigate(localized('/'), { replace: true })
                  }}
                  className="block w-full rounded-xl px-3 py-2.5 text-left text-rose-100 transition hover:bg-rose-500/10"
                >
                  {t('common.logout')}
                </button>
              </>
            ) : (
              <>
                <Link
                  to={localized('/eisodos')}
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-slate-700 transition hover:bg-[#fff8ea] hover:text-gold-700"
                >
                  {t('common.login')}
                </Link>
                <Link
                  to={localized('/eggrafi')}
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-slate-700 transition hover:bg-[#fff8ea] hover:text-gold-700"
                >
                  {t('common.register')}
                </Link>
              </>
            )}
          </div>
        </div>
      </Drawer>
    </>
  )
}

export default Navbar
