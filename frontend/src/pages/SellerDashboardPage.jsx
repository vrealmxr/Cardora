import { CreditCard, ExternalLink, PackageCheck, ShieldCheck, Wallet } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import OrderCard from '@/components/orders/OrderCard'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { usePageLoader } from '@/hooks/usePageLoader'
import { formatCurrency } from '@/utils/formatters'
import { getOrderRole, getOrderStage } from '@/utils/orderWorkflow'
import { normalizeTextTree } from '@/utils/textEncoding'

function SellerDashboardPage() {
  const { currentUser, isAuthReady } = useAuth()
  const { locale } = useI18n()
  const { withPageLoader } = usePageLoader()
  const {
    orders,
    myListings,
    marketplaceAccess,
    getSellerConnectAccount,
    startSellerOnboarding,
    openSellerStripeDashboard,
    getSellerBalanceSummary,
    getSellerPayoutHistory,
  } = useMarketplace()
  const [searchParams] = useSearchParams()

  const [stripeAccount, setStripeAccount] = useState(null)
  const [balanceSummary, setBalanceSummary] = useState(null)
  const [payoutHistory, setPayoutHistory] = useState([])
  const [feedback, setFeedback] = useState('')
  const [isBusy, setIsBusy] = useState(false)
  const [isLoading, setIsLoading] = useState(true)

  const isEnglish = locale === 'en'
  const copy = normalizeTextTree(
    isEnglish
      ? {
          eyebrow: 'Seller dashboard',
          title: 'Sales, protected holds and Stripe release flow',
          description:
            'See what has been paid, what still needs shipping, what is waiting for buyer confirmation and which amounts remain on hold before release.',
          connectTitle: 'Stripe connected account',
          connectMissing: 'Create your Stripe connected account',
          connectPending: 'Complete Stripe setup',
          connectReady: 'Stripe account ready',
          verificationTitle: 'Marketplace activation checklist',
          verificationText:
            'Buying and selling stay locked until identity, address, IBAN and Stripe Connect are all ready. Sellers also need complete private shipping details before listings can stay visible.',
          openVerification: 'Open verification center',
          connectButton: 'Create Stripe Connected Account',
          connectButtonPending: 'Complete Stripe setup now',
          openStripe: 'Open Stripe dashboard',
          openOrders: 'Open orders',
          pendingBalance: 'Held balance',
          availableBalance: 'Released balance',
          paidOutBalance: 'Paid out',
          actionOrders: 'Sales that need action now',
          recentSales: 'Recent sales',
          payouts: 'Recent Stripe payouts',
          noActionOrders: 'No sales require action right now.',
          noSales: 'No sales yet.',
          noPayouts: 'No Stripe payouts recorded yet.',
          listings: 'Active listings',
          holdNoticeTitle: 'Escrow hold',
          holdNotice:
            'After a buyer pays, the seller amount stays on hold here until the order ships and the buyer confirms safe receipt, or the auto-release window closes.',
          needsShipping: 'Need shipping',
          waitingBuyer: 'Waiting for buyer',
          releasedSales: 'Released sales',
          connectSuccess: 'Stripe returned successfully. Your account status was refreshed.',
          connectReturnPending:
            'Stripe onboarding returned, but some steps are still pending before payouts can be enabled.',
        }
      : {
          eyebrow: 'Seller dashboard',
          title: 'Πωλήσεις, holds και αποδεσμεύσεις μέσω Stripe',
          description:
            'Δες τι έχει πληρωθεί, τι πρέπει να σταλεί, τι περιμένει επιβεβαίωση αγοραστή και ποια ποσά κρατιούνται σε hold πριν την αποδέσμευση.',
          connectTitle: 'Stripe connected account',
          connectMissing: 'Δημιούργησε Stripe connected account',
          connectPending: 'Ολοκλήρωσε το Stripe setup',
          connectReady: 'Το Stripe account είναι έτοιμο',
          verificationTitle: 'Checklist ενεργοποίησης marketplace',
          verificationText:
            'Αγορές και πωλήσεις μένουν κλειδωμένες μέχρι να ολοκληρωθούν ταυτότητα, διεύθυνση, IBAN και Stripe Connect. Οι πωλητές χρειάζονται επίσης πλήρη ιδιωτικά στοιχεία αποστολής για να παραμένουν ορατές οι αγγελίες τους.',
          openVerification: 'Άνοιγμα verification center',
          connectButton: 'Δημιουργία Stripe Connected Account',
          connectButtonPending: 'Ολοκλήρωσε τώρα το Stripe setup',
          openStripe: 'Άνοιγμα Stripe dashboard',
          openOrders: 'Άνοιγμα παραγγελιών',
          pendingBalance: 'Balance σε hold',
          availableBalance: 'Αποδεσμευμένο balance',
          paidOutBalance: 'Έχει πληρωθεί',
          actionOrders: 'Πωλήσεις που θέλουν ενέργεια τώρα',
          recentSales: 'Πρόσφατες πωλήσεις',
          payouts: 'Πρόσφατα Stripe payouts',
          noActionOrders: 'Δεν υπάρχουν πωλήσεις που χρειάζονται ενέργεια αυτή τη στιγμή.',
          noSales: 'Δεν υπάρχουν ακόμη πωλήσεις.',
          noPayouts: 'Δεν υπάρχουν ακόμη καταγεγραμμένα Stripe payouts.',
          listings: 'Ενεργές αγγελίες',
          holdNoticeTitle: 'Escrow hold',
          holdNotice:
            'Αφού πληρώσει ο αγοραστής, το ποσό του πωλητή κρατιέται εδώ σε hold μέχρι να σταλεί η παραγγελία και να επιβεβαιωθεί η ασφαλής παραλαβή, ή να λήξει το auto-release window.',
          needsShipping: 'Πρέπει να σταλεί',
          waitingBuyer: 'Αναμένει αγοραστή',
          releasedSales: 'Αποδεσμευμένες πωλήσεις',
          connectSuccess: 'Η επιστροφή από το Stripe ολοκληρώθηκε και το status συγχρονίστηκε.',
          connectReturnPending:
            'Η επιστροφή από το Stripe έγινε, αλλά απομένουν βήματα πριν ενεργοποιηθούν πλήρως τα payouts.',
        },
  )

  useEffect(() => {
    if (!isAuthReady || !currentUser) return undefined

    let mounted = true

    const loadDashboard = async () => {
      try {
        const [account, balance, payouts] = await withPageLoader(
          () =>
            Promise.all([
              getSellerConnectAccount(),
              getSellerBalanceSummary(),
              getSellerPayoutHistory(),
            ]),
          { key: `seller-dashboard:${currentUser.id}` },
        )

        if (!mounted) return
        setStripeAccount(account)
        setBalanceSummary(balance)
        setPayoutHistory(Array.isArray(payouts) ? payouts : [])
      } catch (error) {
        if (!mounted) return
        setFeedback(error.message)
      } finally {
        if (mounted) setIsLoading(false)
      }
    }

    loadDashboard()

    return () => {
      mounted = false
    }
  }, [currentUser?.id, getSellerBalanceSummary, getSellerConnectAccount, getSellerPayoutHistory, isAuthReady, withPageLoader])

  const sellerOrders = useMemo(
    () => orders.filter((order) => getOrderRole(order, currentUser?.id) === 'seller'),
    [currentUser?.id, orders],
  )

  const actionableOrders = useMemo(
    () => sellerOrders.filter((order) => getOrderStage(order, 'seller', locale).actionRequired),
    [locale, sellerOrders],
  )

  const stageSummary = useMemo(() => {
    const stageCounts = sellerOrders.reduce(
      (summary, order) => {
        const stageKey = getOrderStage(order, 'seller', locale).key

        if (stageKey === 'needs_shipping') summary.needsShipping += 1
        if (stageKey === 'awaiting_buyer_confirmation') summary.waitingBuyer += 1
        if (stageKey === 'released') summary.released += 1

        return summary
      },
      { needsShipping: 0, waitingBuyer: 0, released: 0 },
    )

    return stageCounts
  }, [locale, sellerOrders])

  const stripeRequirement = marketplaceAccess?.requirements?.find((item) => item.key === 'stripe_connect')
  const stripeReady = Boolean(stripeRequirement?.ready || stripeAccount?.is_fully_onboarded)
  const stripeReturned = searchParams.get('stripe_connect') === 'success'
  const stripeReturnReady = searchParams.get('ready') === '1'

  const handleStartOnboarding = async () => {
    try {
      setIsBusy(true)
      setFeedback('')
      const response = await startSellerOnboarding()
      if (response?.onboarding_url) {
        window.location.assign(response.onboarding_url)
      }
    } catch (error) {
      setFeedback(error.message)
    } finally {
      setIsBusy(false)
    }
  }

  const handleOpenStripeDashboard = async () => {
    try {
      setIsBusy(true)
      setFeedback('')
      const response = await openSellerStripeDashboard()
      if (response?.url) {
        window.open(response.url, '_blank', 'noopener,noreferrer')
      }
    } catch (error) {
      setFeedback(error.message)
    } finally {
      setIsBusy(false)
    }
  }

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />

      {stripeReturned ? (
        <CardSurface className={`mb-6 ${stripeReturnReady ? 'border-emerald-400/20 bg-emerald-500/10' : 'border-amber-400/20 bg-amber-500/10'}`}>
          <p className={`text-sm font-semibold ${stripeReturnReady ? 'text-emerald-800' : 'text-amber-800'}`}>
            {stripeReturnReady ? copy.connectSuccess : copy.connectReturnPending}
          </p>
        </CardSurface>
      ) : null}

      <div className="grid gap-6 xl:grid-cols-[1.1fr,0.9fr]">
        <CardSurface className="border-gold-300/20 bg-gold-300/10">
          <div className="flex items-start justify-between gap-4">
            <div>
              <div className="flex items-center gap-3">
                <ShieldCheck className="h-5 w-5 text-gold-100" />
                <p className="text-[11px] uppercase tracking-[0.3em] text-gold-100">{copy.verificationTitle}</p>
              </div>
              <h2 className="mt-4 text-3xl font-semibold text-white">
                {marketplaceAccess?.is_marketplace_ready
                  ? (isEnglish ? 'Marketplace access is fully unlocked' : 'Το marketplace έχει ξεκλειδώσει πλήρως')
                  : (isEnglish ? 'Complete every requirement before trading' : 'Ολοκλήρωσε όλα τα requirements πριν από αγορές ή πωλήσεις')}
              </h2>
              <p className="mt-3 max-w-3xl text-sm leading-7 text-gold-50">{copy.verificationText}</p>
            </div>
            <Badge tone={marketplaceAccess?.is_marketplace_ready ? 'success' : 'warning'}>
              {marketplaceAccess?.completion_percentage ?? 0}%
            </Badge>
          </div>

          <div className="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            {(marketplaceAccess?.requirements ?? []).map((requirement) => (
              <div key={requirement.key} className="rounded-2xl border border-white/10 bg-[#0d1523] p-4">
                <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{requirement.label}</p>
                <p className="mt-2 text-sm font-semibold text-white">{requirement.status_label}</p>
                <p className="mt-2 text-xs leading-6 text-mist">{requirement.description}</p>
              </div>
            ))}
          </div>

          <div className="mt-6 flex flex-wrap gap-3">
            <Button as={Link} to="/epalithefsi-logariasmou" variant="secondary">
              {copy.openVerification}
            </Button>
            <Button as={Link} to="/oi-aggelies-mou" variant="secondary">
              {isEnglish ? 'My listings' : 'Οι αγγελίες μου'}
            </Button>
            <Button as={Link} to="/paraggelies" variant="ghost">
              {copy.openOrders}
            </Button>
            {!stripeReady ? (
              <Button onClick={handleStartOnboarding} disabled={isBusy}>
                {stripeAccount?.stripe_account_id ? copy.connectButtonPending : copy.connectButton}
              </Button>
            ) : (
              <Button onClick={handleOpenStripeDashboard} disabled={isBusy}>
                <ExternalLink className="h-4 w-4" />
                {copy.openStripe}
              </Button>
            )}
          </div>
        </CardSurface>

        <CardSurface className={!stripeReady ? 'border-gold-300/25 bg-gradient-to-br from-gold-300/14 via-gold-300/10 to-white/5' : ''}>
          <div className="flex items-center gap-3">
            <CreditCard className="h-5 w-5 text-gold-100" />
            <p className="text-[11px] uppercase tracking-[0.3em] text-gold-100">{copy.connectTitle}</p>
          </div>
          <h3 className="mt-4 text-2xl font-semibold text-white">
            {!stripeAccount?.stripe_account_id
              ? copy.connectMissing
              : stripeReady
                ? copy.connectReady
                : copy.connectPending}
          </h3>
          {stripeAccount?.stripe_account_id ? (
            <p className="mt-3 text-sm text-mist">{stripeAccount.stripe_account_id}</p>
          ) : null}
          {feedback ? (
            <div className="mt-4 rounded-xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
              {feedback}
            </div>
          ) : null}
          {!stripeReady ? (
            <button
              type="button"
              onClick={handleStartOnboarding}
              disabled={isBusy}
              className="mt-6 flex w-full items-center justify-center rounded-[20px] border border-gold-300/30 bg-gradient-to-r from-gold-300 to-gold-500 px-5 py-4 text-base font-semibold text-slate-950 shadow-gold transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-70"
            >
              {stripeAccount?.stripe_account_id ? copy.connectButtonPending : copy.connectButton}
            </button>
          ) : null}
          <div className="mt-6 grid gap-3 md:grid-cols-2">
            <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
              <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{copy.pendingBalance}</p>
              <p className="mt-2 text-2xl font-semibold text-white">{formatCurrency(balanceSummary?.pending_amount ?? 0)}</p>
            </div>
            <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
              <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{copy.availableBalance}</p>
              <p className="mt-2 text-2xl font-semibold text-white">{formatCurrency(balanceSummary?.available_amount ?? 0)}</p>
            </div>
            <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
              <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{copy.paidOutBalance}</p>
              <p className="mt-2 text-2xl font-semibold text-white">{formatCurrency(balanceSummary?.paid_out_amount ?? 0)}</p>
            </div>
            <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
              <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{copy.listings}</p>
              <p className="mt-2 text-2xl font-semibold text-white">{myListings.length}</p>
            </div>
          </div>
          <div className="mt-5 rounded-[22px] border border-gold-300/20 bg-gold-300/10 p-4 text-sm leading-7 text-gold-50">
            <p className="text-[11px] uppercase tracking-[0.24em] text-gold-100">{copy.holdNoticeTitle}</p>
            <p className="mt-2">{copy.holdNotice}</p>
          </div>
        </CardSurface>
      </div>

      <div className="mt-10 grid gap-4 md:grid-cols-3">
        <CardSurface>
          <div className="flex items-center gap-3">
            <PackageCheck className="h-5 w-5 text-gold-100" />
            <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.needsShipping}</p>
          </div>
          <p className="mt-4 text-3xl font-semibold text-white">{stageSummary.needsShipping}</p>
        </CardSurface>
        <CardSurface>
          <div className="flex items-center gap-3">
            <Wallet className="h-5 w-5 text-gold-100" />
            <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.waitingBuyer}</p>
          </div>
          <p className="mt-4 text-3xl font-semibold text-white">{stageSummary.waitingBuyer}</p>
        </CardSurface>
        <CardSurface>
          <div className="flex items-center gap-3">
            <ShieldCheck className="h-5 w-5 text-gold-100" />
            <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.releasedSales}</p>
          </div>
          <p className="mt-4 text-3xl font-semibold text-white">{stageSummary.released}</p>
        </CardSurface>
      </div>

      <section className="mt-10 space-y-5">
        <div className="flex items-center justify-between gap-3">
          <h3 className="font-display text-3xl text-white">{copy.actionOrders}</h3>
          {actionableOrders.length ? <Badge tone="warning">{actionableOrders.length}</Badge> : null}
        </div>
        {actionableOrders.length ? (
          actionableOrders.map((order) => <OrderCard key={order.databaseId ?? order.id} order={order} />)
        ) : (
          <CardSurface>
            <p className="text-sm text-mist">{copy.noActionOrders}</p>
          </CardSurface>
        )}
      </section>

      <div className="mt-10 grid gap-8 xl:grid-cols-[1.15fr,0.85fr]">
        <section className="space-y-5">
          <h3 className="font-display text-3xl text-white">{copy.recentSales}</h3>
          {sellerOrders.length ? (
            sellerOrders.slice(0, 4).map((order) => <OrderCard key={`recent-${order.databaseId ?? order.id}`} order={order} />)
          ) : (
            <CardSurface>
              <p className="text-sm text-mist">{copy.noSales}</p>
            </CardSurface>
          )}
        </section>

        <section>
          <h3 className="font-display text-3xl text-white">{copy.payouts}</h3>
          <div className="mt-5 space-y-3">
            {isLoading ? (
              <CardSurface>
                <p className="text-sm text-mist">{isEnglish ? 'Loading Stripe data...' : 'Φόρτωση στοιχείων Stripe...'}</p>
              </CardSurface>
            ) : payoutHistory.length ? (
              payoutHistory.slice(0, 6).map((payout) => (
                <CardSurface key={payout.id}>
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-white">{formatCurrency(Number(payout.amount ?? 0))}</p>
                      <p className="mt-1 text-sm text-mist">{payout.status}</p>
                    </div>
                    <p className="text-xs text-white/45">{payout.processed_at ? new Date(payout.processed_at).toLocaleDateString(locale === 'en' ? 'en-GB' : 'el-GR') : '—'}</p>
                  </div>
                </CardSurface>
              ))
            ) : (
              <CardSurface>
                <p className="text-sm text-mist">{copy.noPayouts}</p>
              </CardSurface>
            )}
          </div>
        </section>
      </div>
    </div>
  )
}

export default SellerDashboardPage
