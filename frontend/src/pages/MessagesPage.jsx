import { MessageCircle, PackageSearch } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import MessagePanel from '@/components/messages/MessagePanel'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { formatShortDateTime } from '@/utils/formatters'
import { getUserDisplayName } from '@/utils/helpers'

function MessagesPage() {
  const { locale } = useI18n()
  const { currentUser } = useAuth()
  const { conversations, productsWithSellers, users } = useMarketplace()
  const [searchParams] = useSearchParams()
  const [activeConversationId, setActiveConversationId] = useState(null)

  const copy = useMemo(
    () =>
      locale === 'en'
        ? {
            eyebrow: 'Messages',
            title: 'Inbox for buyers and sellers',
            description:
              'Keep each order conversation, the item context and the next step together in one place.',
            listTitle: 'Conversations',
            listDescription: 'Open a conversation directly from any order card.',
            empty: 'You do not have any conversations yet.',
            emptyHint: 'A new thread appears automatically as soon as you message from an order.',
            orderContext: 'Order item',
            unread: 'Unread',
            offer: 'Private offer',
          }
        : {
            eyebrow: 'Μηνύματα',
            title: 'Inbox για αγοραστή και πωλητή',
            description:
              'Κράτησε τη συζήτηση, το αντικείμενο και το επόμενο βήμα κάθε παραγγελίας στο ίδιο σημείο.',
            listTitle: 'Συνομιλίες',
            listDescription: 'Μπορείς να ανοίξεις νέα συζήτηση κατευθείαν από κάθε order card.',
            empty: 'Δεν υπάρχουν ακόμη συνομιλίες.',
            emptyHint: 'Μόλις στείλεις μήνυμα από μία παραγγελία, θα εμφανιστεί εδώ αυτόματα.',
            orderContext: 'Αντικείμενο παραγγελίας',
            unread: 'Αδιάβαστα',
            offer: 'Προσωπική προσφορά',
          },
    [locale],
  )

  useEffect(() => {
    const requestedId = Number(searchParams.get('conversation'))

    if (requestedId && conversations.some((conversation) => Number(conversation.id) === requestedId)) {
      setActiveConversationId(requestedId)
      return
    }

    setActiveConversationId((current) => {
      if (current && conversations.some((conversation) => Number(conversation.id) === Number(current))) {
        return current
      }

      return conversations[0] ? Number(conversations[0].id) : null
    })
  }, [conversations, searchParams])

  const activeConversation = conversations.find(
    (conversation) => Number(conversation.id) === Number(activeConversationId),
  )

  return (
    <div className="container pb-16">
      <SectionHeader eyebrow={copy.eyebrow} title={copy.title} description={copy.description} />

      <div className="grid gap-8 xl:grid-cols-[360px,1fr]">
        <CardSurface className="h-full p-4">
          <div className="border-b border-white/8 pb-4">
            <p className="text-xs uppercase tracking-[0.3em] text-gold-100">{copy.listTitle}</p>
            <p className="mt-2 text-sm leading-7 text-mist">{copy.listDescription}</p>
          </div>

          {conversations.length ? (
            <div className="mt-4 space-y-2">
              {conversations.map((conversation) => {
                const listingId = Number(conversation.productId ?? conversation.listing_id)
                const product = productsWithSellers.find((item) => Number(item.id) === listingId)
                const buyer = users.find(
                  (user) => Number(user.id) === Number(conversation.buyerId ?? conversation.buyer_id),
                )
                const seller = users.find(
                  (user) => Number(user.id) === Number(conversation.sellerId ?? conversation.seller_id),
                )
                const counterparty =
                  Number(currentUser?.id) === Number(conversation.sellerId ?? conversation.seller_id)
                    ? buyer
                    : seller
                const lastMessage = conversation.messages?.at(-1)
                const lastMessageText = lastMessage?.offer
                  ? `${copy.offer} • ${
                      lastMessage.offer.totalAmount != null
                        ? new Intl.NumberFormat(locale === 'en' ? 'en-US' : 'el-GR', {
                            style: 'currency',
                            currency: 'EUR',
                            maximumFractionDigits: 2,
                          }).format(Number(lastMessage.offer.totalAmount))
                        : ''
                    }`.trim()
                  : lastMessage?.text ?? lastMessage?.body ?? copy.emptyHint
                const lastMessageTime = lastMessage?.sentAt ?? lastMessage?.created_at ?? null
                const unreadCount = Number(conversation.unreadCount ?? 0)

                return (
                  <button
                    key={conversation.id}
                    type="button"
                    onClick={() => setActiveConversationId(Number(conversation.id))}
                    className={`w-full rounded-[24px] border p-4 text-left transition ${
                      Number(activeConversationId) === Number(conversation.id)
                        ? 'border-gold-300/30 bg-gold-300/12'
                        : 'border-white/8 bg-white/5 hover:border-white/14'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <p className="text-sm font-semibold text-white">{getUserDisplayName(counterparty)}</p>
                        <p className="mt-1 text-xs uppercase tracking-[0.24em] text-gold-100">
                          {copy.orderContext}
                        </p>
                      </div>
                      <div className="flex flex-col items-end gap-2">
                        {lastMessageTime ? (
                          <span className="text-[11px] text-white/45">
                            {formatShortDateTime(lastMessageTime)}
                          </span>
                        ) : null}
                        {unreadCount > 0 ? (
                          <span className="rounded-full border border-gold-300/25 bg-gold-300/12 px-2.5 py-1 text-[11px] font-semibold text-gold-50">
                            {copy.unread}: {unreadCount}
                          </span>
                        ) : null}
                      </div>
                    </div>

                    <p className="mt-3 line-clamp-2 text-sm font-medium text-white/90">
                      {product?.title ?? '-'}
                    </p>
                    <p className={`mt-2 line-clamp-2 text-sm ${unreadCount > 0 ? 'text-white' : 'text-mist'}`}>
                      {lastMessageText}
                    </p>
                  </button>
                )
              })}
            </div>
          ) : (
            <div className="flex min-h-[340px] flex-col items-center justify-center rounded-[28px] border border-dashed border-white/10 bg-white/4 px-6 py-10 text-center">
              <div className="flex h-16 w-16 items-center justify-center rounded-full border border-gold-300/20 bg-gold-300/10">
                <MessageCircle className="h-7 w-7 text-gold-100" />
              </div>
              <p className="mt-5 text-lg font-semibold text-white">{copy.empty}</p>
              <p className="mt-2 max-w-xs text-sm leading-7 text-mist">{copy.emptyHint}</p>
            </div>
          )}
        </CardSurface>

        {conversations.length ? (
          <MessagePanel conversation={activeConversation} currentUser={currentUser} />
        ) : (
          <CardSurface className="flex min-h-[520px] flex-col items-center justify-center text-center">
            <div className="flex h-20 w-20 items-center justify-center rounded-full border border-gold-300/20 bg-gold-300/10">
              <PackageSearch className="h-8 w-8 text-gold-100" />
            </div>
            <p className="mt-6 text-2xl font-semibold text-white">{copy.empty}</p>
            <p className="mt-3 max-w-xl text-sm leading-7 text-mist">{copy.emptyHint}</p>
          </CardSurface>
        )}
      </div>
    </div>
  )
}

export default MessagesPage
