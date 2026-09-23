import { X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { loadConnectAndInitialize } from '@stripe/connect-js'
import { ConnectAccountManagement, ConnectComponentsProvider, ConnectNotificationBanner, ConnectPayouts } from '@stripe/react-connect-js'
import { useMarketplace } from '@/hooks/useMarketplace'

// Embedded Stripe Connect account management — sellers manage payout/bank
// and identity settings inline on Cardora instead of being redirected to
// the Stripe-hosted Express Dashboard.
function StripeAccountManagementModal({ open, onClose, isEnglish }) {
  const { getSellerAccountManagementSession } = useMarketplace()
  const [connectInstance, setConnectInstance] = useState(null)
  const [error, setError] = useState(null)
  const initializedRef = useRef(false)

  useEffect(() => {
    if (!open || initializedRef.current) return undefined
    initializedRef.current = true

    let cancelled = false

    const init = async () => {
      try {
        // Stripe's publishable key is not secret, but the SDK still needs it
        // synchronously to initialize — fetch the first session up front,
        // then hand fetchClientSecret a callback that can re-fetch a fresh
        // one whenever Connect.js needs to refresh it.
        const firstSession = await getSellerAccountManagementSession()
        if (!firstSession?.client_secret || !firstSession?.publishable_key) {
          throw new Error('missing_session')
        }
        if (cancelled) return

        let latestClientSecret = firstSession.client_secret

        const instance = loadConnectAndInitialize({
          publishableKey: firstSession.publishable_key,
          fetchClientSecret: async () => {
            if (latestClientSecret) {
              const secret = latestClientSecret
              latestClientSecret = null
              return secret
            }
            const session = await getSellerAccountManagementSession()
            return session?.client_secret
          },
          appearance: {
            variables: {
              colorPrimary: '#9d6a17',
              fontFamily: 'Manrope, ui-sans-serif, system-ui, sans-serif',
              borderRadius: '12px',
            },
          },
        })

        if (!cancelled) setConnectInstance(instance)
      } catch (err) {
        if (!cancelled) setError(err?.message || 'Could not load Stripe account management.')
      }
    }

    init()

    return () => {
      cancelled = true
    }
  }, [open, getSellerAccountManagementSession])

  useEffect(() => {
    if (!open) return undefined
    const previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      document.body.style.overflow = previousOverflow
    }
  }, [open])

  if (typeof document === 'undefined' || !open) return null

  const copy = isEnglish
    ? {
        title: 'Manage your Stripe account',
        loading: 'Loading…',
        payoutsHeading: 'Balance & payouts',
        accountHeading: 'Account details',
      }
    : {
        title: 'Διαχείριση Stripe account',
        loading: 'Φόρτωση…',
        payoutsHeading: 'Υπόλοιπο & αναλήψεις',
        accountHeading: 'Στοιχεία λογαριασμού',
      }

  return createPortal(
    <div className="fixed inset-0 z-[400] flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-[rgba(28,24,17,0.45)]" onClick={onClose} />
      <div className="relative flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-[22px] border border-[#ead7ae] bg-white shadow-[0_24px_60px_rgba(15,23,42,0.18)]">
        <div className="flex items-center justify-between border-b border-[#eee2c4] px-5 py-4">
          <h2 className="font-display text-lg font-semibold text-ink">{copy.title}</h2>
          <button
            type="button"
            onClick={onClose}
            className="rounded-full border border-[#ead7ae] bg-white p-1.5 text-[#7a6440] transition hover:border-[#d8b06a]"
            aria-label="Close"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="min-h-[300px] flex-1 overflow-y-auto p-5">
          {error ? (
            <p className="text-sm text-rose-600">{error}</p>
          ) : connectInstance ? (
            <ConnectComponentsProvider connectInstance={connectInstance}>
              <ConnectNotificationBanner />
              <div className="mt-4">
                <h3 className="mb-2 text-sm font-semibold text-ink">{copy.payoutsHeading}</h3>
                <ConnectPayouts />
              </div>
              <div className="mt-6">
                <h3 className="mb-2 text-sm font-semibold text-ink">{copy.accountHeading}</h3>
                <ConnectAccountManagement />
              </div>
            </ConnectComponentsProvider>
          ) : (
            <p className="text-sm text-slate-400">{copy.loading}</p>
          )}
        </div>
      </div>
    </div>,
    document.body,
  )
}

export default StripeAccountManagementModal
