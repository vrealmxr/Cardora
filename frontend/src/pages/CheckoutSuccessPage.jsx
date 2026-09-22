import { CheckCircle2, CreditCard, PackageCheck, ShieldCheck } from 'lucide-react'
import { Link, useSearchParams } from 'react-router-dom'
import { useEffect, useMemo, useRef, useState } from 'react'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { usePageLoader } from '@/hooks/usePageLoader'
import { cardoraService } from '@/services/cardoraService'
import { formatCurrency, formatDate } from '@/utils/formatters'
import { getOrderRole, getOrderStage } from '@/utils/orderWorkflow'
import { normalizeTextTree } from '@/utils/textEncoding'

function CheckoutSuccessPage() {
  const { currentUser, isAuthReady } = useAuth()
  const { locale } = useI18n()
  const { orders, refreshBootstrap } = useMarketplace()
  const { withPageLoader } = usePageLoader()
  const [isRefreshing, setIsRefreshing] = useState(true)
  const [searchParams] = useSearchParams()
  const orderNumber = searchParams.get('order')
  const orderId = searchParams.get('order_id')
  const batchId = searchParams.get('batch_id')
  const sessionId = searchParams.get('session_id')
  const syncedKeysRef = useRef(new Set())

  const copy = normalizeTextTree(
    locale === 'en'
      ? {
          eyebrow: 'Payment received',
          title: 'Stripe payment completed',
          description:
            'Your order has been paid successfully. Cardora will keep the funds protected until buyer confirmation or the auto-release window.',
          order: 'Order',
          multipleOrders: (count) => `This checkout created ${count} separate orders — one per item, each with its own seller and shipment.`,
          orders: 'Open buyer dashboard',
          sellerDashboard: 'Open seller dashboard',
          continueShopping: 'Continue shopping',
          syncNotice: 'Cardora is syncing the latest protected-order state in the background.',
          stage: 'Current stage',
          held: 'Held for seller',
          autoRelease: 'Auto-release',
          nextStep: 'What happens next',
          nextStepText:
            'The order is now visible to both buyer and seller. The seller will see that the sale is paid and waiting for shipment, while the buyer can follow the order until confirmation and release.',
        }
      : {
          eyebrow: 'Η πληρωμή ολοκληρώθηκε',
          title: 'Η πληρωμή στο Stripe ολοκληρώθηκε',
          description:
            'Η παραγγελία πληρώθηκε επιτυχώς. Η Cardora κρατά την προστασία της συναλλαγής μέχρι την επιβεβαίωση παραλαβής ή την αυτόματη αποδέσμευση.',
          order: 'Παραγγελία',
          multipleOrders: (count) => `Αυτό το checkout δημιούργησε ${count} ξεχωριστές παραγγελίες — μία ανά αντικείμενο, η καθεμία με τον δικό της πωλητή και αποστολή.`,
          orders: 'Άνοιγμα buyer dashboard',
          sellerDashboard: 'Άνοιγμα seller dashboard',
          continueShopping: 'Συνέχεια αγορών',
          syncNotice:
            'Η Cardora συγχρονίζει στο παρασκήνιο την τελευταία κατάσταση της προστατευμένης παραγγελίας.',
          stage: 'Τρέχον στάδιο',
          held: 'Σε hold για τον πωλητή',
          autoRelease: 'Auto-release',
          nextStep: 'Τι ακολουθεί τώρα',
          nextStepText:
            'Η παραγγελία είναι πλέον ορατή και στον αγοραστή και στον πωλητή. Ο πωλητής θα δει ότι η πώληση πληρώθηκε και περιμένει αποστολή, ενώ ο αγοραστής θα παρακολουθεί την πορεία μέχρι την επιβεβαίωση και την αποδέσμευση.',
        },
  )

  useEffect(() => {
    if (!isAuthReady || !currentUser) {
      return undefined
    }

    const syncKey = `${orderNumber ?? 'latest'}:${orderId ?? 'no-order-id'}:${batchId ?? 'no-batch'}:${sessionId ?? 'no-session'}`

    if (syncedKeysRef.current.has(syncKey)) {
      setIsRefreshing(false)
      return undefined
    }

    syncedKeysRef.current.add(syncKey)

    let mounted = true

    withPageLoader(async () => {
      if (sessionId) {
        try {
          await cardoraService.confirmCheckoutSession(sessionId, orderId)
        } catch (_) {
          // Webhooks remain the primary source of truth; this is a best-effort fallback.
        }
      }

      await refreshBootstrap().catch(() => {})
    }, {
      key: `checkout-success:${syncKey}`,
    }).finally(() => {
      if (mounted) {
        setIsRefreshing(false)
      }
    })

    return () => {
      mounted = false
    }
  }, [currentUser, isAuthReady, orderId, orderNumber, batchId, refreshBootstrap, sessionId, withPageLoader])

  // A single checkout can now produce several orders (one per cart item, possibly across
  // different sellers) — batch_id resolves every order from that one payment, falling back to
  // the older single-order lookups for links that predate batching.
  const batchOrders = useMemo(() => {
    if (!orders.length) return []

    if (batchId) {
      const matched = orders.filter((order) => order.checkoutBatchId === batchId)
      if (matched.length) return matched
    }

    if (orderId) {
      const matchedByDatabaseId =
        orders.find((order) => Number(order.databaseId ?? order.id) === Number(orderId)) ?? null
      if (matchedByDatabaseId) return [matchedByDatabaseId]
    }

    if (orderNumber) {
      const matchedOrder =
        orders.find(
          (order) =>
            String(order.id ?? '') === String(orderNumber) ||
            String(order.orderNumber ?? order.order_number ?? '') === String(orderNumber),
        ) ?? null
      if (matchedOrder) return [matchedOrder]
    }

    const latest =
      [...orders]
        .filter((order) => getOrderRole(order, currentUser?.id) === 'buyer')
        .sort((a, b) => new Date(b.orderedAt ?? 0) - new Date(a.orderedAt ?? 0))[0] ?? null

    return latest ? [latest] : []
  }, [batchId, currentUser?.id, orderId, orderNumber, orders])

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />

      <CardSurface className="max-w-4xl">
        <div className="flex items-start gap-4">
          <CheckCircle2 className="mt-1 h-10 w-10 shrink-0 text-emerald-300" />
          <div>
            {orderNumber ? (
              <p className="text-sm text-mist">
                {copy.order}: <span className="font-semibold text-white">{orderNumber}</span>
              </p>
            ) : null}
            <p className="mt-3 text-sm leading-7 text-mist">{copy.description}</p>
            {!isRefreshing ? (
              <p className="mt-3 text-xs uppercase tracking-[0.24em] text-gold-100">{copy.syncNotice}</p>
            ) : null}
          </div>
        </div>

        {batchOrders.length > 1 ? (
          <p className="mt-6 text-xs uppercase tracking-[0.24em] text-gold-100">
            {copy.multipleOrders(batchOrders.length)}
          </p>
        ) : null}

        {batchOrders.map((order) => {
          const stage = getOrderStage(order, getOrderRole(order, currentUser?.id), locale)

          return (
            <div key={order.databaseId ?? order.id} className="mt-4 rounded-[22px] border border-white/8 p-4">
              <p className="text-sm text-mist">
                {copy.order}: <span className="font-semibold text-white">{order.id}</span>
                {order.title ? <span className="text-mist"> — {order.title}</span> : null}
              </p>

              <div className="mt-4 grid gap-4 md:grid-cols-3">
                <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
                  <div className="flex items-center gap-2 text-gold-100">
                    <ShieldCheck className="h-4 w-4" />
                    <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{copy.stage}</p>
                  </div>
                  <p className="mt-3 text-lg font-semibold text-white">{stage?.label ?? '—'}</p>
                </div>
                <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
                  <div className="flex items-center gap-2 text-gold-100">
                    <CreditCard className="h-4 w-4" />
                    <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{copy.held}</p>
                  </div>
                  <p className="mt-3 text-lg font-semibold text-white">{formatCurrency(order.sellerAmount ?? 0)}</p>
                </div>
                <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
                  <div className="flex items-center gap-2 text-gold-100">
                    <PackageCheck className="h-4 w-4" />
                    <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{copy.autoRelease}</p>
                  </div>
                  <p className="mt-3 text-lg font-semibold text-white">
                    {order.autoReleaseAt ? formatDate(order.autoReleaseAt) : '—'}
                  </p>
                </div>
              </div>

              {stage?.summary ? <p className="mt-3 text-sm leading-7 text-mist">{stage.summary}</p> : null}
            </div>
          )
        })}

        <div className="mt-6 rounded-[22px] border border-gold-300/20 bg-gold-300/10 p-4 text-sm leading-7 text-gold-50">
          <p className="text-[11px] uppercase tracking-[0.24em] text-gold-100">{copy.nextStep}</p>
          <p className="mt-2">{copy.nextStepText}</p>
        </div>

        <div className="mt-8 flex flex-wrap gap-3">
          <Button as={Link} to="/dashboard-agorasti">
            {copy.orders}
          </Button>
          <Button as={Link} to="/dashboard-politi" variant="secondary">
            {copy.sellerDashboard}
          </Button>
          <Button as={Link} to="/" variant="secondary">
            {copy.continueShopping}
          </Button>
        </div>
      </CardSurface>
    </div>
  )
}

export default CheckoutSuccessPage
