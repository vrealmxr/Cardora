import {
  CheckCircle2,
  CreditCard,
  MessageCircle,
  PackageCheck,
  Shield,
  Star,
  Truck,
} from 'lucide-react'
import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import OrderReviewComposer from '@/components/orders/OrderReviewComposer'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input } from '@/components/ui/Input'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { formatCurrency, formatDate } from '@/utils/formatters'
import { getUserDisplayName } from '@/utils/helpers'
import { buildOrderTimeline, getOrderRole, getOrderStage } from '@/utils/orderWorkflow'
import { normalizeTextTree } from '@/utils/textEncoding'

const stageToneMap = {
  released: 'success',
  refunded: 'danger',
  disputed: 'warning',
  needs_shipping: 'warning',
  awaiting_confirmation: 'warning',
  waiting_for_seller: 'info',
  awaiting_buyer_confirmation: 'info',
  pending_payment: 'muted',
  cancelled: 'muted',
  processing: 'muted',
}

function OrderCard({ order }) {
  const { currentUser } = useAuth()
  const { locale } = useI18n()
  const navigate = useNavigate()
  const {
    users,
    conversations,
    confirmOrderReceived,
    updateOrder,
    startConversationForOrder,
    getMyReviewForOrder,
    saveOrderReview,
  } = useMarketplace()
  const [isConfirming, setIsConfirming] = useState(false)
  const [isSavingTracking, setIsSavingTracking] = useState(false)
  const [isMessaging, setIsMessaging] = useState(false)
  const [isReviewOpen, setIsReviewOpen] = useState(false)
  const [isReviewBusy, setIsReviewBusy] = useState(false)
  const [isReviewLoading, setIsReviewLoading] = useState(false)
  const [hasLoadedReview, setHasLoadedReview] = useState(false)
  const [existingReview, setExistingReview] = useState(null)
  const [trackingNumber, setTrackingNumber] = useState(order?.trackingNumber ?? '')
  const [feedback, setFeedback] = useState('')
  const [feedbackTone, setFeedbackTone] = useState('info')

  const isEnglish = locale === 'en'
  const role = getOrderRole(order, currentUser?.id)
  const stage = normalizeTextTree(getOrderStage(order, role, locale))
  const timeline = normalizeTextTree(buildOrderTimeline(order, locale))
  const seller = users.find((user) => Number(user.id) === Number(order.sellerId)) ?? null
  const buyer = users.find((user) => Number(user.id) === Number(order.buyerId)) ?? null
  const counterparty = role === 'seller' ? buyer : seller
  const canMessage = order?.itemType === 'listing' && Number(order?.productId) > 0
  const canReview = role !== 'viewer' && order?.statusKey === 'released'
  const activeConversation = conversations.find((conversation) => {
    const sameListing = Number(conversation.productId ?? conversation.listing_id) === Number(order?.productId)
    const sameBuyer = Number(conversation.buyerId ?? conversation.buyer_id) === Number(order?.buyerId)
    const sameSeller = Number(conversation.sellerId ?? conversation.seller_id) === Number(order?.sellerId)

    return sameListing && sameBuyer && sameSeller
  })
  const unreadMessageCount = Number(activeConversation?.unreadCount ?? 0)

  const copy = useMemo(
    () =>
      normalizeTextTree(
        isEnglish
          ? {
              order: 'Order',
              total: 'Total paid',
              held: 'Held for seller',
              sellerReceives: 'Seller release amount',
              autoRelease: 'Auto-release',
              tracking: 'Tracking',
              buyer: 'Buyer',
              seller: 'Seller',
              placeTracking: 'Add tracking and mark as shipped',
              saveTracking: 'Save tracking',
              savingTracking: 'Saving...',
              trackingPlaceholder: 'e.g. BOXNOW-123456 or DHL-123456',
              confirmReceived: 'Confirm received',
              confirming: 'Releasing funds...',
              releaseNote:
                'Cardora keeps the protected amount on hold until buyer confirmation or the automatic release window.',
              releasedNote:
                'The protected amount has already been released to the seller Stripe account.',
              shippedAt: 'Shipped',
              placedAt: 'Paid',
              actionRequired: 'Action required',
              openMessages: role === 'seller' ? 'Message the buyer' : 'Message the seller',
              openingMessages: 'Opening conversation...',
              reviewButton: 'Leave a review',
              editReview: 'Edit your review',
              reviewSaved: 'Your review was saved.',
              reviewPrompt:
                role === 'seller'
                  ? 'The sale is complete. Leave a quick review for the buyer.'
                  : 'The order is complete. Leave a quick review for the seller.',
              reviewReady: 'Your review is already saved for this order.',
              receiptConfirmed: 'Receipt confirmed. Funds were released.',
              trackingSaved: 'Tracking saved. The buyer can now follow the order.',
              done: 'Done',
              current: 'Current',
              waiting: 'Waiting',
              emptyValue: '-',
              timelineSeparator: ' | ',
            }
          : {
              order: 'Παραγγελία',
              total: 'Συνολικό πληρωτέο',
              held: 'Σε hold για τον πωλητή',
              sellerReceives: 'Ποσό αποδέσμευσης πωλητή',
              autoRelease: 'Αυτόματη αποδέσμευση',
              tracking: 'Tracking',
              buyer: 'Αγοραστής',
              seller: 'Πωλητής',
              placeTracking: 'Πρόσθεσε tracking και σήμανε ότι στάλθηκε',
              saveTracking: 'Αποθήκευση tracking',
              savingTracking: 'Αποθήκευση...',
              trackingPlaceholder: 'π.χ. BOXNOW-123456 ή DHL-123456',
              confirmReceived: 'Επιβεβαίωση παραλαβής',
              confirming: 'Γίνεται αποδέσμευση...',
              releaseNote:
                'Η Cardora κρατά το προστατευμένο ποσό σε hold μέχρι να επιβεβαιώσει ο αγοραστής ή να λήξει το αυτόματο παράθυρο αποδέσμευσης.',
              releasedNote:
                'Το προστατευμένο ποσό έχει ήδη αποδεσμευτεί προς το Stripe account του πωλητή.',
              shippedAt: 'Στάλθηκε',
              placedAt: 'Πληρώθηκε',
              actionRequired: 'Απαιτείται ενέργεια',
              openMessages: role === 'seller' ? 'Μήνυμα στον αγοραστή' : 'Μήνυμα στον πωλητή',
              openingMessages: 'Ανοίγουμε τη συνομιλία...',
              reviewButton: 'Άφησε αξιολόγηση',
              editReview: 'Επεξεργασία αξιολόγησης',
              reviewSaved: 'Η αξιολόγησή σου αποθηκεύτηκε.',
              reviewPrompt:
                role === 'seller'
                  ? 'Η πώληση ολοκληρώθηκε. Άφησε μια σύντομη αξιολόγηση για τον αγοραστή.'
                  : 'Η παραγγελία ολοκληρώθηκε. Άφησε μια σύντομη αξιολόγηση για τον πωλητή.',
              reviewReady: 'Η αξιολόγησή σου έχει ήδη αποθηκευτεί για αυτή την παραγγελία.',
              receiptConfirmed: 'Η παραλαβή επιβεβαιώθηκε και τα χρήματα αποδεσμεύτηκαν.',
              trackingSaved: 'Το tracking αποθηκεύτηκε. Ο αγοραστής μπορεί πλέον να δει ότι η παραγγελία στάλθηκε.',
              done: 'Έτοιμο',
              current: 'Τώρα',
              waiting: 'Αναμονή',
              emptyValue: '-',
              timelineSeparator: ' | ',
            },
      ),
    [isEnglish, role],
  )

  const handleConfirmReceived = async () => {
    if (!order?.databaseId || isConfirming) return

    try {
      setIsConfirming(true)
      setFeedback('')
      await confirmOrderReceived(order.databaseId)
      setFeedbackTone('success')
      setFeedback(copy.receiptConfirmed)
    } catch (error) {
      setFeedbackTone('danger')
      setFeedback(error.message)
    } finally {
      setIsConfirming(false)
    }
  }

  const handleSaveTracking = async () => {
    if (!order?.databaseId || isSavingTracking || !trackingNumber.trim()) return

    try {
      setIsSavingTracking(true)
      setFeedback('')
      await updateOrder(order.databaseId, {
        tracking_number: trackingNumber.trim(),
        metadata: {
          shipped_at: new Date().toISOString(),
        },
      })
      setFeedbackTone('success')
      setFeedback(copy.trackingSaved)
    } catch (error) {
      setFeedbackTone('danger')
      setFeedback(error.message)
    } finally {
      setIsSavingTracking(false)
    }
  }

  const handleOpenMessages = async () => {
    if (!canMessage || isMessaging) return

    try {
      setIsMessaging(true)
      setFeedback('')
      const conversation = await startConversationForOrder(order)

      navigate(conversation?.id ? `/minymata?conversation=${conversation.id}` : '/minymata')
    } catch (error) {
      setFeedbackTone('danger')
      setFeedback(error.message)
    } finally {
      setIsMessaging(false)
    }
  }

  const handleOpenReview = async () => {
    if (!canReview) return

    setIsReviewOpen(true)

    if (hasLoadedReview) return

    try {
      setIsReviewLoading(true)
      const review = await getMyReviewForOrder(order.databaseId)
      setExistingReview(review)
      setHasLoadedReview(true)
    } catch (error) {
      setFeedbackTone('danger')
      setFeedback(error.message)
    } finally {
      setIsReviewLoading(false)
    }
  }

  const handleSaveReview = async (draft) => {
    try {
      setIsReviewBusy(true)
      setFeedback('')
      const review = await saveOrderReview(order, draft, existingReview?.id ?? null)
      setExistingReview(review)
      setHasLoadedReview(true)
      setFeedbackTone('success')
      setFeedback(copy.reviewSaved)
      setIsReviewOpen(false)
    } catch (error) {
      setFeedbackTone('danger')
      setFeedback(error.message)
    } finally {
      setIsReviewBusy(false)
    }
  }

  return (
    <CardSurface className="space-y-5">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-xs uppercase tracking-[0.32em] text-gold-100">
            {copy.order} {order.id}
          </p>
          <h3 className="mt-2 text-2xl font-semibold text-white">{order.title ?? order.id}</h3>
          <p className="mt-2 text-sm text-mist">
            {copy.placedAt} {formatDate(order.orderedAt)}
            {order.shippedAt
              ? `${copy.timelineSeparator}${copy.shippedAt} ${formatDate(order.shippedAt)}`
              : ''}
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {stage.actionRequired ? <Badge tone="warning">{copy.actionRequired}</Badge> : null}
          <Badge tone={stageToneMap[stage.key] ?? 'muted'}>{stage.label}</Badge>
        </div>
      </div>

      <div className="grid gap-3 md:grid-cols-4">
        <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
          <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.total}</p>
          <p className="mt-2 text-xl font-semibold text-white">
            {formatCurrency(order.totalAmount ?? order.total ?? 0)}
          </p>
        </div>
        <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
          <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.held}</p>
          <p className="mt-2 text-xl font-semibold text-white">
            {formatCurrency(order.sellerAmount ?? 0)}
          </p>
        </div>
        <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
          <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.autoRelease}</p>
          <p className="mt-2 text-sm font-semibold text-white">
            {order.autoReleaseAt ? formatDate(order.autoReleaseAt) : copy.emptyValue}
          </p>
        </div>
        <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
          <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">
            {role === 'seller' ? copy.buyer : copy.seller}
          </p>
          <p className="mt-2 text-sm font-semibold text-white">{getUserDisplayName(counterparty)}</p>
        </div>
      </div>

      <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
        <div className="grid gap-3 md:grid-cols-4">
          {timeline.map((step) => (
            <div
              key={step.key}
              className={`rounded-2xl border px-4 py-3 ${
                step.completed
                  ? 'border-emerald-400/20 bg-emerald-500/10'
                  : step.current
                    ? 'border-gold-300/20 bg-gold-300/10'
                    : 'border-white/8 bg-[#0d1523]'
              }`}
            >
              <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{step.label}</p>
              <div className="mt-2 flex items-center gap-2 text-sm font-semibold text-white">
                {step.completed ? (
                  <CheckCircle2 className="h-4 w-4 text-emerald-200" />
                ) : (
                  <div className="h-2.5 w-2.5 rounded-full bg-white/20" />
                )}
                {step.completed ? copy.done : step.current ? copy.current : copy.waiting}
              </div>
            </div>
          ))}
        </div>
        <p className="mt-4 text-sm leading-7 text-mist">{stage.summary}</p>
      </div>

      <div className="grid gap-4 xl:grid-cols-[1.25fr,0.75fr]">
        <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
          <div className="flex items-center gap-2 text-sm font-semibold text-white">
            <Shield className="h-4 w-4 text-gold-100" />
            {copy.sellerReceives}
          </div>
          <p className="mt-3 text-sm leading-7 text-mist">
            {order.releasedAt ? copy.releasedNote : copy.releaseNote}
          </p>
        </div>

        <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
          <div className="flex items-center gap-2 text-sm font-semibold text-white">
            <Truck className="h-4 w-4 text-gold-100" />
            {copy.tracking}
          </div>
          <p className="mt-3 text-sm font-semibold text-white">
            {order.trackingNumber || copy.emptyValue}
          </p>
        </div>
      </div>

      {canMessage || canReview ? (
        <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              {canReview ? (
                <p className="text-sm leading-7 text-mist">
                  {existingReview ? copy.reviewReady : copy.reviewPrompt}
                </p>
              ) : null}
            </div>

            <div className="flex flex-wrap gap-3">
              {canMessage ? (
                <Button variant="secondary" onClick={handleOpenMessages} disabled={isMessaging}>
                  <MessageCircle className="h-4 w-4" />
                  {isMessaging ? copy.openingMessages : copy.openMessages}
                  {unreadMessageCount > 0 ? (
                    <span className="rounded-full bg-gold-300 px-2 py-0.5 text-[11px] font-bold text-slate-950">
                      {unreadMessageCount}
                    </span>
                  ) : null}
                </Button>
              ) : null}

              {canReview ? (
                <Button variant={existingReview ? 'secondary' : 'primary'} onClick={handleOpenReview}>
                  <Star className="h-4 w-4" />
                  {existingReview ? copy.editReview : copy.reviewButton}
                </Button>
              ) : null}
            </div>
          </div>

          {isReviewOpen ? (
            <div className="mt-4">
              <OrderReviewComposer
                locale={locale}
                role={role}
                initialReview={existingReview}
                isBusy={isReviewBusy}
                isLoading={isReviewLoading}
                onSubmit={handleSaveReview}
                onCancel={() => setIsReviewOpen(false)}
              />
            </div>
          ) : null}
        </div>
      ) : null}

      {feedback ? (
        <div
          className={`rounded-xl border px-4 py-3 text-sm ${
            feedbackTone === 'success'
              ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100'
              : 'border-rose-400/20 bg-rose-500/10 text-rose-100'
          }`}
        >
          {feedback}
        </div>
      ) : null}

      {role === 'seller' && order.canMarkShipped ? (
        <div className="rounded-2xl border border-gold-300/15 bg-gold-300/10 p-4">
          <div className="flex items-center gap-2 text-sm font-semibold text-gold-50">
            <PackageCheck className="h-4 w-4 text-gold-100" />
            {copy.placeTracking}
          </div>
          <div className="mt-4 flex flex-col gap-3 md:flex-row">
            <Input
              value={trackingNumber}
              onChange={(event) => setTrackingNumber(event.target.value)}
              placeholder={copy.trackingPlaceholder}
              className="md:flex-1"
            />
            <Button
              onClick={handleSaveTracking}
              disabled={isSavingTracking || !trackingNumber.trim()}
            >
              {isSavingTracking ? copy.savingTracking : copy.saveTracking}
            </Button>
          </div>
        </div>
      ) : null}

      {role === 'buyer' && order.canConfirmReceived ? (
        <div className="flex justify-start">
          <Button onClick={handleConfirmReceived} disabled={isConfirming}>
            <CreditCard className="h-4 w-4" />
            {isConfirming ? copy.confirming : copy.confirmReceived}
          </Button>
        </div>
      ) : null}
    </CardSurface>
  )
}

export default OrderCard
