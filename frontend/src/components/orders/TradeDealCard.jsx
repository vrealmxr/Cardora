import {
  AlertTriangle,
  CheckCircle2,
  CreditCard,
  ShieldAlert,
  Wallet,
} from 'lucide-react'
import { useMemo } from 'react'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { formatCurrency, formatDate } from '@/utils/formatters'
import { getUserDisplayName } from '@/utils/helpers'
import { normalizeTextTree } from '@/utils/textEncoding'

const terminalStatuses = new Set(['settled', 'resolved', 'cancelled'])
const statusToneMap = {
  pending_funding: 'warning',
  funded: 'info',
  settling: 'info',
  settled: 'success',
  resolved: 'success',
  disputed: 'danger',
  cancelled: 'muted',
}

const statusLabelMap = {
  pending_funding: { en: 'Pending funding', el: 'Αναμονή πληρωμών' },
  funded: { en: 'Funded', el: 'Πληρωμένο από 2 πλευρές' },
  settling: { en: 'Settling', el: 'Σε αποδέσμευση' },
  settled: { en: 'Settled', el: 'Ολοκληρώθηκε' },
  resolved: { en: 'Resolved', el: 'Επιλύθηκε' },
  disputed: { en: 'Disputed', el: 'Διαφωνία' },
  cancelled: { en: 'Cancelled', el: 'Ακυρώθηκε' },
}

