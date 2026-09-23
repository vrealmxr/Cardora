import { MessageCircle, RefreshCcw, ShoppingBag, Star, Store, Wallet } from 'lucide-react'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import OrderCard from '@/components/orders/OrderCard'
import TradeDealCard from '@/components/orders/TradeDealCard'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { cardoraService } from '@/services/cardoraService'
import { formatCurrency } from '@/utils/formatters'
import { getOrderRole, getOrderStage } from '@/utils/orderWorkflow'
import { normalizeTextTree } from '@/utils/textEncoding'

const TRADE_TERMINAL_STATUSES = new Set(['settled', 'resolved', 'cancelled'])

const normalizeTab = (tab, fallback = 'buyer') => {
  const normalized = String(tab ?? '').toLowerCase()
  if (['buyer', 'seller', 'trades'].includes(normalized)) {
    return normalized
  }

  return fallback
}

const isAcceptedTradeDeal = (deal) => {
  const requestStatus = String(deal?.trade_request?.status ?? '').toLowerCase()
  const status = String(deal?.status ?? '').toLowerCase()

  if (requestStatus === 'accepted') {
    return true
  }

  return [
    'pending_funding',
    'funded',
    'settling',
    'settled',
    'resolved',
    'disputed',
    'cancelled',
  ].includes(status)
}

