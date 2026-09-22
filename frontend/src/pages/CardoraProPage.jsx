import { Check, Crown } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import PageSeo from '@/components/PageSeo'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useSeo } from '@/context/SeoContext'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { cardoraService } from '@/services/cardoraService'
import { cn } from '@/utils/helpers'

function FeatureRow({ label, gold }) {
  return (
    <li className="flex items-start gap-2.5 text-sm leading-6 text-mist">
      <span
        className={cn(
          'mt-0.5 flex h-4.5 w-4.5 shrink-0 items-center justify-center rounded-full',
          gold ? 'bg-[#fff2d6] text-[#9d6a17]' : 'bg-slate-100 text-slate-400',
        )}
      >
        <Check className="h-3 w-3" strokeWidth={3} />
      </span>
      <span className={gold ? 'text-ink' : ''}>{label}</span>
    </li>
  )
}

function CardoraProPage() {
  const { locale } = useI18n()
  const seo = useSeo('cardora-pro')
  const { currentUser, isAuthenticated, refreshCurrentUser } = useAuth()
  const isEnglish = locale === 'en'

  const [status, setStatus] = useState(null)
  const [loading, setLoading] = useState(isAuthenticated)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    document.title = 'Cardora PRO'
  }, [])

  useEffect(() => {
    if (!isAuthenticated) {
      setLoading(false)
      return
    }
    setLoading(true)
    cardoraService
      .getProStatus()
      .then((data) => setStatus(data))
      .catch(() => setStatus(null))
      .finally(() => setLoading(false))
  }, [isAuthenticated])

  const copy = isEnglish
    ? {
        eyebrow: 'Cardora PRO',
        title: 'Collect smarter. Sell for less.',
        description:
          'Unlimited AI Scanner, advanced Binder tools and up to 25% lower seller fees — €6.99/month, with a 7-day free trial once per account. Cancel anytime; PRO stays active until the period you already paid for runs out.',
        freeTitle: 'Cardora FREE',
        freePrice: '€0',
        freeSub: 'forever',
        proTitle: 'Cardora PRO',
        proPrice: '€6.99',
        proSub: 'per month',
        mostPopular: 'Most popular',
        freeFeatures: [
          'Unlimited sets & cards in your Binder',
          'Owned / missing tracking',
          'Collection value & set completion',
          'AI Scanner — limited scans/month',
          'Up to 10 missing-card alerts',
          'Basic price history (latest price)',
          'Unlimited buying & selling',
          'Offers & messages',
          'Standard marketplace fees',
        ],
        proFeatures: [
          'Unlimited AI Scanner',
          'Unlimited missing-card alerts',
          'Price-drop / price-rise alerts',
          'Full price history',
          'Profit / loss vs. purchase price',
          'Advanced collection analytics',
          'Export your collection',
          'Advanced Binder statistics',
          'Lower marketplace fees (~20-25%)',
          'Bulk-list your duplicates',
          'Smart pricing suggestions',
          'Advanced seller statistics',
          '1 free featured listing / month',
          'PRO badge on your profile',
        ],
        startTrial: 'Start your 7-day free trial',
        upgrade: 'Upgrade to Cardora PRO',
        currentPlan: 'Your current plan',
        cancelCta: 'Cancel at period end',
        resumeCta: 'Resume subscription',
        signInFirst: 'Sign in to start your trial',
        activeUntil: 'PRO active until',
        cancelScheduled: 'Your PRO will end on',
        proBadgeLabel: "You're on PRO",
        trialUsed: 'Free trial already used on this account',
        feesTitle: 'Cardora PRO seller fees',
        feesDescription: 'Same tiers as FREE, roughly 20-25% lower — the more you sell, the more PRO pays for itself.',
        feeRows: [
          ['Up to €5', '€1.00', '€0.75'],
          ['€5 – €300', '6.5%', '5%'],
          ['€300 – €2,000', '5%', '4%'],
          ['€2,000+', '4%', '3%'],
        ],
        feeHeaderPrice: 'Sale price',
        feeHeaderFree: 'FREE',
        feeHeaderPro: 'PRO',
      }
    : {
        eyebrow: 'Cardora PRO',
        title: 'Συλλέγε πιο έξυπνα. Πούλα με λιγότερο κόστος.',
        description:
          'Απεριόριστο AI Scanner, προηγμένα εργαλεία Binder και έως 25% χαμηλότερες προμήθειες — €6,99/μήνα, με δωρεάν δοκιμή 7 ημερών μία φορά ανά λογαριασμό. Ακύρωσε όποτε θες — το PRO μένει ενεργό μέχρι να λήξει η περίοδος που έχεις ήδη πληρώσει.',
        freeTitle: 'Cardora FREE',
        freePrice: '€0',
        freeSub: 'για πάντα',
        proTitle: 'Cardora PRO',
        proPrice: '€6,99',
        proSub: 'τον μήνα',
        mostPopular: 'Πιο δημοφιλές',
        freeFeatures: [
          'Απεριόριστα sets & κάρτες στο Binder',
          'Owned / Missing tracking',
          'Αξία συλλογής & set completion',
          'AI Scanner — περιορισμένα scans/μήνα',
          'Έως 10 missing-card alerts',
          'Βασικό price history (τελευταία τιμή)',
          'Απεριόριστη αγορά & πώληση',
          'Offers & μηνύματα',
          'Κανονικές προμήθειες marketplace',
        ],
        proFeatures: [
          'Unlimited AI Scanner',
          'Unlimited missing-card alerts',
          'Price-drop / price-rise alerts',
          'Πλήρες price history',
          'Profit / Loss vs. τιμή αγοράς',
          'Advanced collection analytics',
          'Export της συλλογής σου',
          'Advanced Binder statistics',
          'Χαμηλότερες προμήθειες (~20-25%)',
          'Bulk-listing των duplicates',
          'Έξυπνες προτάσεις τιμής',
          'Advanced seller statistics',
          '1 δωρεάν featured listing / μήνα',
          'PRO badge στο προφίλ σου',
        ],
        startTrial: 'Ξεκίνα τη δωρεάν δοκιμή 7 ημερών',
        upgrade: 'Αναβάθμιση σε Cardora PRO',
        currentPlan: 'Το τρέχον πλάνο σου',
        cancelCta: 'Ακύρωση στο τέλος της περιόδου',
        resumeCta: 'Επανενεργοποίηση συνδρομής',
        signInFirst: 'Συνδέσου για να ξεκινήσεις τη δοκιμή',
        activeUntil: 'Το PRO είναι ενεργό μέχρι',
        cancelScheduled: 'Το PRO σου θα λήξει στις',
        proBadgeLabel: 'Είσαι σε PRO',
        trialUsed: 'Η δωρεάν δοκιμή έχει ήδη χρησιμοποιηθεί σε αυτόν τον λογαριασμό',
        feesTitle: 'Προμήθειες πωλητή Cardora PRO',
        feesDescription:
          'Ίδιες κλίμακες με το FREE, περίπου 20-25% χαμηλότερες — όσο πουλάς πιο συχνά, τόσο πιο εύκολα βγάζεις πίσω τα €6,99.',
        feeRows: [
          ['Έως €5', '€1,00', '€0,75'],
          ['€5 – €300', '6,5%', '5%'],
          ['€300 – €2.000', '5%', '4%'],
          ['€2.000+', '4%', '3%'],
        ],
        feeHeaderPrice: 'Τιμή πώλησης',
        feeHeaderFree: 'FREE',
        feeHeaderPro: 'PRO',
      }

  const isPro = Boolean(currentUser?.isPro)
  const cancelAtPeriodEnd = Boolean(status?.cancelAtPeriodEnd)
  const trialAvailable = status?.trialAvailable ?? true
  const currentPeriodEnd = status?.currentPeriodEnd
    ? new Date(status.currentPeriodEnd).toLocaleDateString(isEnglish ? 'en-US' : 'el-GR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      })
    : null

  const handleUpgrade = async () => {
    setError(null)
    setBusy(true)
    try {
      const result = await cardoraService.startProCheckout()
      if (result?.checkout_url) {
        window.location.href = result.checkout_url
        return
      }
      throw new Error('missing checkout url')
    } catch (err) {
      setError(err?.message || (isEnglish ? 'Could not start checkout.' : 'Δεν ξεκίνησε το checkout.'))
      setBusy(false)
    }
  }

  const handleCancel = async () => {
    setError(null)
    setBusy(true)
    try {
      await cardoraService.cancelProSubscription()
      setStatus(await cardoraService.getProStatus())
    } catch (err) {
      setError(err?.message || 'Error')
    } finally {
      setBusy(false)
    }
  }

  const handleResume = async () => {
    setError(null)
    setBusy(true)
    try {
      await cardoraService.resumeProSubscription()
      setStatus(await cardoraService.getProStatus())
      await refreshCurrentUser?.()
    } catch (err) {
      setError(err?.message || 'Error')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="container pb-16">
      <PageSeo pageKey="cardora-pro" fallbackTitle={copy.title} fallbackDescription={copy.description} />
      <SectionHeader eyebrow={copy.eyebrow} title={seo?.h1 || copy.title} description={copy.description} />

      {error ? (
        <div className="mb-5 rounded-2xl border border-rose-300/50 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</div>
      ) : null}

      {isAuthenticated && isPro ? (
        <CardSurface className="featured-glow mb-8">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-center gap-3">
              <div className="flex h-11 w-11 items-center justify-center rounded-2xl border border-[#d7b57b]/70 bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_48%,#c79d62_100%)] shadow-[0_10px_24px_rgba(199,157,98,0.3)]">
                <Crown className="h-5 w-5 text-[#5a3a13]" />
              </div>
              <div>
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#9d6a17]">{copy.currentPlan}</p>
                <p className="mt-1 font-display text-xl text-ink">{copy.proBadgeLabel}</p>
                {currentPeriodEnd ? (
                  <p className="mt-1 text-xs text-mist">
                    {cancelAtPeriodEnd ? copy.cancelScheduled : copy.activeUntil} {currentPeriodEnd}
                  </p>
                ) : null}
              </div>
            </div>
            <div>
              {cancelAtPeriodEnd ? (
                <Button variant="secondary" onClick={handleResume} disabled={busy}>
                  {copy.resumeCta}
                </Button>
              ) : (
                <Button variant="ghost" onClick={handleCancel} disabled={busy}>
                  {copy.cancelCta}
                </Button>
              )}
            </div>
          </div>
        </CardSurface>
      ) : null}

      <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
        <CardSurface className="h-full">
          <p className="text-xs font-semibold uppercase tracking-[0.28em] text-slate-400">{copy.freeTitle}</p>
          <p className="mt-3 font-display text-4xl text-ink">
            {copy.freePrice} <span className="text-sm font-sans font-normal text-mist">{copy.freeSub}</span>
          </p>
          <ul className="mt-6 space-y-3">
            {copy.freeFeatures.map((label) => (
              <FeatureRow key={label} label={label} />
            ))}
          </ul>
        </CardSurface>

        <CardSurface className="featured-glow relative h-full">
          <span className="absolute -top-3 left-5 inline-flex items-center rounded-full border border-[#d8b06a] bg-[linear-gradient(145deg,#f7ebd1_0%,#ecd3a2_48%,#c79d62_100%)] px-3 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-[#5a3a13] shadow-[0_8px_18px_rgba(199,157,98,0.35)]">
            {copy.mostPopular}
          </span>
          <p className="text-xs font-semibold uppercase tracking-[0.28em] text-[#9d6a17]">{copy.proTitle}</p>
          <p className="mt-3 font-display text-4xl text-ink">
            {copy.proPrice} <span className="text-sm font-sans font-normal text-mist">{copy.proSub}</span>
          </p>
          <ul className="mt-6 space-y-3">
            {copy.proFeatures.map((label) => (
              <FeatureRow key={label} label={label} gold />
            ))}
          </ul>

          <div className="mt-7">
            {!isAuthenticated ? (
              <Button as={Link} to="/eisodos" className="w-full justify-center">
                {copy.signInFirst}
              </Button>
            ) : isPro ? null : loading ? (
              <div className="h-11 animate-pulse rounded-xl bg-[#f3e9d3]" />
            ) : (
              <>
                <Button onClick={handleUpgrade} disabled={busy} className="w-full justify-center">
                  {trialAvailable ? copy.startTrial : copy.upgrade}
                </Button>
                {!trialAvailable ? <p className="mt-2 text-center text-[11px] text-slate-400">{copy.trialUsed}</p> : null}
              </>
            )}
          </div>
        </CardSurface>
      </div>

      <div className="mt-12">
        <h3 className="font-display text-2xl text-ink">{copy.feesTitle}</h3>
        <p className="mt-1.5 max-w-2xl text-sm leading-6 text-mist">{copy.feesDescription}</p>

        <CardSurface className="mt-4 overflow-x-auto">
          <table className="w-full min-w-[420px] text-sm">
            <thead>
              <tr className="text-left text-xs uppercase tracking-wide text-slate-400">
                <th className="pb-3 pr-4 font-semibold">{copy.feeHeaderPrice}</th>
                <th className="pb-3 pr-4 font-semibold">{copy.feeHeaderFree}</th>
                <th className="pb-3 font-semibold text-[#9d6a17]">{copy.feeHeaderPro}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#eee2c4]">
              {copy.feeRows.map((row) => (
                <tr key={row[0]}>
                  <td className="py-3 pr-4 text-ink">{row[0]}</td>
                  <td className="py-3 pr-4 text-slate-500">{row[1]}</td>
                  <td className="py-3 font-semibold text-[#9d6a17]">{row[2]}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </CardSurface>
      </div>
    </div>
  )
}

export default CardoraProPage
