import {
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
import { cn, getUserDisplayName } from '@/utils/helpers'

const navClassName = ({ isActive }) =>
  cn(
    'relative px-2.5 py-1.5 text-[13px] font-medium text-[#e4c58f]/82 transition hover:text-[#f8ebcd] hover:[text-shadow:0_0_14px_rgba(228,197,138,0.24)]',
    isActive &&
      'text-[#f8ebcd] after:absolute after:bottom-0 after:left-3 after:right-3 after:h-px after:bg-[#e4c58a] after:shadow-[0_0_14px_rgba(228,197,138,0.42)]',
  )

const iconButtonClassName =
  'relative rounded-full border border-white/10 bg-white/5 p-2 text-[#e4c58f]/86 transition hover:border-[#e7c99a]/72 hover:text-[#f8ebcd] hover:shadow-[0_10px_22px_rgba(199,157,98,0.22)]'

const counterBadgeClassName =
  'absolute -right-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full border border-[#d8b980]/75 bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd4a3_52%,#c79d62_100%)] px-1 text-[10px] font-bold text-[#251608] shadow-[0_6px_16px_rgba(199,157,98,0.34)]'

function Navbar() {
  const { t, locale } = useI18n()
  const { currentUser, isAuthenticated, logout } = useAuth()
  const navigate = useNavigate()
  const {
    cartSummary,
    collectorProfilesDetailed,
    favoriteProductIds,
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
  const unreadNotifications = notifications.filter((item) => !item.read)
  const resolveNotificationRoute = (notification) => {
    const data = notification?.data ?? {}

    if (data.conversation_id) {
      return `/minymata?conversation=${data.conversation_id}`
    }

    if (data.order_id) {
      return `/paraggelies?order=${data.order_id}`
    }

    if (data.trade_deal_id) {
      return `/dashboard-politi/kliroseis?trade_deal=${data.trade_deal_id}`
    }

    if (data.trade_request_id) {
      const requestScope = notification?.type === 'trade_request_received' ? 'received' : 'sent'
      return `/dashboard-politi/kliroseis?trade_request=${data.trade_request_id}&trade_scope=${requestScope}`
    }

    if (data.support_ticket_id) {
      return `/kentro-ypostiriksis?ticket=${data.support_ticket_id}`
    }

    if (data.verification_submission_id) {
      return '/epalithefsi-logariasmou'
    }

    if (data.draw_campaign_id) {
      return '/kliroseis'
    }

    if (data.listing_id) {
      const listing = productsWithSellers.find(
        (item) => Number(item.id) === Number(data.listing_id),
      )

      if (listing?.slug) {
        return `/proion/${listing.slug}`
      }
    }

    if (data.profile_user_id) {
      const profile = collectorProfilesDetailed.find(
        (item) => Number(item.userId) === Number(data.profile_user_id),
      )

      if (profile?.handle) {
        return `/melos/${profile.handle}`
      }

      if (currentUser && Number(currentUser.id) === Number(data.profile_user_id)) {
        return '/profil'
      }
    }

    if (notification?.type === 'welcome' || notification?.type === 'review_received') {
      return '/profil'
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
      <header className="fixed inset-x-0 top-0 z-40 border-b border-white/8 bg-[#081220]/85 backdrop-blur-xl">
        <div className="container">
          <div className="flex min-h-[82px] items-center gap-3 py-2">
            <Link to="/" className="flex shrink-0 items-center">
              <img src="/asset.php?f=logo.png" alt="Cardora" className="h-10 w-auto object-contain sm:h-12" />
            </Link>

            <nav className="hidden items-center gap-1 xl:flex">
              {mainNavigation.map((item) => (
                <NavLink key={item.href} to={item.href} className={navClassName}>
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
                to="/agapimena"
                className={iconButtonClassName}
              >
                <Heart className="h-[18px] w-[18px]" />
                {favoriteProductIds.length ? (
                  <span className={counterBadgeClassName}>
                    {favoriteProductIds.length}
                  </span>
                ) : null}
              </Link>

              <Link
                to="/kalathi"
                className={iconButtonClassName}
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
                    className="flex items-center gap-2.5 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-sm text-white transition hover:border-gold-300/40"
                  >
                    <UserAvatar
                      user={currentUser}
                      size="sm"
                      className="h-8 w-8 text-sm"
                      ringClassName="border-transparent"
                    />
                    <span className="hidden lg:block">{getUserDisplayName(currentUser)}</span>
                    <ChevronDown className="h-4 w-4 text-white/55" />
                  </button>

                  {userOpen ? (
                    <div className="absolute right-0 mt-3 w-64 rounded-[20px] border border-white/10 bg-[#0b1628]/95 p-2.5 shadow-glass backdrop-blur-xl">
                      <div className="rounded-xl border border-white/8 bg-white/5 p-3">
                        <p className="text-sm font-semibold text-white">{getUserDisplayName(currentUser)}</p>
                        <p className="mt-1 text-sm text-mist">{currentUser?.email}</p>
                      </div>
                      <div className="mt-3 space-y-1">
                        <Link
                          to="/profil"
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-white/75 transition hover:bg-white/5 hover:text-white"
                        >
                          <UserRound className="h-4 w-4" />
                          {t('common.profile')}
                        </Link>
                        <Link
                          to="/paraggelies"
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-white/75 transition hover:bg-white/5 hover:text-white"
                        >
                          <ShoppingBag className="h-4 w-4" />
                          {locale === 'en' ? 'Orders' : 'Παραγγελίες'}
                        </Link>
                        <Link
                          to="/dashboard-politi"
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-white/75 transition hover:bg-white/5 hover:text-white"
                        >
                          <LayoutDashboard className="h-4 w-4" />
                          {t('common.sellerDashboard')}
                        </Link>
                        <Link
                          to="/epalithefsi-logariasmou"
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-white/75 transition hover:bg-white/5 hover:text-white"
                        >
                          <ShieldCheck className="h-4 w-4" />
                          {t('common.verification')}
                        </Link>
                        <Link
                          to="/dimiourgia-aggelias"
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl border border-[#e4c58a]/24 bg-[linear-gradient(155deg,rgba(248,235,202,0.1)_0%,rgba(228,197,138,0.14)_38%,rgba(14,28,49,0.84)_100%)] px-3 py-2.5 text-sm text-[#f3dfb4] transition hover:border-[#f0d9aa]/42 hover:bg-[linear-gradient(155deg,rgba(248,235,202,0.14)_0%,rgba(228,197,138,0.18)_38%,rgba(15,29,51,0.88)_100%)] hover:text-[#fff2d6]"
                        >
                          <PlusSquare className="h-4 w-4" />
                          {t('common.newListing')}
                        </Link>
                        <Link
                          to="/dashboard-politi/kliroseis"
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-white/75 transition hover:bg-white/5 hover:text-white"
                        >
                          <Ticket className="h-4 w-4" />
                          {t('common.raffleStudio')}
                        </Link>
                        <Link
                          to="/minymata"
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-white/75 transition hover:bg-white/5 hover:text-white"
                        >
                          <MessageSquareMore className="h-4 w-4" />
                          {t('common.messages')}
                        </Link>
                        <Link
                          to="/rythmiseis-eidopoiiseon"
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-white/75 transition hover:bg-white/5 hover:text-white"
                        >
                          <Bell className="h-4 w-4" />
                          {t('common.notificationSettings')}
                        </Link>
                        <Link
                          to="/rythmiseis-logariasmou"
                          onClick={closeMenus}
                          className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-white/75 transition hover:bg-white/5 hover:text-white"
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
                          className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-rose-100 transition hover:bg-rose-500/10"
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
                    to="/eisodos"
                    className="rounded-xl border border-white/10 bg-white/5 px-3.5 py-2 text-sm font-semibold text-white transition hover:border-gold-300/40 hover:text-gold-100"
                  >
                    {t('common.login')}
                  </Link>
                <Link
                  to="/eggrafi"
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
              >
                <Menu className="h-[18px] w-[18px]" />
              </button>
            </div>
          </div>
        </div>
      </header>

      <Drawer open={mobileOpen} title={t('common.menu')} onClose={() => setMobileOpen(false)}>
        <div className="space-y-6">
          <LanguageToggle />
          <SearchBar compact className="pt-1" />

          <div className="space-y-1">
            {mainNavigation.map((item) => (
              <NavLink
                key={item.href}
                to={item.href}
                onClick={() => setMobileOpen(false)}
                className={({ isActive }) =>
                  cn(
                    'block rounded-xl px-3.5 py-2.5 text-sm transition',
                    isActive
                      ? 'bg-gold-300/12 text-gold-100'
                      : 'text-white/75 hover:bg-white/5 hover:text-white',
                  )
                }
              >
                {t(`nav.${item.key}`)}
              </NavLink>
            ))}
          </div>

          <div className="space-y-2 rounded-[22px] border border-white/10 bg-white/5 p-3.5">
            {isAuthenticated ? (
              <>
                <Link
                  to="/dimiourgia-aggelias"
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl border border-[#e4c58a]/30 bg-[linear-gradient(155deg,rgba(248,235,202,0.14)_0%,rgba(228,197,138,0.2)_38%,rgba(15,29,51,0.86)_100%)] px-3 py-2.5 text-center text-sm font-semibold text-[#f8ebcd] shadow-[0_10px_24px_rgba(199,157,98,0.2)] transition hover:border-[#f0d9aa]/44 hover:bg-[linear-gradient(155deg,rgba(248,235,202,0.18)_0%,rgba(228,197,138,0.24)_38%,rgba(16,31,54,0.9)_100%)]"
                >
                  {t('common.newListing')}
                </Link>
                <Link
                  to="/profil"
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-white/75 transition hover:bg-white/5 hover:text-white"
                >
                  {t('common.userProfile')}
                </Link>
                <Link
                  to="/paraggelies"
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-white/75 transition hover:bg-white/5 hover:text-white"
                >
                  {locale === 'en' ? 'Orders' : 'Παραγγελίες'}
                </Link>
                <Link
                  to="/dashboard-politi"
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-white/75 transition hover:bg-white/5 hover:text-white"
                >
                  {t('common.sellerDashboard')}
                </Link>
                <Link
                  to="/epalithefsi-logariasmou"
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-white/75 transition hover:bg-white/5 hover:text-white"
                >
                  {t('common.verification')}
                </Link>
                <Link
                  to="/rythmiseis-eidopoiiseon"
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-white/75 transition hover:bg-white/5 hover:text-white"
                >
                  {t('common.notificationSettings')}
                </Link>
                <Link
                  to="/rythmiseis-logariasmou"
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-white/75 transition hover:bg-white/5 hover:text-white"
                >
                  {t('common.accountSettings')}
                </Link>
                <Link
                  to="/oi-aggelies-mou"
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-white/75 transition hover:bg-white/5 hover:text-white"
                >
                  {t('common.myListings')}
                </Link>
                <Link
                  to="/dashboard-politi/kliroseis"
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-white/75 transition hover:bg-white/5 hover:text-white"
                >
                  {t('common.raffleStudio')}
                </Link>
                <button
                  type="button"
                  onClick={async () => {
                    setMobileOpen(false)
                    await logout()
                    navigate('/', { replace: true })
                  }}
                  className="block w-full rounded-xl px-3 py-2.5 text-left text-rose-100 transition hover:bg-rose-500/10"
                >
                  {t('common.logout')}
                </button>
              </>
            ) : (
              <>
                <Link
                  to="/eisodos"
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-white/75 transition hover:bg-white/5 hover:text-white"
                >
                  {t('common.login')}
                </Link>
                <Link
                  to="/eggrafi"
                  onClick={() => setMobileOpen(false)}
                  className="block rounded-xl px-3 py-2.5 text-white/75 transition hover:bg-white/5 hover:text-white"
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