function TradeDealCard({
  deal,
  currentUserId,
  locale = 'el',
  onPay,
  onRelease,
  onDispute,
  busyAction = '',
  isBusy = false,
}) {
  const isEnglish = locale === 'en'
  const copy = useMemo(
    () =>
      normalizeTextTree(
        isEnglish
          ? {
              deal: 'Trade deal',
              status: 'Status',
              accepted: 'Accepted',
              funded: 'Funded',
              dualRelease: 'Dual release',
              settled: 'Settlement',
              current: 'Current',
              done: 'Done',
              waiting: 'Waiting',
              deposit: 'Deposit each side',
              listingValue: 'Listing value',
              offeredValue: 'Offered value',
              counterparty: 'Counterparty',
              yourPayment: 'Your payment',
              counterpartyPayment: 'Counterparty payment',
              yourRelease: 'Your release',
              ownerNet: 'Owner net',
              proposerNet: 'Proposer net',
              openStripe: 'Open Stripe deposit',
              release: 'Confirm release',
              dispute: 'Open dispute',
              disputedNotice:
                'This trade is currently in dispute. Cardora support handles settlement manually.',
              paid: 'Paid',
              unpaid: 'Unpaid',
              released: 'Released',
              pending: 'Pending',
              roleOwner: 'Owner',
              roleProposer: 'Proposer',
              roleViewer: 'Viewer',
              role: 'Role',
              fundedAt: 'Funded at',
              settledAt: 'Settled at',
              resolving: 'Resolving...',
              paying: 'Opening...',
              releasing: 'Saving...',
              actionRequired: 'Action required',
            }
          : {
              deal: 'Trade deal',
              status: 'Κατάσταση',
              accepted: 'Έγινε αποδοχή',
              funded: 'Έγιναν πληρωμές',
              dualRelease: 'Διπλό release',
              settled: 'Ολοκλήρωση',
              current: 'Τώρα',
              done: 'Έτοιμο',
              waiting: 'Αναμονή',
              deposit: 'Deposit ανά πλευρά',
              listingValue: 'Αξία listing',
              offeredValue: 'Αξία προσφοράς',
              counterparty: 'Άλλος χρήστης',
              yourPayment: 'Η πληρωμή σου',
              counterpartyPayment: 'Πληρωμή άλλου χρήστη',
              yourRelease: 'Το release σου',
              ownerNet: 'Καθαρό owner',
              proposerNet: 'Καθαρό proposer',
              openStripe: 'Άνοιγμα Stripe deposit',
              release: 'Επιβεβαίωση release',
              dispute: 'Άνοιγμα dispute',
              disputedNotice:
                'Το trade είναι σε dispute. Η Cardora το λύνει χειροκίνητα.',
              paid: 'Πληρωμένο',
              unpaid: 'Απλήρωτο',
              released: 'Έγινε',
              pending: 'Σε αναμονή',
              roleOwner: 'Owner',
              roleProposer: 'Proposer',
              roleViewer: 'Viewer',
              role: 'Ρόλος',
              fundedAt: 'Χρηματοδότηση',
              settledAt: 'Ολοκλήρωση',
              resolving: 'Επεξεργασία...',
              paying: 'Άνοιγμα...',
              releasing: 'Αποθήκευση...',
              actionRequired: 'Απαιτείται ενέργεια',
            },
      ),
    [isEnglish],
  )

  const ownerId = Number(deal?.owner_user_id ?? deal?.owner?.id ?? 0)
  const proposerId = Number(deal?.proposer_user_id ?? deal?.proposer?.id ?? 0)
  const me = Number(currentUserId ?? 0)
  const role = ownerId === me ? 'owner' : proposerId === me ? 'proposer' : 'viewer'
  const isOwner = role === 'owner'
  const isProposer = role === 'proposer'
  const isParticipant = isOwner || isProposer

  const statusKey = String(deal?.status ?? '')
  const statusLabel =
    statusLabelMap[statusKey]?.[isEnglish ? 'en' : 'el'] ?? (statusKey || '-')
  const isOwnerPaid = Boolean(deal?.owner_paid_at)
  const isProposerPaid = Boolean(deal?.proposer_paid_at)
  const isFunded = Boolean(deal?.is_funded ?? (isOwnerPaid && isProposerPaid))
  const isOwnerReleased = Boolean(deal?.owner_released_at)
  const isProposerReleased = Boolean(deal?.proposer_released_at)
  const isFullyReleased = Boolean(deal?.is_fully_released ?? (isOwnerReleased && isProposerReleased))
  const isTerminal = terminalStatuses.has(statusKey)

  const myPaid = isOwner ? isOwnerPaid : isProposer ? isProposerPaid : false
  const counterpartyPaid = isOwner ? isProposerPaid : isProposer ? isOwnerPaid : false
  const myReleased = isOwner ? isOwnerReleased : isProposer ? isProposerReleased : false

  const canPay = statusKey === 'pending_funding' && isParticipant && !myPaid
  const canRelease = isFunded && !isTerminal && isParticipant && !myReleased
  const canDispute = ['funded', 'settling'].includes(statusKey) && isParticipant

  const listingTitle =
    deal?.listing?.product?.title ??
    deal?.listing?.title_snapshot ??
    deal?.listing?.title ??
    `Listing #${deal?.listing_id ?? '-'}`

  const counterpartyUser = isOwner ? deal?.proposer : isProposer ? deal?.owner : null

  const timeline = [
    {
      key: 'accepted',
      label: copy.accepted,
      completed: true,
      current: statusKey === 'pending_funding',
    },
    {
      key: 'funded',
      label: copy.funded,
      completed: isFunded,
      current: !isFunded && statusKey === 'pending_funding',
    },
    {
      key: 'release',
      label: copy.dualRelease,
      completed: isFullyReleased,
      current: isFunded && !isFullyReleased && !isTerminal,
    },
    {
      key: 'settled',
      label: copy.settled,
      completed: isTerminal,
      current: ['settling', 'disputed'].includes(statusKey),
    },
  ]

  return (
    <CardSurface
      className={`space-y-5 ${
        canPay || canRelease ? 'border-gold-300/25 bg-gradient-to-br from-gold-300/10 via-white/5 to-white/5' : ''
      }`}
    >
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-xs uppercase tracking-[0.3em] text-gold-100">
            {copy.deal} #{deal?.id}
          </p>
          <h3 className="mt-2 text-xl font-semibold text-white">{listingTitle}</h3>
          <p className="mt-2 text-sm text-mist">
            {copy.role}:{' '}
            {role === 'owner' ? copy.roleOwner : role === 'proposer' ? copy.roleProposer : copy.roleViewer}
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {(canPay || canRelease) && <Badge tone="warning">{copy.actionRequired}</Badge>}
          <Badge tone={statusToneMap[statusKey] ?? 'muted'}>
            {copy.status}: {statusLabel}
          </Badge>
        </div>
      </div>

      <div className="grid gap-3 md:grid-cols-4">
        <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
          <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.deposit}</p>
          <p className="mt-2 text-xl font-semibold text-white">
            {formatCurrency(Number(deal?.deposit_amount ?? 0))}
          </p>
        </div>
        <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
          <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.listingValue}</p>
          <p className="mt-2 text-base font-semibold text-white">
            {formatCurrency(Number(deal?.owner_declared_value ?? 0))}
          </p>
        </div>
        <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
          <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.offeredValue}</p>
          <p className="mt-2 text-base font-semibold text-white">
            {formatCurrency(Number(deal?.proposer_declared_value ?? 0))}
          </p>
        </div>
        <div className="rounded-2xl border border-white/8 bg-white/5 p-4">
          <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{copy.counterparty}</p>
          <p className="mt-2 text-base font-semibold text-white">{getUserDisplayName(counterpartyUser)}</p>
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

        <div className="mt-4 grid gap-3 md:grid-cols-5">
          <div className="rounded-xl border border-white/8 bg-white/5 p-3">
            <p className="text-[10px] uppercase tracking-[0.2em] text-white/45">{copy.yourPayment}</p>
            <p className="mt-1 text-sm font-semibold text-white">{myPaid ? copy.paid : copy.unpaid}</p>
          </div>
          <div className="rounded-xl border border-white/8 bg-white/5 p-3">
            <p className="text-[10px] uppercase tracking-[0.2em] text-white/45">{copy.counterpartyPayment}</p>
            <p className="mt-1 text-sm font-semibold text-white">
              {counterpartyPaid ? copy.paid : copy.unpaid}
            </p>
          </div>
          <div className="rounded-xl border border-white/8 bg-white/5 p-3">
            <p className="text-[10px] uppercase tracking-[0.2em] text-white/45">{copy.yourRelease}</p>
            <p className="mt-1 text-sm font-semibold text-white">{myReleased ? copy.released : copy.pending}</p>
          </div>
          <div className="rounded-xl border border-white/8 bg-white/5 p-3">
            <p className="text-[10px] uppercase tracking-[0.2em] text-white/45">{copy.ownerNet}</p>
            <p className="mt-1 text-sm font-semibold text-white">
              {formatCurrency(Number(deal?.owner_net_amount ?? 0))}
            </p>
          </div>
          <div className="rounded-xl border border-white/8 bg-white/5 p-3">
            <p className="text-[10px] uppercase tracking-[0.2em] text-white/45">{copy.proposerNet}</p>
            <p className="mt-1 text-sm font-semibold text-white">
              {formatCurrency(Number(deal?.proposer_net_amount ?? 0))}
            </p>
          </div>
        </div>

        <div className="mt-3 flex flex-wrap gap-4 text-xs text-mist">
          <span>
            {copy.fundedAt}: {deal?.funded_at ? formatDate(deal.funded_at) : '-'}
          </span>
          <span>
            {copy.settledAt}: {deal?.settled_at ? formatDate(deal.settled_at) : '-'}
          </span>
        </div>
      </div>

      {statusKey === 'disputed' ? (
        <div className="rounded-xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">
          <div className="inline-flex items-center gap-2">
            <ShieldAlert className="h-4 w-4" />
            {copy.disputedNotice}
          </div>
        </div>
      ) : null}

      <div className="flex flex-wrap gap-3">
        {canPay ? (
          <Button size="sm" onClick={() => onPay?.(deal.id)} disabled={isBusy}>
            <CreditCard className="h-4 w-4" />
            {isBusy && busyAction === 'pay' ? copy.paying : copy.openStripe}
          </Button>
        ) : null}
        {canRelease ? (
          <Button size="sm" onClick={() => onRelease?.(deal.id)} disabled={isBusy}>
            <Wallet className="h-4 w-4" />
            {isBusy && busyAction === 'release' ? copy.releasing : copy.release}
          </Button>
        ) : null}
        {canDispute ? (
          <Button size="sm" variant="ghost" onClick={() => onDispute?.(deal.id)} disabled={isBusy}>
            <AlertTriangle className="h-4 w-4" />
            {isBusy && busyAction === 'dispute' ? copy.resolving : copy.dispute}
          </Button>
        ) : null}
      </div>
    </CardSurface>
  )
}

export default TradeDealCard
