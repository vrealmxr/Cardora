import { CheckCircle2, CreditCard, PackageCheck, ShieldCheck } from 'lucide-react'
import { Link, useSearchParams } from 'react-router-dom'
import { useEffect, useMemo, useState } from 'react'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { usePageLoader } from '@/hooks/usePageLoader'
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

  const copy = normalizeTextTree(
    locale === 'en'
      ? {
          eyebrow: 'Payment received',
          title: 'Stripe payment completed',
          description:
            'Your order has been paid successfully. Cardora will keep the funds protected until buyer confirmation or the auto-release window.',
          order: 'Order',
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

    let mounted = true

    withPageLoader(() => refreshBootstrap().catch(() => {}), {
      key: `checkout-success:${orderNumber ?? 'latest'}`,
    }).finally(() => {
      if (mounted) {
        setIsRefreshing(false)
      }
    })

    return () => {
      mounted = false
    }
  }, [currentUser, isAuthReady, orderNumber, refreshBootstrap, withPageLoader])

  const latestOrder = useMemo(() => {
    if (!orders.length) return null

    if (orderNumber) {
      const matchedOrder = orders.find((order) => order.id === orderNumber)
      if (matchedOrder) return matchedOrder
    }

    return [...orders]
      .filter((order) => getOrderRole(order, currentUser?.id) === 'buyer')
      .sort((a, b) => new Date(b.orderedAt ?? 0) - new Date(a.orderedAt ?? 0))[0] ?? null
  }, [currentUser?.id, orderNumber, orders])

  const latestOrderStage = latestOrder
    ? getOrderStage(latestOrder, getOrderRole(latestOrder, currentUser?.id), locale)
    : null

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

        {latestOrder ? (
          <div className="mt-8 grid gap-4 md:grid-cols-3">
            <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
              <div className="flex items-center gap-2 text-gold-100">
                <ShieldCheck className="h-4 w-4" />
                <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{copy.stage}</p>
              </div>
              <p className="mt-3 text-lg font-semibold text-white">{latestOrderStage?.label ?? '—'}</p>
            </div>
            <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
              <div className="flex items-center gap-2 text-gold-100">
                <CreditCard className="h-4 w-4" />
                <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{copy.held}</p>
              </div>
              <p className="mt-3 text-lg font-semibold text-white">{formatCurrency(latestOrder.sellerAmount ?? 0)}</p>
            </div>
            <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
              <div className="flex items-center gap-2 text-gold-100">
                <PackageCheck className="h-4 w-4" />
                <p className="text-[11px] uppercase tracking-[0.22em] text-white/45">{copy.autoRelease}</p>
              </div>
              <p className="mt-3 text-lg font-semibold text-white">
                {latestOrder.autoReleaseAt ? formatDate(latestOrder.autoReleaseAt) : '—'}
              </p>
            </div>
          </div>
        ) : null}

        <div className="mt-6 rounded-[22px] border border-gold-300/20 bg-gold-300/10 p-4 text-sm leading-7 text-gold-50">
          <p className="text-[11px] uppercase tracking-[0.24em] text-gold-100">{copy.nextStep}</p>
          <p className="mt-2">{copy.nextStepText}</p>
          {latestOrderStage?.summary ? <p className="mt-2 text-white/85">{latestOrderStage.summary}</p> : null}
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
