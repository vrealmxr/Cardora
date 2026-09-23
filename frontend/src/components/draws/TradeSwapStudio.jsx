import { ArrowRightLeft, GripVertical, ShieldCheck, Sparkles, UserStar, X } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import UserAvatar from '@/components/people/UserAvatar'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input } from '@/components/ui/Input'
import { formatCurrency } from '@/utils/formatters'
import { normalizeTextTree } from '@/utils/textEncoding'

const listingIdOf = (item) => Number(item?.listingId ?? item?.databaseId ?? item?.id ?? 0)
const listingMediaOf = (item) => (Array.isArray(item?.media) ? item.media.filter((entry) => entry?.url) : [])
const listingImageOf = (item) =>
  listingMediaOf(item)[0]?.url ??
  item?.visual?.imageUrl ??
  item?.imageUrl ??
  item?.image_url ??
  null
const listingRarityOf = (item) => String(item?.rarity ?? '').trim() || 'Trade'
const listingConditionOf = (item) => String(item?.condition ?? '').trim() || 'Raw'
const listingSellerNameOf = (item) =>
  item?.seller?.displayName ?? item?.seller?.name ?? item?.sellerName ?? '-'

function TradeSwapStudio({
  locale = 'el',
  myListings = [],
  marketListings = [],
  submitting = false,
  onSubmit,
  pendingAdd = null,
}) {
  const isEnglish = locale === 'en'
  const copy = useMemo(
    () =>
      normalizeTextTree(
        isEnglish
          ? {
              title: 'Trade Studio',
              subtitle:
                'Choose cards from both sides, build your proposal, and send it when you are ready.',
              yourPool: 'Your cards',
              marketPool: 'Cards from other collectors',
              yourBasket: 'You offer',
              targetBasket: 'You request',
              emptyMine: 'Drag your cards here',
              emptyTarget: 'Drag target cards here',
              ownerOnly: 'All target cards must be from the same user.',
              requestMessage: 'Message for the collector',
              requestPlaceholder: 'Write a short note about condition, shipping, or anything important...',
              terms:
                'I understand this swap is completed only after release from both sides and includes a 5% fee per side on successful completion.',
              send: 'Send swap proposal',
              depositPreview: 'Estimated hold deposit each side',
              offeredTotal: 'Your cards total',
              targetTotal: 'Target cards total',
              diffToCover: 'Difference to cover',
              chooseMine: 'Select at least one of your cards.',
              chooseTarget: 'Select at least one target card.',
              acceptTerms: 'Please accept the swap terms to continue.',
              noMine: 'No active trade cards found in your listings.',
              noMarket: 'No target trade cards found right now.',
              owner: 'Collector',
              rating: 'Rating',
              remove: 'Remove',
              studioBadge: 'Trade Studio',
              swapArena: 'Swap area',
              arenaHint:
                'Add cards on both sides and check values before sending.',
              addTip: 'Tip: you can also add cards with a click.',
              poolCount: 'cards',
              picked: 'in proposal',
              valueHigherRequest: 'You are asking for higher value cards.',
              valueHigherOffer: 'You are offering higher value cards.',
              valueBalanced: 'Card values look balanced.',
            }
          : {
              title: 'Studio Ανταλλαγής',
              subtitle:
                'Διάλεξε κάρτες και από τις δύο πλευρές, φτιάξε την πρότασή σου και στείλε την όταν είσαι έτοιμος.',
              yourPool: 'Οι κάρτες σου',
              marketPool: 'Κάρτες άλλων συλλεκτών',
              yourBasket: 'Τι δίνεις',
              targetBasket: 'Τι ζητάς',
              emptyMine: 'Σύρε εδώ τις δικές σου κάρτες',
              emptyTarget: 'Σύρε εδώ τις κάρτες που θέλεις',
              ownerOnly: 'Διάλεξε κάρτες από τον ίδιο χρήστη.',
              requestMessage: 'Μήνυμα προς τον συλλέκτη',
              requestPlaceholder: 'Γράψε ένα σύντομο μήνυμα για κατάσταση, αποστολή ή ό,τι άλλο χρειάζεται...',
              terms:
                'Καταλαβαίνω ότι η ανταλλαγή ολοκληρώνεται μόνο με release και από τις δύο πλευρές και ότι υπάρχει χρέωση 5% ανά πλευρά στην επιτυχημένη ολοκλήρωση.',
              send: 'Αποστολή πρότασης',
              depositPreview: 'Εκτιμώμενη εγγύηση ανά πλευρά',
              offeredTotal: 'Σύνολο καρτών που δίνεις',
              targetTotal: 'Σύνολο καρτών που ζητάς',
              diffToCover: 'Διαφορά προς κάλυψη',
              chooseMine: 'Διάλεξε τουλάχιστον μία δική σου κάρτα.',
              chooseTarget: 'Διάλεξε τουλάχιστον μία κάρτα στόχου.',
              acceptTerms: 'Πρέπει να αποδεχτείς τους όρους ανταλλαγής.',
              noMine: 'Δεν βρέθηκαν ενεργές κάρτες για ανταλλαγή στις αγγελίες σου.',
              noMarket: 'Δεν βρέθηκαν διαθέσιμες κάρτες στόχου αυτή τη στιγμή.',
              owner: 'Συλλέκτης',
              rating: 'Βαθμολογία',
              remove: 'Αφαίρεση',
              studioBadge: 'Studio Ανταλλαγής',
              swapArena: 'Χώρος ανταλλαγής',
              arenaHint:
                'Πρόσθεσε κάρτες και στις δύο πλευρές και έλεγξε τις αξίες πριν την αποστολή.',
              addTip: 'Tip: μπορείς να προσθέτεις κάρτες και με κλικ.',
              poolCount: 'κάρτες',
              picked: 'στην πρόταση',
              valueHigherRequest: 'Η αξία που ζητάς είναι μεγαλύτερη.',
              valueHigherOffer: 'Η αξία που προσφέρεις είναι μεγαλύτερη.',
              valueBalanced: 'Οι αξίες φαίνονται ισορροπημένες.',
            },
      ),
    [isEnglish],
  )

  const [selectedMineIds, setSelectedMineIds] = useState([])
  const [selectedTargetIds, setSelectedTargetIds] = useState([])
  const [draggingId, setDraggingId] = useState(0)
  const [dragZone, setDragZone] = useState('')
  const [requestMessage, setRequestMessage] = useState('')
  const [termsAccepted, setTermsAccepted] = useState(false)
  const [localError, setLocalError] = useState('')

  const myListingsById = useMemo(
    () => new Map(myListings.map((item) => [listingIdOf(item), item])),
    [myListings],
  )
  const marketListingsById = useMemo(
    () => new Map(marketListings.map((item) => [listingIdOf(item), item])),
    [marketListings],
  )

  const selectedMine = useMemo(
    () => selectedMineIds.map((id) => myListingsById.get(id)).filter(Boolean),
    [myListingsById, selectedMineIds],
  )
  const selectedTarget = useMemo(
    () => selectedTargetIds.map((id) => marketListingsById.get(id)).filter(Boolean),
    [marketListingsById, selectedTargetIds],
  )

  const targetOwnerId = Number(selectedTarget[0]?.sellerId ?? 0)
  const targetOwnerName =
    selectedTarget[0]?.seller?.displayName ??
    selectedTarget[0]?.seller?.name ??
    selectedTarget[0]?.sellerName ??
    '-'
  const targetOwnerRating = Number(selectedTarget[0]?.sellerRating ?? 0)

  const mineTotal = useMemo(
    () => selectedMine.reduce((sum, item) => sum + Number(item?.price ?? 0), 0),
    [selectedMine],
  )
  const targetTotal = useMemo(
    () => selectedTarget.reduce((sum, item) => sum + Number(item?.price ?? 0), 0),
    [selectedTarget],
  )
  const depositPreview = Math.max(mineTotal, targetTotal)
  const valueDelta = targetTotal - mineTotal

  const selectedMineSet = useMemo(() => new Set(selectedMineIds), [selectedMineIds])
  const selectedTargetSet = useMemo(() => new Set(selectedTargetIds), [selectedTargetIds])

  const buildSwapPairs = () => {
    const mineListingIds = selectedMine
      .map((item) => listingIdOf(item))
      .filter((listingId) => listingId > 0)
    const targetListingIds = selectedTarget
      .map((item) => listingIdOf(item))
      .filter((listingId) => listingId > 0)

    if (!mineListingIds.length || !targetListingIds.length) {
      return []
    }

    const pairCount = Math.max(mineListingIds.length, targetListingIds.length)
    const pairs = []

    for (let index = 0; index < pairCount; index += 1) {
      pairs.push({
        from_listing_id: mineListingIds[index % mineListingIds.length],
        to_listing_id: targetListingIds[index % targetListingIds.length],
      })
    }

    return pairs
  }

  const addMine = (listing) => {
    const listingId = listingIdOf(listing)
    if (!listingId) return

    setSelectedMineIds((prev) => (prev.includes(listingId) ? prev : [...prev, listingId]))
    setLocalError('')
  }

  const addTarget = (listing) => {
    const listingId = listingIdOf(listing)
    if (!listingId) return

    const listingOwnerId = Number(listing?.sellerId ?? 0)
    if (targetOwnerId && listingOwnerId && targetOwnerId !== listingOwnerId) {
      setLocalError(copy.ownerOnly)
      return
    }

    setSelectedTargetIds((prev) => (prev.includes(listingId) ? prev : [...prev, listingId]))
    setLocalError('')
  }

  useEffect(() => {
    if (!pendingAdd?.token || !pendingAdd?.listing) return
    if (pendingAdd.side === 'mine') {
      addMine(pendingAdd.listing)
    } else if (pendingAdd.side === 'target') {
      addTarget(pendingAdd.listing)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pendingAdd?.token])

  const removeMine = (listingId) => {
    setSelectedMineIds((prev) => prev.filter((id) => id !== listingId))
  }

  const removeTarget = (listingId) => {
    setSelectedTargetIds((prev) => prev.filter((id) => id !== listingId))
  }

  const onDragStart = (event, listingId, source) => {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', JSON.stringify({ listingId, source }))
    setDraggingId(listingId)
  }

  const onDropToZone = (event, zone) => {
    event.preventDefault()
    setDragZone('')

    try {
      const payload = JSON.parse(event.dataTransfer.getData('text/plain') || '{}')
      const listingId = Number(payload?.listingId ?? 0)
      const source = String(payload?.source ?? '')

      if (!listingId) return

      if (zone === 'mine' && source === 'mine-pool') {
        const listing = myListingsById.get(listingId)
        if (listing) addMine(listing)
      }

      if (zone === 'target' && source === 'target-pool') {
        const listing = marketListingsById.get(listingId)
        if (listing) addTarget(listing)
      }
    } catch {
      // Ignore malformed drag payload.
    } finally {
      setDraggingId(0)
    }
  }

  const buildTitle = () => {
    if (!selectedMine.length) return ''

    const first = String(selectedMine[0]?.title ?? '').trim()
    if (selectedMine.length === 1) return first || 'Trade card'

    return `${first || 'Trade card'} + ${selectedMine.length - 1}`
  }

  const handleSubmit = async () => {
    if (!selectedMine.length) {
      setLocalError(copy.chooseMine)
      return
    }

    if (!selectedTarget.length) {
      setLocalError(copy.chooseTarget)
      return
    }

    if (!termsAccepted) {
      setLocalError(copy.acceptTerms)
      return
    }

    setLocalError('')
    const pairs = buildSwapPairs()

    const payload = {
      listing_id: listingIdOf(selectedTarget[0]),
      target_listing_ids: selectedTarget.map((item) => listingIdOf(item)),
      offered_listing_ids: selectedMine.map((item) => listingIdOf(item)),
      offered_title: buildTitle(),
      offered_description: requestMessage,
      offered_value: mineTotal,
      request_message: requestMessage,
      terms_accepted: termsAccepted,
      swap_pairs: pairs,
      offered_metadata: {
        studio_mode: true,
        target_owner_user_id: targetOwnerId || null,
        offered_total: Number(mineTotal.toFixed(2)),
        target_total: Number(targetTotal.toFixed(2)),
      },
    }

    try {
      await onSubmit?.(payload)
      setSelectedMineIds([])
      setSelectedTargetIds([])
      setRequestMessage('')
      setTermsAccepted(false)
    } catch (error) {
      setLocalError(error?.message || 'Could not submit trade proposal.')
    }
  }

  const renderPoolCard = (item, source) => {
    const listingId = listingIdOf(item)
    const isDragging = draggingId === listingId
    const isSelected =
      source === 'mine-pool' ? selectedMineSet.has(listingId) : selectedTargetSet.has(listingId)
    const imageUrl = listingImageOf(item)
    const sellerRating = Number(item?.sellerRating ?? 0)
    const seller = item?.seller ?? null
    const itemSellerId = Number(item?.sellerId ?? 0)
    const isLockedOut =
      source === 'target-pool' && targetOwnerId > 0 && itemSellerId > 0 && itemSellerId !== targetOwnerId

    return (
      <button
        key={`${source}-${listingId}`}
        type="button"
        draggable={!isLockedOut}
        disabled={isLockedOut}
        onDragStart={(event) => onDragStart(event, listingId, source)}
        onClick={() => (source === 'mine-pool' ? addMine(item) : addTarget(item))}
        className={`group w-full rounded-[20px] border p-2.5 text-left transition ${
          isLockedOut
            ? 'cursor-not-allowed border-[#eadab7] bg-[#fffaf2] opacity-45'
            : isDragging
              ? 'border-[#d4b074] bg-[#f6ead0] opacity-75'
              : isSelected
                ? 'border-[#d4b074] bg-[#fbf3e4] shadow-gold-soft'
                : 'border-[#eadab7] bg-white hover:border-[#d4b074] hover:bg-[#fbf6ec]'
        }`}
      >
        <div className="relative overflow-hidden rounded-[14px] border border-[#eadab7] bg-[#fffaf2]">
          <div className="aspect-[0.76]">
            {imageUrl ? (
              <>
                <img
                  src={imageUrl}
                  alt={item?.title ?? 'Trade card'}
                  className="absolute inset-0 h-full w-full scale-110 object-cover opacity-35 blur-xl"
                  loading="lazy"
                />
                <img
                  src={imageUrl}
                  alt={item?.title ?? 'Trade card'}
                  className="relative z-[1] h-full w-full object-contain p-1.5"
                  loading="lazy"
                />
              </>
            ) : (
              <div className="absolute inset-0 bg-gradient-to-br from-[#f8efd9] via-[#efe2c3] to-[#e1c792]" />
            )}
          </div>

          <div className="pointer-events-none absolute inset-x-0 bottom-0 z-[2] bg-gradient-to-t from-[rgba(255,249,239,0.96)] via-[rgba(255,249,239,0.84)] to-transparent px-2 py-2">
            <p className="line-clamp-1 text-xs font-semibold text-slate-900">{item?.title ?? 'Trade card'}</p>
            <p className="text-[11px] text-gold-700">{formatCurrency(Number(item?.price ?? 0))}</p>
          </div>

          <span className="absolute right-1.5 top-1.5 z-[2] inline-flex h-6 w-6 items-center justify-center rounded-full border border-[#eadab7] bg-[rgba(255,252,245,0.96)]">
            <GripVertical className="h-3.5 w-3.5 text-slate-500 transition group-hover:text-gold-700" />
          </span>
        </div>

        <div className="mt-2 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-600">
          <span className="rounded-full border border-[#eadab7] bg-[#fffaf2] px-2 py-0.5">{listingRarityOf(item)}</span>
          <span className="rounded-full border border-[#eadab7] bg-[#fffaf2] px-2 py-0.5">{listingConditionOf(item)}</span>
          {isSelected ? (
            <span className="rounded-full border border-[#d4b074] bg-[#f6ead0] px-2 py-0.5 text-gold-700">
              {copy.picked}
            </span>
          ) : null}
        </div>

        {source === 'target-pool' ? (
          <div className="mt-2 flex items-center justify-between gap-2 rounded-xl border border-[#eadab7] bg-[#fffaf2] px-2 py-1.5 text-[11px] text-slate-700">
            <div className="flex min-w-0 items-center gap-2">
              <UserAvatar user={seller} size="xs" className="h-6 w-6 text-[10px]" />
              <span className="truncate">{listingSellerNameOf(item)}</span>
            </div>
            <span className="shrink-0 text-slate-500">
              {copy.rating}: {sellerRating.toFixed(1)}
            </span>
          </div>
        ) : null}
      </button>
    )
  }

  const renderSelectedRow = (item, side) => {
    const listingId = listingIdOf(item)
    const imageUrl = listingImageOf(item)

    return (
      <div
        key={`${side}-selected-${listingId}`}
        className="relative overflow-hidden rounded-[14px] border border-[#eadab7] bg-[#fffaf2]"
      >
        <div className="aspect-[0.76]">
          {imageUrl ? (
            <img
              src={imageUrl}
              alt={item?.title ?? 'Trade card'}
              className="h-full w-full object-cover"
              loading="lazy"
            />
          ) : (
            <div className="absolute inset-0 bg-gradient-to-br from-[#f8efd9] via-[#efe2c3] to-[#e1c792]" />
          )}
        </div>

        <div className="pointer-events-none absolute inset-x-0 bottom-0 z-[2] bg-gradient-to-t from-[rgba(255,249,239,0.96)] via-[rgba(255,249,239,0.84)] to-transparent px-2 py-2">
          <p className="line-clamp-1 text-xs font-semibold text-slate-900">{item?.title ?? 'Trade card'}</p>
          <p className="text-[11px] text-gold-700">{formatCurrency(Number(item?.price ?? 0))}</p>
        </div>

        <button
          type="button"
          onClick={() => (side === 'mine' ? removeMine(listingId) : removeTarget(listingId))}
          aria-label={copy.remove}
          className="absolute right-1.5 top-1.5 z-[2] inline-flex h-6 w-6 items-center justify-center rounded-full border border-[#eadab7] bg-[rgba(255,252,245,0.96)] text-slate-500 transition hover:border-rose-300/40 hover:bg-rose-50 hover:text-rose-600"
        >
          <X className="h-3.5 w-3.5" />
        </button>
      </div>
    )
  }

  return (
    <CardSurface
      id="trade-studio"
      hover={false}
      className="relative mb-8 overflow-hidden border-[#eadab7] bg-[linear-gradient(180deg,rgba(255,255,255,0.99),rgba(252,247,238,0.98))] p-0"
    >
      <div className="pointer-events-none absolute inset-0">
        <div className="absolute -left-40 -top-40 h-[280px] w-[280px] rounded-full bg-[#f5e6c6]/45 blur-3xl" />
        <div className="absolute -right-28 top-10 h-[220px] w-[220px] rounded-full bg-[#f1dcc0]/35 blur-3xl" />
        <div className="absolute bottom-0 left-0 right-0 h-[220px] bg-[radial-gradient(circle_at_50%_0%,rgba(218,185,120,0.16),transparent_58%)]" />
      </div>

      <div className="relative z-[1] border-b border-[#eadab7] px-5 py-5 md:px-7 md:py-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p className="inline-flex items-center gap-2 rounded-full border border-[#d4b074] bg-[#f6ead0] px-3 py-1 text-[11px] uppercase tracking-[0.22em] text-gold-700">
              <Sparkles className="h-3.5 w-3.5" /> {copy.studioBadge}
            </p>
            <h3 className="mt-3 font-display text-2xl text-slate-900 md:text-3xl">{copy.title}</h3>
            <p className="mt-2 max-w-4xl text-sm leading-7 text-slate-600">{copy.subtitle}</p>
          </div>

          <div className="rounded-2xl border border-[#eadab7] bg-[#fffaf2] px-4 py-3 text-right">
            <p className="text-[11px] uppercase tracking-[0.22em] text-slate-500">{copy.depositPreview}</p>
            <p className="mt-1 text-2xl font-semibold text-gold-700">{formatCurrency(depositPreview)}</p>
          </div>
        </div>
      </div>

      <div className="relative z-[1] px-5 pb-5 pt-5 md:px-7 md:pb-7">
        <div className="grid gap-4 xl:grid-cols-[1fr_1.1fr_1fr]">
          <section className="rounded-[22px] border border-[#eadab7] bg-[rgba(255,255,255,0.96)] p-3.5">
            <div className="mb-3 flex items-center justify-between gap-2">
              <p className="text-xs uppercase tracking-[0.22em] text-gold-700">{copy.yourPool}</p>
              <span className="rounded-full border border-[#eadab7] bg-[#fffaf2] px-2.5 py-1 text-[10px] text-slate-600">
                {myListings.length} {copy.poolCount}
              </span>
            </div>
            <div className="max-h-[380px] overflow-auto pr-0.5 sm:max-h-[560px]">
              {myListings.length ? (
                <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                  {myListings.map((item) => renderPoolCard(item, 'mine-pool'))}
                </div>
              ) : (
                <p className="text-sm text-mist">{copy.noMine}</p>
              )}
            </div>
          </section>

          <section className="rounded-[22px] border border-[#eadab7] bg-[linear-gradient(180deg,rgba(255,250,242,0.98),rgba(247,238,222,0.98))] p-3.5">
            <div className="rounded-2xl border border-[#eadab7] bg-white px-4 py-3">
              <p className="text-xs uppercase tracking-[0.22em] text-gold-700">{copy.swapArena}</p>
              <p className="mt-1 text-xs leading-6 text-slate-600">{copy.arenaHint}</p>
            </div>

            <div className="mt-3 rounded-[20px] border border-[#eadab7] bg-[rgba(255,255,255,0.82)] p-3">
              <div className="grid items-start gap-3 xl:grid-cols-[1fr_auto_1fr]">
                <div
                  onDragOver={(event) => {
                    event.preventDefault()
                    setDragZone('mine')
                  }}
                  onDragLeave={() => setDragZone('')}
                  onDrop={(event) => onDropToZone(event, 'mine')}
                  className={`min-h-[170px] min-w-0 rounded-2xl border p-3 transition sm:min-h-[220px] ${
                    dragZone === 'mine'
                      ? 'border-[#d4b074] bg-[#f6ead0] shadow-[0_0_40px_rgba(212,176,116,0.18)]'
                      : 'border-[#eadab7] bg-white'
                  }`}
                >
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-sm font-semibold text-slate-900">{copy.yourBasket}</p>
                    <span className="text-[11px] text-gold-700">{formatCurrency(mineTotal)}</span>
                  </div>
                  <div className="mt-2 space-y-2">
                    {selectedMine.length ? (
                      selectedMine.map((item) => renderSelectedRow(item, 'mine'))
                    ) : (
                      <p className="text-sm text-mist">{copy.emptyMine}</p>
                    )}
                  </div>
                </div>

                <div className="mx-auto mt-1 flex h-9 w-9 items-center justify-center rounded-full border border-[#d4b074] bg-[#f6ead0] xl:mt-[92px]">
                  <ArrowRightLeft className="h-4 w-4 text-gold-700" />
                </div>

                <div
                  onDragOver={(event) => {
                    event.preventDefault()
                    setDragZone('target')
                  }}
                  onDragLeave={() => setDragZone('')}
                  onDrop={(event) => onDropToZone(event, 'target')}
                  className={`min-h-[170px] min-w-0 rounded-2xl border p-3 transition sm:min-h-[220px] ${
                    dragZone === 'target'
                      ? 'border-[#d4b074] bg-[#f6ead0] shadow-[0_0_40px_rgba(212,176,116,0.18)]'
                      : 'border-[#eadab7] bg-white'
                  }`}
                >
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-sm font-semibold text-slate-900">{copy.targetBasket}</p>
                    <span className="text-[11px] text-gold-700">{formatCurrency(targetTotal)}</span>
                  </div>

                  {targetOwnerId ? (
                    <div className="mt-2 flex max-w-full flex-wrap items-center gap-x-2 gap-y-1 rounded-xl border border-[#eadab7] bg-[#fffaf2] px-2.5 py-1.5 text-[11px] text-slate-700">
                      <span className="inline-flex min-w-0 max-w-full items-center gap-1.5">
                        <UserStar className="h-3.5 w-3.5 shrink-0 text-gold-700" />
                        <span className="truncate">
                          {copy.owner}: {targetOwnerName}
                        </span>
                      </span>
                      <span className="shrink-0 text-slate-500">
                        {copy.rating}: {targetOwnerRating.toFixed(1)}
                      </span>
                    </div>
                  ) : null}

                  <div className="mt-2 space-y-2">
                    {selectedTarget.length ? (
                      selectedTarget.map((item) => renderSelectedRow(item, 'target'))
                    ) : (
                      <p className="text-sm text-mist">{copy.emptyTarget}</p>
                    )}
                  </div>
                </div>
              </div>
            </div>

            <p className="mt-3 text-[11px] leading-5 text-slate-500">{copy.addTip}</p>
          </section>

          <section className="rounded-[22px] border border-[#eadab7] bg-[rgba(255,255,255,0.96)] p-3.5">
            <div className="mb-3 flex items-center justify-between gap-2">
              <p className="text-xs uppercase tracking-[0.22em] text-gold-700">{copy.marketPool}</p>
              <span className="rounded-full border border-[#eadab7] bg-[#fffaf2] px-2.5 py-1 text-[10px] text-slate-600">
                {marketListings.length} {copy.poolCount}
              </span>
            </div>
            {targetOwnerId ? (
              <div className="mb-3 rounded-xl border border-[#eadab7] bg-[#fffaf2] px-3 py-2 text-[11px] leading-5 text-slate-600">
                {copy.ownerOnly} ({targetOwnerName})
              </div>
            ) : null}
            <div className="max-h-[380px] overflow-auto pr-0.5 sm:max-h-[560px]">
              {marketListings.length ? (
                <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                  {marketListings.map((item) => renderPoolCard(item, 'target-pool'))}
                </div>
              ) : (
                <p className="text-sm text-mist">{copy.noMarket}</p>
              )}
            </div>
          </section>
        </div>

        <div className="mt-4">
          <section className="rounded-[22px] border border-[#eadab7] bg-white p-4">
            <p className="text-[11px] uppercase tracking-[0.22em] text-slate-500">{copy.diffToCover}</p>
            <p className="mt-1 text-3xl font-semibold text-slate-900">{formatCurrency(Math.abs(valueDelta))}</p>
            <p className="mt-2 text-xs text-slate-600">
              {valueDelta > 0
                ? copy.valueHigherRequest
                : valueDelta < 0
                  ? copy.valueHigherOffer
                  : copy.valueBalanced}
            </p>

            <p className="mt-4 text-[11px] uppercase tracking-[0.22em] text-slate-500">{copy.requestMessage}</p>
            <Input
              className="mt-2"
              value={requestMessage}
              onChange={(event) => setRequestMessage(event.target.value)}
              placeholder={copy.requestPlaceholder}
            />

            <label className="mt-3 flex items-start gap-2 rounded-xl border border-[#eadab7] bg-[#fffaf2] px-3 py-2 text-xs text-slate-600">
              <input
                type="checkbox"
                checked={termsAccepted}
                onChange={(event) => setTermsAccepted(event.target.checked)}
                className="mt-0.5 h-4 w-4 rounded border-[#d4b074] bg-white text-[#c79d62]"
              />
              <span>{copy.terms}</span>
            </label>

            {localError ? (
              <div className="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                {localError}
              </div>
            ) : null}

            <Button className="mt-3 w-full" onClick={handleSubmit} disabled={submitting}>
              <ShieldCheck className="h-4 w-4" />
              {copy.send}
            </Button>
          </section>
        </div>
      </div>
    </CardSurface>
  )
}

export default TradeSwapStudio
