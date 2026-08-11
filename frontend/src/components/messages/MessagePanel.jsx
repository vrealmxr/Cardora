import {
  AlertTriangle,
  CheckCheck,
  Euro,
  SendHorizonal,
  ShieldAlert,
  Tag,
  XCircle,
} from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import UserAvatar from '@/components/people/UserAvatar'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input, Textarea } from '@/components/ui/Input'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { formatCurrency, formatShortDateTime } from '@/utils/formatters'
import { getUserDisplayName } from '@/utils/helpers'
import { normalizeTextTree } from '@/utils/textEncoding'

const MINIMUM_PRIVATE_OFFER_TOTAL = 2.5

function MessagePanel({ conversation, currentUser }) {
  const navigate = useNavigate()
  const { locale } = useI18n()
  const {
    productsWithSellers,
    users,
    sendMessage,
    markConversationRead,
    createListingOffer,
    counterListingOffer,
    acceptListingOffer,
    rejectListingOffer,
  } = useMarketplace()
  const [message, setMessage] = useState('')
  const [isSending, setIsSending] = useState(false)
  const [offerComposerOpen, setOfferComposerOpen] = useState(false)
  const [offerComposerMode, setOfferComposerMode] = useState('new')
  const [offerActionTargetId, setOfferActionTargetId] = useState(null)
  const [offerAmount, setOfferAmount] = useState('')
  const [offerNote, setOfferNote] = useState('')
  const [offerNotice, setOfferNotice] = useState(null)
  const [isOfferSubmitting, setIsOfferSubmitting] = useState(false)

  const copy = useMemo(
    () =>
      normalizeTextTree(
        locale === 'en'
          ? {
              empty: 'Select a conversation to read the messages.',
              heading: 'Conversation about listing',
              withCounterparty: 'Conversation with',
              offer: 'Private offer',
              sendOffer: 'Send offer',
              sendCounterOffer: 'Send counteroffer',
              context: 'Listing details',
              placeholder: 'Write a message...',
              send: 'Send',
              sending: 'Sending...',
              policyTitle: 'Cardora message safety',
              policyBody:
                'Phone numbers, email addresses, direct links, off-platform payment requests, addresses and abusive language are filtered automatically. Repeated violations may lead to account suspension.',
              moderatedOwn:
                'Part of this message was hidden because it looks like contact details, an off-platform attempt or abusive language. Repeated violations may lead to account suspension.',
              moderatedOther:
                'Cardora hid part of this message to keep the transaction inside the platform and protect both sides.',
              offerTotalLabel: 'Agreed total',
              offerItemLabel: 'Item value',
              offerShippingLabel: 'Included shipping',
              offerIncludesShipping: 'The agreed total already includes the mandatory €2.50 shipping amount.',
              offerDescription:
                'This is a private amount only for you and the seller. It does not change the public listing price.',
              offerAmountInput: 'Total amount for this buyer',
              offerNoteInput: 'Message with the offer',
              offerNotePlaceholder: 'Optional note for the seller...',
              offerActionsTitle: 'Seller can accept, decline or counter from the same thread.',
              offerSubmit: 'Send private offer',
              offerCounterSubmit: 'Send counteroffer',
              offerCancel: 'Cancel',
              offerMinimumHint: 'The amount must stay above the included €2.50 shipping.',
              offerAccepted: 'Accepted',
              offerPending: 'Awaiting reply',
              offerRejected: 'Declined',
              offerPaid: 'Paid',
              offerCountered: 'Countered',
              accept: 'Accept',
              reject: 'Decline',
              counter: 'Counter',
              payNow: 'Pay agreed amount',
              noteLabel: 'Offer note',
              newOfferSuccess: 'Private offer sent. You can continue the negotiation in this thread.',
              counterOfferSuccess: 'Counteroffer sent.',
              offerAcceptedSuccess: 'Private offer accepted. The buyer can now pay the agreed amount.',
              offerRejectedSuccess: 'Private offer declined.',
              offerActionCreated: 'Private offer',
              offerActionCountered: 'Counteroffer',
              offerActionAccepted: 'Accepted offer',
              offerActionRejected: 'Declined offer',
              payHelp: 'The buyer will pay this exact total in checkout.',
            }
          : {
              empty: 'Επίλεξε μια συνομιλία για να δεις τα μηνύματα.',
              heading: 'Συζήτηση για αγγελία',
              withCounterparty: 'Συζήτηση με',
              offer: 'Προσωπική προσφορά',
              sendOffer: 'Προσωπική προσφορά',
              sendCounterOffer: 'Στείλε αντιπρόταση',
              context: 'Στοιχεία αγγελίας',
              placeholder: 'Γράψε μήνυμα...',
              send: 'Αποστολή',
              sending: 'Αποστολή...',
              policyTitle: 'Προστασία συνομιλιών Cardora',
              policyBody:
                'Τηλέφωνα, email, links, αιτήματα εκτός πλατφόρμας, διευθύνσεις και υβριστικό περιεχόμενο φιλτράρονται αυτόματα. Επαναλαμβανόμενες παραβάσεις μπορούν να οδηγήσουν σε αναστολή λογαριασμού.',
              moderatedOwn:
                'Τμήμα αυτού του μηνύματος κρύφτηκε επειδή μοιάζει με στοιχεία επικοινωνίας, απόπειρα εκτός πλατφόρμας ή υβριστικό περιεχόμενο. Επαναλαμβανόμενες παραβάσεις μπορούν να οδηγήσουν σε αναστολή λογαριασμού.',
              moderatedOther:
                'Η Cardora έκρυψε τμήμα αυτού του μηνύματος για να προστατεύσει τη συναλλαγή και να παραμείνει μέσα στην πλατφόρμα.',
              offerTotalLabel: 'Συμφωνημένο σύνολο',
              offerItemLabel: 'Καθαρή αξία αντικειμένου',
              offerShippingLabel: 'Μεταφορικά που περιλαμβάνονται',
              offerIncludesShipping: 'Το συμφωνημένο ποσό περιλαμβάνει ήδη τα υποχρεωτικά 2,50€ μεταφορικών.',
              offerDescription:
                'Αυτό το ποσό είναι ιδιωτικό μόνο για εσένα και τον πωλητή. Δεν αλλάζει τη δημόσια τιμή της αγγελίας.',
              offerAmountInput: 'Τελικό ποσό για αυτόν τον αγοραστή',
              offerNoteInput: 'Μήνυμα μαζί με την προσφορά',
              offerNotePlaceholder: 'Προαιρετικό σχόλιο προς τον πωλητή...',
              offerActionsTitle: 'Ο πωλητής μπορεί να δεχτεί, να απορρίψει ή να κάνει αντιπρόταση μέσα από το ίδιο νήμα.',
              offerSubmit: 'Στείλε προσωπική προσφορά',
              offerCounterSubmit: 'Στείλε αντιπρόταση',
              offerCancel: 'Ακύρωση',
              offerMinimumHint: 'Το ποσό πρέπει να μένει πάνω από τα 2,50€ που περιλαμβάνονται για μεταφορικά.',
              offerAccepted: 'Έγινε αποδεκτή',
              offerPending: 'Περιμένει απάντηση',
              offerRejected: 'Απορρίφθηκε',
              offerPaid: 'Πληρώθηκε',
              offerCountered: 'Έγινε αντιπρόταση',
              accept: 'Αποδοχή',
              reject: 'Απόρριψη',
              counter: 'Αντιπρόταση',
              payNow: 'Πληρωμή συμφωνημένου ποσού',
              noteLabel: 'Σημείωση προσφοράς',
              newOfferSuccess: 'Η προσωπική προσφορά στάλθηκε. Μπορείτε να συνεχίσετε εδώ τη διαπραγμάτευση.',
              counterOfferSuccess: 'Η αντιπρόταση στάλθηκε.',
              offerAcceptedSuccess: 'Η προσωπική προσφορά έγινε αποδεκτή. Ο αγοραστής μπορεί τώρα να πληρώσει το συμφωνημένο ποσό.',
              offerRejectedSuccess: 'Η προσωπική προσφορά απορρίφθηκε.',
              offerActionCreated: 'Προσωπική προσφορά',
              offerActionCountered: 'Αντιπρόταση',
              offerActionAccepted: 'Αποδεκτή προσφορά',
              offerActionRejected: 'Απορριφθείσα προσφορά',
              payHelp: 'Ο αγοραστής θα πληρώσει ακριβώς αυτό το συνολικό ποσό στο checkout.',
            },
      ),
    [locale],
  )

  useEffect(() => {
    if (!conversation?.id || !conversation?.unreadCount) return

    markConversationRead(conversation.id).catch(() => {})
  }, [conversation?.id, conversation?.unreadCount, markConversationRead])

  if (!conversation) {
    return (
      <CardSurface className="flex h-full items-center justify-center">
        <p className="text-mist">{copy.empty}</p>
      </CardSurface>
    )
  }

  const currentUserId = Number(currentUser?.id ?? 0)
  const listingId = Number(conversation.productId ?? conversation.listing_id)
  const product = productsWithSellers.find((item) => Number(item.id) === listingId)
  const buyer = users.find(
    (user) => Number(user.id) === Number(conversation.buyerId ?? conversation.buyer_id),
  )
  const seller = users.find(
    (user) => Number(user.id) === Number(conversation.sellerId ?? conversation.seller_id),
  )
  const counterparty =
    currentUserId === Number(conversation.sellerId ?? conversation.seller_id) ? buyer : seller
  const isBuyer = currentUserId === Number(conversation.buyerId ?? conversation.buyer_id)
  const isOwnListing = Number(product?.sellerId ?? product?.seller?.id ?? 0) === currentUserId
  const canCreateNewOffer =
    Boolean(product?.acceptOffers) &&
    product?.saleFormat === 'fixed_price' &&
    isBuyer &&
    !isOwnListing

  const contextParts = [
    product?.subtitle,
    product?.franchise,
    product?.price != null ? formatCurrency(product.price) : null,
  ].filter(Boolean)

  const resetOfferComposer = () => {
    setOfferComposerOpen(false)
    setOfferComposerMode('new')
    setOfferActionTargetId(null)
    setOfferAmount('')
    setOfferNote('')
  }

  const primeOfferComposer = (mode, offer = null) => {
    setOfferComposerMode(mode)
    setOfferActionTargetId(offer?.id ?? null)
    setOfferAmount(
      String(
        Number(
          offer?.totalAmount ??
            product?.minimumOffer ??
            product?.price ??
            MINIMUM_PRIVATE_OFFER_TOTAL,
        ).toFixed(2),
      ),
    )
    setOfferNote('')
    setOfferNotice(null)
    setOfferComposerOpen(true)
  }

  const submitMessage = async (event) => {
    event.preventDefault()

    if (!message.trim() || isSending) return

    try {
      setIsSending(true)
      await sendMessage(conversation.id, message)
      setMessage('')
    } finally {
      setIsSending(false)
    }
  }

  const submitOffer = async (event) => {
    event.preventDefault()

    if (isOfferSubmitting) return

    const parsedAmount = Number.parseFloat(String(offerAmount).replace(',', '.'))

    if (!Number.isFinite(parsedAmount) || parsedAmount < MINIMUM_PRIVATE_OFFER_TOTAL) {
      setOfferNotice({
        tone: 'warning',
        text: copy.offerMinimumHint,
      })
      return
    }

    try {
      setIsOfferSubmitting(true)
      setOfferNotice(null)

      if (offerComposerMode === 'counter' && offerActionTargetId) {
        await counterListingOffer({
          offerId: offerActionTargetId,
          totalAmount: parsedAmount,
          note: offerNote,
        })

        setOfferNotice({
          tone: 'success',
          text: copy.counterOfferSuccess,
        })
      } else {
        await createListingOffer({
          conversationId: conversation.id,
          totalAmount: parsedAmount,
          note: offerNote,
        })

        setOfferNotice({
          tone: 'success',
          text: copy.newOfferSuccess,
        })
      }

      resetOfferComposer()
    } catch (error) {
      setOfferNotice({
        tone: 'warning',
        text: error.message,
      })
    } finally {
      setIsOfferSubmitting(false)
    }
  }

  const respondToOffer = async (handler, offerId, successText) => {
    try {
      setOfferNotice(null)
      await handler({ offerId })
      setOfferNotice({
        tone: 'success',
        text: successText,
      })
    } catch (error) {
      setOfferNotice({
        tone: 'warning',
        text: error.message,
      })
    }
  }

  const actionLabelForOffer = (item) => {
    const action = item?.metadata?.offer_action

    if (action === 'countered') return copy.offerActionCountered
    if (action === 'accepted') return copy.offerActionAccepted
    if (action === 'rejected') return copy.offerActionRejected
    return copy.offerActionCreated
  }

  return (
    <CardSurface className="flex h-full flex-col gap-4 p-0">
      <div className="border-b border-white/8 p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="text-xs uppercase tracking-[0.3em] text-gold-100">{copy.heading}</p>
            <h3 className="mt-2 text-xl font-semibold text-white">{product?.title ?? '-'}</h3>
            <p className="mt-1 text-sm text-mist">
              {copy.withCounterparty} {getUserDisplayName(counterparty)}
            </p>
          </div>
          {canCreateNewOffer ? (
            <Button
              variant="subtle"
              size="sm"
              type="button"
              onClick={() => primeOfferComposer('new')}
            >
              <Tag className="h-4 w-4" />
              {copy.sendOffer}
            </Button>
          ) : null}
        </div>
      </div>

      <div className="mx-5 rounded-2xl border border-white/8 bg-white/5 p-4 text-sm text-mist">
        <span className="font-semibold text-white">{copy.context}:</span>{' '}
        {contextParts.length ? contextParts.join(' | ') : '-'}
      </div>

      <div className="mx-5 rounded-2xl border border-gold-300/18 bg-gold-300/10 p-4">
        <div className="flex items-center gap-2 text-sm font-semibold text-gold-50">
          <ShieldAlert className="h-4 w-4 text-gold-100" />
          {copy.policyTitle}
        </div>
        <p className="mt-2 text-sm leading-7 text-mist">{copy.policyBody}</p>
      </div>

      {offerComposerOpen ? (
        <div className="mx-5 rounded-[24px] border border-gold-300/20 bg-gold-300/10 p-5">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.offer}</p>
              <h4 className="mt-3 text-xl font-semibold text-white">
                {offerComposerMode === 'counter' ? copy.sendCounterOffer : copy.sendOffer}
              </h4>
              <p className="mt-2 max-w-3xl text-sm leading-7 text-gold-50">
                {copy.offerDescription}
              </p>
            </div>
            <Button type="button" variant="secondary" size="sm" onClick={resetOfferComposer}>
              {copy.offerCancel}
            </Button>
          </div>

          <form onSubmit={submitOffer} className="mt-5 grid gap-4 md:grid-cols-[220px,1fr]">
            <div>
              <label className="mb-2 block text-sm text-mist">{copy.offerAmountInput}</label>
              <Input
                type="number"
                min={MINIMUM_PRIVATE_OFFER_TOTAL}
                step="0.01"
                value={offerAmount}
                onChange={(event) => setOfferAmount(event.target.value)}
                placeholder="0.00"
              />
              <p className="mt-2 text-xs leading-6 text-mist">{copy.offerMinimumHint}</p>
            </div>
            <div>
              <label className="mb-2 block text-sm text-mist">{copy.offerNoteInput}</label>
              <Textarea
                className="min-h-[110px]"
                value={offerNote}
                onChange={(event) => setOfferNote(event.target.value)}
                placeholder={copy.offerNotePlaceholder}
              />
            </div>
            <div className="md:col-span-2 rounded-2xl border border-white/10 bg-black/10 px-4 py-3 text-sm leading-7 text-white/78">
              {copy.offerActionsTitle}
            </div>
            <div className="md:col-span-2 flex flex-wrap gap-3">
              <Button type="submit" disabled={isOfferSubmitting}>
                <Euro className="h-4 w-4" />
                {isOfferSubmitting
                  ? copy.sending
                  : offerComposerMode === 'counter'
                    ? copy.offerCounterSubmit
                    : copy.offerSubmit}
              </Button>
              <Button type="button" variant="secondary" onClick={resetOfferComposer}>
                {copy.offerCancel}
              </Button>
            </div>
          </form>
        </div>
      ) : null}

      {offerNotice ? (
        <div
          className={`mx-5 rounded-xl border px-4 py-3 text-sm ${
            offerNotice.tone === 'success'
              ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-800'
              : 'border-amber-400/20 bg-amber-500/10 text-amber-800'
          }`}
        >
          {offerNotice.text}
        </div>
      ) : null}

      <div className="premium-scrollbar flex-1 space-y-4 overflow-y-auto px-5">
        {(conversation.messages ?? []).map((item) => {
          const senderId = Number(item.senderId ?? item.sender_id)
          const sender = users.find((user) => Number(user.id) === senderId)
          const isOwn = senderId === currentUserId
          const messageText = item.text ?? item.body ?? ''
          const sentAt = item.sentAt ?? item.created_at
          const isModerated = Boolean(
            item.isModerated ?? (item.moderationStatus && item.moderationStatus !== 'clean'),
          )
          const offer = item.offer
          const isOfferMessage = Boolean(offer && item.metadata?.message_type === 'listing_offer')

          return (
            <div key={item.id} className={`flex ${isOwn ? 'justify-end' : 'justify-start'}`}>
              <div
                className={`max-w-[80%] rounded-[24px] border px-4 py-3 ${
                  isOwn
                    ? 'border-gold-300/30 bg-gold-300/14 text-gold-50'
                    : 'border-white/8 bg-white/6 text-white'
                }`}
              >
                <div className="mb-2 flex items-center gap-3 text-xs text-white/55">
                  <UserAvatar user={sender} size="xs" />
                  <span>{getUserDisplayName(sender)}</span>
                  <span>{sentAt ? formatShortDateTime(sentAt) : '-'}</span>
                </div>

                {isOfferMessage ? (
                  <div className="space-y-4">
                    <div>
                      <p className="text-[11px] uppercase tracking-[0.24em] text-gold-100">
                        {actionLabelForOffer(item)}
                      </p>
                      <div className="mt-3 grid gap-3 md:grid-cols-3">
                        <div className="rounded-2xl border border-white/10 bg-black/10 px-3.5 py-3">
                          <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">
                            {copy.offerTotalLabel}
                          </p>
                          <p className="mt-2 text-xl font-semibold text-white">
                            {formatCurrency(Number(offer.totalAmount ?? 0))}
                          </p>
                        </div>
                        <div className="rounded-2xl border border-white/10 bg-black/10 px-3.5 py-3">
                          <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">
                            {copy.offerItemLabel}
                          </p>
                          <p className="mt-2 text-base font-semibold text-white">
                            {formatCurrency(Number(offer.itemAmount ?? 0))}
                          </p>
                        </div>
                        <div className="rounded-2xl border border-white/10 bg-black/10 px-3.5 py-3">
                          <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">
                            {copy.offerShippingLabel}
                          </p>
                          <p className="mt-2 text-base font-semibold text-white">
                            {formatCurrency(Number(offer.shippingAmount ?? 0))}
                          </p>
                        </div>
                      </div>
                    </div>

                    <div className="rounded-2xl border border-white/10 bg-black/10 px-4 py-3 text-sm leading-7 text-white/80">
                      {copy.offerIncludesShipping}
                      <div className="mt-2 text-gold-100">{copy.payHelp}</div>
                    </div>

                    {offer.note ? (
                      <div className="rounded-2xl border border-white/10 bg-black/10 px-4 py-3">
                        <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">
                          {copy.noteLabel}
                        </p>
                        <p className="mt-2 text-sm leading-7 text-white/85">{offer.note}</p>
                      </div>
                    ) : null}

                    <div className="flex flex-wrap items-center gap-2">
                      <span className="rounded-full border border-gold-300/20 bg-gold-300/10 px-3 py-1 text-[11px] font-semibold text-gold-50">
                        {offer.status === 'accepted'
                          ? copy.offerAccepted
                          : offer.status === 'rejected'
                            ? copy.offerRejected
                            : offer.status === 'paid'
                              ? copy.offerPaid
                              : offer.status === 'countered'
                                ? copy.offerCountered
                                : copy.offerPending}
                      </span>

                      {offer.isActionable ? (
                        <>
                          <Button
                            type="button"
                            size="sm"
                            onClick={() =>
                              respondToOffer(
                                acceptListingOffer,
                                offer.id,
                                copy.offerAcceptedSuccess,
                              )
                            }
                          >
                            <CheckCheck className="h-4 w-4" />
                            {copy.accept}
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            onClick={() => primeOfferComposer('counter', offer)}
                          >
                            <Tag className="h-4 w-4" />
                            {copy.counter}
                          </Button>
                          <Button
                            type="button"
                            size="sm"
                            variant="danger"
                            onClick={() =>
                              respondToOffer(
                                rejectListingOffer,
                                offer.id,
                                copy.offerRejectedSuccess,
                              )
                            }
                          >
                            <XCircle className="h-4 w-4" />
                            {copy.reject}
                          </Button>
                        </>
                      ) : null}

                      {offer.canPay ? (
                        <Button
                          type="button"
                          size="sm"
                          onClick={() => navigate(`/checkout?offer=${offer.id}`)}
                        >
                          <Euro className="h-4 w-4" />
                          {copy.payNow}
                        </Button>
                      ) : null}
                    </div>
                  </div>
                ) : (
                  <>
                    <p className="leading-7">{messageText}</p>
                    {isModerated ? (
                      <div className="mt-3 rounded-xl border border-white/10 bg-slate-950/25 px-3 py-2 text-xs leading-6 text-white/75">
                        <div className="flex items-start gap-2">
                          <AlertTriangle className="mt-0.5 h-4 w-4 flex-none text-gold-100" />
                          <span>{isOwn ? copy.moderatedOwn : copy.moderatedOther}</span>
                        </div>
                      </div>
                    ) : null}
                  </>
                )}
              </div>
            </div>
          )
        })}
      </div>

      <form onSubmit={submitMessage} className="border-t border-white/8 p-5">
        <div className="flex flex-wrap gap-3">
          <div className="min-w-[220px] flex-1">
            <Input
              value={message}
              onChange={(event) => setMessage(event.target.value)}
              placeholder={copy.placeholder}
            />
          </div>
          <Button type="submit" disabled={isSending || !message.trim()}>
            <SendHorizonal className="h-4 w-4" />
            {isSending ? copy.sending : copy.send}
          </Button>
        </div>
      </form>
    </CardSurface>
  )
}

export default MessagePanel