function OrdersPage({ initialTab = 'buyer' }) {
  const { currentUser } = useAuth()
  const { locale } = useI18n()
  const { orders } = useMarketplace()
  const [searchParams, setSearchParams] = useSearchParams()

  const isEnglish = locale === 'en'
  const normalizedInitialTab = normalizeTab(initialTab, 'buyer')
  const requestedTab = normalizeTab(searchParams.get('tab') || '', normalizedInitialTab)

  const [activeTab, setActiveTab] = useState(requestedTab)
  const [tradeDeals, setTradeDeals] = useState([])
  const [loadingTradeDeals, setLoadingTradeDeals] = useState(false)
  const [tradeFeedback, setTradeFeedback] = useState('')
  const [tradeFeedbackTone, setTradeFeedbackTone] = useState('info')
  const [tradeAction, setTradeAction] = useState({ dealId: null, type: '' })
  const tradeFeedbackRef = useRef(null)

  const copy = normalizeTextTree(
    isEnglish
      ? {
          eyebrow: 'Orders',
          title: 'Protected order dashboards for buyer and seller',
          description:
            'See exactly what has been paid, what still needs to ship, what is waiting for confirmation and what has already been released.',
          buyer: 'My purchases',
          seller: 'My sales',
          trades: 'My trades',
          actionRequired: 'Action required',
          buyerSummary: 'Purchases',
          sellerSummary: 'Sales',
          heldSummary: 'Held for release',
          tradeSummary: 'Accepted trade deals',
          emptyBuyer: 'You do not have any purchase orders yet.',
          emptySeller: 'You do not have any sales yet.',
          emptyTrades: 'No accepted trade deals yet.',
          inboxButton: 'Open inbox',
          helperTitle: 'Messages and reviews now live inside each order',
          helperBody:
            'Use the message button for shipping, delivery and order questions. When an order is completed, the review action appears in the same card.',
          helperMessage: 'Message from the order',
          helperReview: 'Review after completion',
          helperTradeTitle: 'Trade Studio inside your orders',
          helperTradeBody:
            'Only accepted trade deals appear here. Complete Stripe deposits, confirm release and open disputes directly from this tab.',
          helperTradeFunding: 'Stripe deposit',
          helperTradeRelease: 'Dual release',
          helperTradeDispute: 'Manual dispute review',
          refreshing: 'Refreshing...',
          refreshDeals: 'Refresh trades',
          tradePaymentSuccess: 'Trade deposit payment received successfully.',
          tradePaymentCancelled: 'Trade deposit payment was cancelled.',
          tradeDepositPaid: 'Your deposit is already paid for this trade.',
          tradeReleaseSaved: 'Release confirmation recorded.',
          tradeDisputeOpened: 'Dispute opened. Cardora will review it manually.',
          tradeDisputePrompt: 'Describe the issue for Cardora review:',
          tradeActionFailed: 'Could not complete this trade action right now.',
        }
      : {
          eyebrow: 'Παραγγελίες',
          title: 'Protected dashboards για αγοραστή και πωλητή',
          description:
            'Δες καθαρά τι έχει πληρωθεί, τι πρέπει να σταλεί, τι περιμένει επιβεβαίωση και τι έχει ήδη αποδεσμευτεί.',
          buyer: 'Οι αγορές μου',
          seller: 'Οι πωλήσεις μου',
          trades: 'Τα trades μου',
          actionRequired: 'Απαιτείται ενέργεια',
          buyerSummary: 'Αγορές',
          sellerSummary: 'Πωλήσεις',
          heldSummary: 'Σε hold για αποδέσμευση',
          tradeSummary: 'Αποδεκτά trade deals',
          emptyBuyer: 'Δεν υπάρχουν ακόμη αγορές.',
          emptySeller: 'Δεν υπάρχουν ακόμη πωλήσεις.',
          emptyTrades: 'Δεν υπάρχουν αποδεκτά trade deals ακόμη.',
          inboxButton: 'Άνοιγμα inbox',
          helperTitle: 'Μηνύματα και αξιολογήσεις μέσα σε κάθε παραγγελία',
          helperBody:
            'Χρησιμοποίησε το κουμπί μηνύματος για αποστολή, tracking ή παραλαβή. Μόλις μια παραγγελία ολοκληρωθεί, εμφανίζεται στο ίδιο card και η αξιολόγηση.',
          helperMessage: 'Μήνυμα από την παραγγελία',
          helperReview: 'Αξιολόγηση μετά την ολοκλήρωση',
          helperTradeTitle: 'Trade Studio μέσα στις παραγγελίες',
          helperTradeBody:
            'Εδώ εμφανίζονται μόνο αποδεκτά trade deals. Οι πληρωμές Stripe deposit, τα release και τα disputes γίνονται από αυτό το tab.',
          helperTradeFunding: 'Stripe deposit',
          helperTradeRelease: 'Διπλό release',
          helperTradeDispute: 'Χειροκίνητο dispute review',
          refreshing: 'Ανανέωση...',
          refreshDeals: 'Ανανέωση trades',
          tradePaymentSuccess: 'Το trade deposit πληρώθηκε επιτυχώς.',
          tradePaymentCancelled: 'Το trade deposit ακυρώθηκε.',
          tradeDepositPaid: 'Το deposit σου είναι ήδη πληρωμένο για αυτό το trade.',
          tradeReleaseSaved: 'Η επιβεβαίωση release καταχωρήθηκε.',
          tradeDisputeOpened: 'Άνοιξε dispute. Η Cardora θα το ελέγξει χειροκίνητα.',
          tradeDisputePrompt: 'Περιέγραψε το πρόβλημα για έλεγχο από την Cardora:',
          tradeActionFailed: 'Δεν ήταν δυνατή η ενέργεια trade αυτή τη στιγμή.',
        },
  )

  useEffect(() => {
    setActiveTab(requestedTab)
  }, [requestedTab])

  const switchTab = useCallback(
    (nextTab) => {
      const normalizedTab = normalizeTab(nextTab, normalizedInitialTab)
      setActiveTab(normalizedTab)

      const nextParams = new URLSearchParams(searchParams)
      nextParams.set('tab', normalizedTab)
      if (normalizedTab !== 'trades') {
        nextParams.delete('trade_payment')
        nextParams.delete('trade_deal')
      }
      setSearchParams(nextParams, { replace: true })
    },
    [normalizedInitialTab, searchParams, setSearchParams],
  )

  const buyerOrders = useMemo(
    () => orders.filter((order) => getOrderRole(order, currentUser?.id) === 'buyer'),
    [currentUser?.id, orders],
  )

  const sellerOrders = useMemo(
    () => orders.filter((order) => getOrderRole(order, currentUser?.id) === 'seller'),
    [currentUser?.id, orders],
  )

  const actionRequiredCount = useMemo(
    () =>
      orders.filter((order) => getOrderStage(order, getOrderRole(order, currentUser?.id), locale).actionRequired)
        .length,
    [currentUser?.id, locale, orders],
  )

  const heldForRelease = useMemo(
    () =>
      sellerOrders
        .filter((order) => order.statusKey === 'paid_pending_release')
        .reduce((sum, order) => sum + Number(order.sellerAmount ?? 0), 0),
    [sellerOrders],
  )

  const tradeDealRole = useCallback(
    (deal) => {
      const ownerId = Number(deal?.owner_user_id ?? deal?.owner?.id ?? 0)
      const proposerId = Number(deal?.proposer_user_id ?? deal?.proposer?.id ?? 0)
      const me = Number(currentUser?.id ?? 0)

      if (ownerId === me) return 'owner'
      if (proposerId === me) return 'proposer'
      return 'viewer'
    },
    [currentUser?.id],
  )

  const acceptedTradeDeals = useMemo(
    () => (Array.isArray(tradeDeals) ? tradeDeals.filter((deal) => isAcceptedTradeDeal(deal)) : []),
    [tradeDeals],
  )

  const tradeActionRequiredCount = useMemo(
    () =>
      acceptedTradeDeals.filter((deal) => {
        const role = tradeDealRole(deal)
        const isParticipant = role === 'owner' || role === 'proposer'
        if (!isParticipant) return false

        const status = String(deal?.status ?? '')
        const myPaid = role === 'owner' ? Boolean(deal?.owner_paid_at) : Boolean(deal?.proposer_paid_at)
        const bothPaid = Boolean(deal?.is_funded ?? (deal?.owner_paid_at && deal?.proposer_paid_at))
        const myReleased =
          role === 'owner' ? Boolean(deal?.owner_released_at) : Boolean(deal?.proposer_released_at)

        const canPay = status === 'pending_funding' && !myPaid
        const canRelease = bothPaid && !TRADE_TERMINAL_STATUSES.has(status) && !myReleased

        return canPay || canRelease
      }).length,
    [acceptedTradeDeals, tradeDealRole],
  )

  const tradeHeldAmount = useMemo(
    () =>
      acceptedTradeDeals.reduce((sum, deal) => {
        const role = tradeDealRole(deal)
        if (role === 'viewer') return sum

        const status = String(deal?.status ?? '')
        if (TRADE_TERMINAL_STATUSES.has(status)) return sum

        const myPaid = role === 'owner' ? Boolean(deal?.owner_paid_at) : Boolean(deal?.proposer_paid_at)
        if (!myPaid) return sum

        return sum + Number(deal?.deposit_amount ?? 0)
      }, 0),
    [acceptedTradeDeals, tradeDealRole],
  )

  const loadTradeDeals = useCallback(async () => {
    if (!currentUser?.id) return

    try {
      setLoadingTradeDeals(true)
      const deals = await cardoraService.getTradeDeals()
      setTradeDeals(Array.isArray(deals) ? deals : [])
    } catch (error) {
      setTradeFeedbackTone('danger')
      setTradeFeedback(error?.message || copy.tradeActionFailed)
    } finally {
      setLoadingTradeDeals(false)
    }
  }, [copy.tradeActionFailed, currentUser?.id])

  useEffect(() => {
    if (activeTab !== 'trades') return
    loadTradeDeals()
  }, [activeTab, loadTradeDeals])

  useEffect(() => {
    const paymentStatus = String(searchParams.get('trade_payment') || '').toLowerCase()
    const tradeDealId = Number(searchParams.get('trade_deal') || 0)

    if (!paymentStatus && tradeDealId <= 0) return

    setActiveTab('trades')

    if (paymentStatus === 'success') {
      setTradeFeedbackTone('success')
      setTradeFeedback(copy.tradePaymentSuccess)
    } else if (paymentStatus === 'cancelled') {
      setTradeFeedbackTone('danger')
      setTradeFeedback(copy.tradePaymentCancelled)
    }

    loadTradeDeals()
  }, [copy.tradePaymentCancelled, copy.tradePaymentSuccess, loadTradeDeals, searchParams])

  useEffect(() => {
    if (!tradeFeedback) return
    if (activeTab !== 'trades') return
    if (typeof window === 'undefined') return

    window.requestAnimationFrame(() => {
      tradeFeedbackRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    })
  }, [activeTab, tradeFeedback])

  const handleTradeCheckout = async (tradeDealId) => {
    try {
      setTradeAction({ dealId: Number(tradeDealId), type: 'pay' })
      setTradeFeedback('')
      const response = await cardoraService.createTradeCheckoutSession(tradeDealId)

      if (response?.checkout_url) {
        window.location.href = response.checkout_url
        return
      }

      setTradeFeedbackTone('success')
      setTradeFeedback(copy.tradeDepositPaid)
      await loadTradeDeals()
    } catch (error) {
      setTradeFeedbackTone('danger')
      setTradeFeedback(error?.message || copy.tradeActionFailed)
    } finally {
      setTradeAction({ dealId: null, type: '' })
    }
  }

  const handleTradeRelease = async (tradeDealId) => {
    try {
      setTradeAction({ dealId: Number(tradeDealId), type: 'release' })
      setTradeFeedback('')
      await cardoraService.confirmTradeRelease(tradeDealId)
      setTradeFeedbackTone('success')
      setTradeFeedback(copy.tradeReleaseSaved)
      await loadTradeDeals()
    } catch (error) {
      setTradeFeedbackTone('danger')
      setTradeFeedback(error?.message || copy.tradeActionFailed)
    } finally {
      setTradeAction({ dealId: null, type: '' })
    }
  }

  const handleTradeDispute = async (tradeDealId) => {
    const reason = window.prompt(copy.tradeDisputePrompt)
    if (!reason) return

    try {
      setTradeAction({ dealId: Number(tradeDealId), type: 'dispute' })
      setTradeFeedback('')
      await cardoraService.openTradeDispute(tradeDealId, { reason })
      setTradeFeedbackTone('success')
      setTradeFeedback(copy.tradeDisputeOpened)
      await loadTradeDeals()
    } catch (error) {
      setTradeFeedbackTone('danger')
      setTradeFeedback(error?.message || copy.tradeActionFailed)
    } finally {
      setTradeAction({ dealId: null, type: '' })
    }
  }

  const activeOrders = activeTab === 'seller' ? sellerOrders : buyerOrders

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />

      <div className="grid gap-4 md:grid-cols-4">
        <CardSurface>
          <div className="flex items-center gap-3">
            <ShoppingBag className="h-5 w-5 text-gold-100" />
            <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.buyerSummary}</p>
          </div>
          <p className="mt-4 text-3xl font-semibold text-white">{buyerOrders.length}</p>
        </CardSurface>

        <CardSurface>
          <div className="flex items-center gap-3">
            <Store className="h-5 w-5 text-gold-100" />
            <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.sellerSummary}</p>
          </div>
          <p className="mt-4 text-3xl font-semibold text-white">{sellerOrders.length}</p>
        </CardSurface>

        <CardSurface>
          <div className="flex items-center gap-3">
            <Wallet className="h-5 w-5 text-gold-100" />
            <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.heldSummary}</p>
          </div>
          <p className="mt-4 text-3xl font-semibold text-white">{formatCurrency(heldForRelease)}</p>
          {actionRequiredCount > 0 ? (
            <Badge tone="warning" className="mt-3">
              {copy.actionRequired}: {actionRequiredCount}
            </Badge>
          ) : null}
        </CardSurface>

        <CardSurface>
          <div className="flex items-center gap-3">
            <Star className="h-5 w-5 text-gold-100" />
            <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.tradeSummary}</p>
          </div>
          <p className="mt-4 text-3xl font-semibold text-white">{acceptedTradeDeals.length}</p>
          <p className="mt-2 text-sm text-mist">{formatCurrency(tradeHeldAmount)}</p>
          {tradeActionRequiredCount > 0 ? (
            <Badge tone="warning" className="mt-3">
              {copy.actionRequired}: {tradeActionRequiredCount}
            </Badge>
          ) : null}
        </CardSurface>
      </div>

      <div className="mt-8 flex flex-wrap gap-3">
        <Button as={Link} to="/minymata" variant="secondary">
          <MessageCircle className="h-4 w-4" />
          {copy.inboxButton}
        </Button>

        <button
          type="button"
          onClick={() => switchTab('buyer')}
          className={`rounded-full border px-4 py-2 text-sm font-semibold transition ${
            activeTab === 'buyer'
              ? 'border-[#d8b06a] bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_48%,#c79d62_100%)] text-[#231508] shadow-[0_10px_22px_rgba(199,157,98,0.18)]'
              : 'border-[#ead9b1] bg-white text-slate-700 hover:border-[#d8b06a] hover:text-[#6e4512]'
          }`}
        >
          {copy.buyer}
        </button>
        <button
          type="button"
          onClick={() => switchTab('seller')}
          className={`rounded-full border px-4 py-2 text-sm font-semibold transition ${
            activeTab === 'seller'
              ? 'border-[#d8b06a] bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_48%,#c79d62_100%)] text-[#231508] shadow-[0_10px_22px_rgba(199,157,98,0.18)]'
              : 'border-[#ead9b1] bg-white text-slate-700 hover:border-[#d8b06a] hover:text-[#6e4512]'
          }`}
        >
          {copy.seller}
        </button>
        <button
          type="button"
          onClick={() => switchTab('trades')}
          className={`inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition ${
            activeTab === 'trades'
              ? 'border-[#d8b06a] bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_48%,#c79d62_100%)] text-[#231508] shadow-[0_10px_22px_rgba(199,157,98,0.18)]'
              : 'border-[#ead9b1] bg-white text-slate-700 hover:border-[#d8b06a] hover:text-[#6e4512]'
          }`}
        >
          {copy.trades}
          {tradeActionRequiredCount > 0 ? (
            <span className="rounded-full bg-gold-300 px-2 py-0.5 text-[11px] font-bold text-slate-950">
              {tradeActionRequiredCount}
            </span>
          ) : null}
        </button>
      </div>

      <CardSurface className="mt-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p className="text-lg font-semibold text-white">
              {activeTab === 'trades' ? copy.helperTradeTitle : copy.helperTitle}
            </p>
            <p className="mt-2 max-w-3xl text-sm leading-7 text-mist">
              {activeTab === 'trades' ? copy.helperTradeBody : copy.helperBody}
            </p>
          </div>

          {activeTab === 'trades' ? (
            <div className="flex flex-wrap gap-3">
              <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-white/85">
                <Wallet className="h-4 w-4 text-gold-100" />
                {copy.helperTradeFunding}
              </div>
              <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-white/85">
                <Star className="h-4 w-4 text-gold-100" />
                {copy.helperTradeRelease}
              </div>
              <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-white/85">
                <RefreshCcw className="h-4 w-4 text-gold-100" />
                {copy.helperTradeDispute}
              </div>
            </div>
          ) : (
            <div className="flex flex-wrap gap-3">
              <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-white/85">
                <MessageCircle className="h-4 w-4 text-gold-100" />
                {copy.helperMessage}
              </div>
              <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-white/85">
                <Star className="h-4 w-4 text-gold-100" />
                {copy.helperReview}
              </div>
            </div>
          )}
        </div>
      </CardSurface>

      <div className="mt-8 space-y-5">
        {activeTab === 'trades' ? (
          <>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="text-sm text-mist">
                {loadingTradeDeals ? copy.refreshing : `${acceptedTradeDeals.length} ${copy.trades}`}
              </div>
              <Button variant="ghost" size="sm" onClick={loadTradeDeals} disabled={loadingTradeDeals}>
                <RefreshCcw className="h-4 w-4" />
                {loadingTradeDeals ? copy.refreshing : copy.refreshDeals}
              </Button>
            </div>

            {tradeFeedback ? (
              <div
                ref={tradeFeedbackRef}
                className={`rounded-xl border px-4 py-3 text-sm ${
                  tradeFeedbackTone === 'success'
                    ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-800'
                    : 'border-rose-400/20 bg-rose-500/10 text-rose-800'
                }`}
              >
                {tradeFeedback}
              </div>
            ) : null}

            {acceptedTradeDeals.length ? (
              acceptedTradeDeals.map((deal) => (
                <TradeDealCard
                  key={`trade-deal-${deal?.id ?? deal?.trade_request_id ?? deal?.listing_id ?? 'row'}`}
                  deal={deal}
                  currentUserId={currentUser?.id}
                  locale={locale}
                  onPay={handleTradeCheckout}
                  onRelease={handleTradeRelease}
                  onDispute={handleTradeDispute}
                  busyAction={tradeAction.type}
                  isBusy={Number(tradeAction.dealId ?? 0) === Number(deal?.id ?? 0)}
                />
              ))
            ) : (
              <CardSurface>
                <p className="text-sm text-mist">{copy.emptyTrades}</p>
              </CardSurface>
            )}
          </>
        ) : activeOrders.length ? (
          activeOrders.map((order) => <OrderCard key={order.databaseId ?? order.id} order={order} />)
        ) : (
          <CardSurface>
            <p className="text-sm text-mist">{activeTab === 'seller' ? copy.emptySeller : copy.emptyBuyer}</p>
          </CardSurface>
        )}
      </div>
    </div>
  )
}

export default OrdersPage
