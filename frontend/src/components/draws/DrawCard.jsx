import { Coins, ShieldCheck, Target, Ticket, Trophy, Users } from 'lucide-react'
import { Link } from 'react-router-dom'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { useI18n } from '@/hooks/useI18n'
import { formatCurrency, formatDate, formatNumber } from '@/utils/formatters'
import { normalizeTextTree } from '@/utils/textEncoding'
import { cn } from '@/utils/helpers'

const campaignTone = {
  platform_volume: 'info',
  community_raffle: 'gold',
}

const asTag = (value, maxLength = 38) => {
  const normalized = String(value ?? '').trim()
  if (!normalized) return null

  return normalized.length > maxLength
    ? `${normalized.slice(0, Math.max(maxLength - 3, 1))}...`
    : normalized
}

function DrawCard({
  draw,
  className,
  compact = false,
  onJoin,
  canJoin = false,
  isOwnDraw = false,
  detailsTo,
  showDetails = true,
}) {
  const { locale } = useI18n()
  const isPlatform = draw.campaignType === 'platform_volume'
  const communityDrawSubtitle = !isPlatform
    ? `${formatNumber(draw.targetEntries ?? 0)} x ${formatCurrency(draw.entryPrice ?? 0)}`
    : draw.subtitle
  const visual = draw.visual ?? {}
  const panelGradient = 'from-[#fffefd] via-[#fbf7ef] to-[#f5eddd]'
  const visualLabel = visual.label ?? (isPlatform ? 'Cardora Draw' : 'Collector Draw')
  const galleryImages = Array.isArray(visual.gallery) ? visual.gallery.filter(Boolean) : []
  const coverImage = draw.imageUrl ?? visual.imageUrl ?? galleryImages[0] ?? null
  const hasCoverImage = Boolean(coverImage)
  const detailHref = detailsTo ?? (isPlatform ? '/' : '/kliroseis')
  const metaTags = [
    draw.prizeCategory,
    draw.prizeCondition,
    draw.dispatchWindow,
  ]
    .map((item) => asTag(item))
    .filter(Boolean)
  const ownDrawLabel = locale === 'en' ? 'Your raffle' : 'Δική σου κλήρωση'

  const copy = normalizeTextTree(
    locale === 'en'
      ? {
          cardoraDraw: 'Cardora draw',
          collectorDraw: 'Collector draw',
          verifiedOnly: 'Verified members only',
          rule: 'Rule',
          entryCost: 'Entry price',
          target: 'Target',
          participants: 'Participants',
          targetProgress: 'Target progress',
          entriesProgress: 'Entry progress',
          slots: 'spots',
          prize: 'Prize',
          hostedBy: 'Hosted by',
          maxEntries: 'Up to',
          perUser: 'entries per member',
          closes: 'Closes',
          drawDate: 'Draw',
          buyEntry: 'Add 1 entry to cart',
          details: 'View details',
          of: 'of',
          entry: 'entry',
        }
      : {
          cardoraDraw: 'Κλήρωση Cardora',
          collectorDraw: 'Κλήρωση συλλέκτη',
          verifiedOnly: 'Μόνο για επιβεβαιωμένα μέλη',
          rule: 'Κανόνας',
          entryCost: 'Κόστος θέσης',
          target: 'Στόχος',
          participants: 'Συμμετέχοντες',
          targetProgress: 'Πρόοδος στόχου',
          entriesProgress: 'Πρόοδος συμμετοχών',
          slots: 'θέσεις',
          prize: 'Δώρο',
          hostedBy: 'Διοργανωτής',
          maxEntries: 'Έως',
          perUser: 'συμμετοχές ανά μέλος',
          closes: 'Κλείσιμο',
          drawDate: 'Κλήρωση',
          buyEntry: 'Προσθήκη 1 συμμετοχής στο καλάθι',
          details: 'Δες λεπτομέρειες',
          of: 'από',
          entry: 'συμμετοχή',
        }
  )

  const statItems = [
    {
      icon: isPlatform ? Coins : Ticket,
      label: isPlatform ? copy.rule : copy.entryCost,
      value: isPlatform ? `1€ = ${draw.entriesPerEuro} ${copy.entry}` : formatCurrency(draw.entryPrice),
    },
    {
      icon: Target,
      label: copy.target,
      value: isPlatform
        ? formatCurrency(draw.targetAmount)
        : `${formatNumber(draw.targetEntries ?? 0)} ${copy.slots}`,
    },
    {
      icon: Users,
      label: copy.participants,
      value: formatNumber(draw.participants),
    },
  ]

  return (
    <CardSurface className={cn('group overflow-hidden p-0', className)}>
      <div
        className={cn(
          'relative overflow-hidden rounded-[24px] border-b border-[#eadab7]',
          compact ? 'min-h-[220px]' : 'min-h-[260px]',
        )}
      >
        <div className={cn('absolute inset-0 bg-gradient-to-br opacity-95', panelGradient)} />
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.92),transparent_30%),radial-gradient(circle_at_bottom_left,rgba(243,202,87,0.16),transparent_35%)]" />
        <div className={cn('relative flex h-full flex-col justify-between', compact ? 'p-4' : 'p-5')}>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone={campaignTone[draw.campaignType] ?? 'gold'}>
                {isPlatform ? copy.cardoraDraw : copy.collectorDraw}
              </Badge>
              {draw.requiresVerification ? <Badge tone="warning">{copy.verifiedOnly}</Badge> : null}
            </div>
            <span className="text-[10px] uppercase tracking-[0.28em] text-[#8f7a58]">
              {visualLabel}
            </span>
          </div>

          <div className="space-y-3">
            <div
              className={cn(
                'grid gap-3',
                hasCoverImage
                  ? compact
                    ? 'grid-cols-1 sm:grid-cols-[120px,1fr]'
                    : 'grid-cols-1 md:grid-cols-[160px,1fr]'
                  : 'grid-cols-1',
              )}
            >
              {hasCoverImage ? (
                <div
                  className={cn(
                    'banner-image-glow overflow-hidden rounded-[18px] border border-[#eadab7] bg-white',
                    compact ? 'min-h-[150px]' : 'min-h-[178px]',
                  )}
                >
                  <img
                    src={coverImage}
                    alt={draw.prizeTitle || draw.title}
                    className="h-full w-full object-contain object-center p-2.5"
                    loading="lazy"
                  />
                </div>
              ) : null}
              <div className={cn('rounded-[18px] border border-[#eadab7] bg-white', compact ? 'p-3.5' : 'p-4')}>
                <p className="text-[11px] uppercase tracking-[0.24em] text-[#968565]">{draw.prizeCategory}</p>
                <h3
                  className={cn(
                    'mt-3 text-balance font-display text-ink transition group-hover:text-gold-600',
                    compact ? 'text-[1.8rem] leading-tight' : 'text-3xl',
                  )}
                >
                  {draw.title}
                </h3>
                <p className="mt-2 text-sm leading-6 text-mist">{communityDrawSubtitle}</p>
              </div>
            </div>

            <div className="flex flex-wrap gap-2">
              {metaTags.map((tag, index) => (
                <span
                  key={`${tag}-${index}`}
                  className="inline-flex items-center rounded-full border border-[#eadab7] bg-white px-2.5 py-1 text-[10px] font-semibold tracking-[0.07em] text-[#7c6742]"
                >
                  {tag}
                </span>
              ))}
              <Badge tone="muted">{formatCurrency(draw.prizeValue)}</Badge>
            </div>
          </div>
        </div>
      </div>

      <div className={cn('space-y-4', compact ? 'p-4' : 'p-5')}>
        <div>
          <div className="mb-2 flex items-center justify-between gap-3 text-sm">
            <span className="text-mist">
              {isPlatform ? copy.targetProgress : copy.entriesProgress}
            </span>
            <span className="font-semibold text-gold-700">{draw.progressPercentage}%</span>
          </div>
          <div className="h-2 rounded-full bg-[#efe4cc]">
            <div
              className="h-2 rounded-full bg-gradient-to-r from-gold-300 to-gold-500"
              style={{ width: `${draw.progressPercentage}%` }}
            />
          </div>
          <p className="mt-2 text-xs text-mist">
            {isPlatform
              ? `${formatCurrency(draw.currentAmount)} ${copy.of} ${formatCurrency(draw.targetAmount)}`
              : `${formatNumber(draw.soldEntries ?? 0)} ${copy.of} ${formatNumber(draw.targetEntries ?? 0)} ${copy.slots}`}
          </p>
        </div>

        <div className="grid gap-3 sm:grid-cols-3">
          {statItems.map((item) => {
            const Icon = item.icon

            return (
              <div key={item.label} className="min-w-0 rounded-[18px] border border-[#eadab7] bg-white p-3.5">
                <div className="flex min-w-0 items-start gap-2 text-gold-700">
                  <Icon className="mt-0.5 h-4 w-4 shrink-0" />
                  <p className="min-w-0 text-[11px] leading-4 text-[#968565]">{item.label}</p>
                </div>
                <p className="mt-2 text-sm font-semibold text-ink">{item.value}</p>
              </div>
            )
          })}
        </div>

        <div className="rounded-[20px] border border-[#eadab7] bg-white px-4 py-3.5">
          <div className="flex items-center gap-2 text-gold-700">
            <Trophy className="h-4 w-4" />
            <p className="text-[11px] uppercase tracking-[0.24em] text-[#968565]">{copy.prize}</p>
          </div>
          <p className="mt-2 font-semibold text-ink">{draw.prizeTitle}</p>
          <p className="mt-1 text-sm text-mist">{draw.prizeCondition}</p>
        </div>

        <div className="space-y-2 text-sm text-[#6b7280]">
          <div className="flex items-start gap-2">
            <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-gold-100" />
            <span>{draw.fairnessNote}</span>
          </div>
          {!isPlatform && draw.hostedBy ? (
            <p className="text-mist">
              {copy.hostedBy}: {draw.hostedBy}
              {draw.maxEntriesPerUser ? ` • ${copy.maxEntries} ${formatNumber(draw.maxEntriesPerUser)} ${copy.perUser}` : ''}
            </p>
          ) : null}
          <p className="text-mist">
            {copy.closes}: {formatDate(draw.endsAt)} • {copy.drawDate}: {formatDate(draw.drawAt)}
          </p>
        </div>

        <div className="flex flex-wrap gap-3">
          {canJoin && !isPlatform && draw.status === 'active' && !isOwnDraw ? (
            <Button size="sm" onClick={() => onJoin?.(draw.id)}>
              {copy.buyEntry}
            </Button>
          ) : null}
          {!isPlatform && draw.status === 'active' && isOwnDraw ? (
            <Button size="sm" variant="secondary" disabled>
              {ownDrawLabel}
            </Button>
          ) : null}
          {showDetails ? (
            <Button
              as={Link}
              to={detailHref}
              variant={canJoin && !isPlatform ? 'secondary' : 'primary'}
              size="sm"
            >
              {copy.details}
            </Button>
          ) : null}
        </div>
      </div>
    </CardSurface>
  )
}

export default DrawCard
