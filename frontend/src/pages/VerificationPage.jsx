import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { DiditSdk } from '@didit-protocol/sdk-web'
import { QRCodeSVG } from 'qrcode.react'
import { ShieldCheck, Smartphone, CheckCircle2 } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { apiClient } from '@/services/apiClient'
import CardSurface from '@/components/ui/CardSurface'
import Button from '@/components/ui/Button'
import LegacyVerificationPage from './LegacyVerificationPage'

const labels = {
  'Not Started': ['Έτοιμος για επαλήθευση', 'Ready to verify'],
  'In Progress': ['Η επαλήθευση είναι σε εξέλιξη', 'Verification in progress'],
  'Awaiting User': ['Χρειάζεται η συνέχεια από εσένα', 'Waiting for you'],
  'In Review': ['Η επαλήθευση εξετάζεται', 'Verification under review'],
  Approved: ['Ο λογαριασμός σου είναι επαληθευμένος', 'Your account is verified'],
  Declined: ['Η επαλήθευση δεν εγκρίθηκε', 'Verification was not approved'],
  Resubmitted: ['Χρειάζεται συμπλήρωση της επαλήθευσης', 'Complete the requested verification steps'],
  Abandoned: ['Η διαδικασία δεν ολοκληρώθηκε', 'Verification was not completed'],
  Expired: ['Η συνεδρία έληξε', 'The verification session expired'],
  'Kyc Expired': ['Χρειάζεται νέα επαλήθευση', 'Verification renewal required'],
}

export function DiditNotice({ en }) {
  return <div className="space-y-3 text-sm leading-7 text-slate-600">
    <p>{en
      ? 'Cardora (VRealm I.K.E.) uses Didit to verify your identity. The workflow checks your ID document, liveness, face match and device/IP signals. Didit processes document images, facial captures and biometric data. Cardora records the session reference, result and consent, without copying new identity images or biometric templates into its database.'
      : 'Η Cardora (VRealm Ι.Κ.Ε.) χρησιμοποιεί τη Didit για την επαλήθευση της ταυτότητάς σου. Η διαδικασία ελέγχει το έγγραφο ταυτότητας, τη ζωντανή παρουσία, την αντιστοίχιση προσώπου και στοιχεία συσκευής/IP. Η Didit επεξεργάζεται εικόνες εγγράφων, λήψεις προσώπου και βιομετρικά δεδομένα. Η Cardora καταγράφει τη συνεδρία, το αποτέλεσμα και τη συγκατάθεση, χωρίς να αντιγράφει νέες εικόνες ταυτότητας ή βιομετρικά πρότυπα στη βάση της.'}</p>
    <p>{en ? 'Proof of address and bank-document uploads are not required here. Payment setup continues through Stripe.' : 'Δεν ζητείται αποδεικτικό διεύθυνσης ή ανέβασμα τραπεζικού εγγράφου εδώ. Η ρύθμιση πληρωμών συνεχίζει μέσω Stripe.'}</p>
    <p>{en ? 'Verification expires one calendar year after this session starts. A new verification is then required and deletion of the old Didit session, documents and biometric data is scheduled. Cardora keeps a limited result, consent and deletion record, not a copy of your ID.' : 'Η επαλήθευση λήγει ένα ημερολογιακό έτος από την έναρξη της συνεδρίας. Τότε απαιτείται νέα επαλήθευση και δρομολογείται η διαγραφή της παλιάς συνεδρίας Didit, των εγγράφων και των βιομετρικών δεδομένων. Η Cardora κρατά περιορισμένο αρχείο αποτελέσματος, συγκατάθεσης και διαγραφής, όχι αντίγραφο της ταυτότητάς σου.'}</p>
    <p>{en ? 'You can withdraw consent or request assistance and human review through Support. Withdrawing consent does not affect prior lawful processing; functions requiring verification may remain unavailable.' : 'Μπορείς να ανακαλέσεις τη συγκατάθεση ή να ζητήσεις βοήθεια και ανθρώπινη επανεξέταση από την Υποστήριξη. Η ανάκληση δεν επηρεάζει την προηγούμενη νόμιμη επεξεργασία· λειτουργίες που απαιτούν επαλήθευση ενδέχεται να παραμένουν μη διαθέσιμες.'}</p>
    <div className="flex flex-wrap gap-x-5 gap-y-2 underline">
      <Link to="/politiki-aporritou">{en ? 'Cardora Privacy Policy' : 'Πολιτική Απορρήτου Cardora'}</Link>
      <a href="https://didit.me/terms/verification-privacy-notice/" target="_blank" rel="noreferrer">{en ? 'Didit Privacy Notice' : 'Ενημέρωση απορρήτου Didit'}</a>
      <a href="https://didit.me/terms/identity-verification/" target="_blank" rel="noreferrer">{en ? 'Didit Verification Terms' : 'Όροι επαλήθευσης Didit'}</a>
    </div>
  </div>
}

export function EmbeddedDidit({ url, onComplete }) {
  const callback = useRef(onComplete)
  callback.current = onComplete
  useEffect(() => {
    const sdk = DiditSdk.shared
    sdk.onComplete = (result) => callback.current?.(result)
    sdk.startVerification({ url, configuration: { embedded: true, embeddedContainerId: 'cardora-didit', loggingEnabled: false } })
    return () => { sdk.onComplete = undefined; sdk.destroy() }
  }, [url])
  return <div id="cardora-didit" className="w-full overflow-hidden rounded-2xl border border-[#eadab7] bg-white" style={{ height: 'min(760px, 85dvh)', minHeight: 520 }} />
}

function DiditVerification({ initial }) {
  const { refreshCurrentUser } = useAuth()
  const { locale } = useI18n()
  const { refreshBootstrap } = useMarketplace()
  const en = locale === 'en'
  const [state, setState] = useState(initial)
  const [session, setSession] = useState(null)
  const [consent, setConsent] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const previousStatus = useRef(initial.status)
  const refreshRef = useRef(refreshBootstrap)
  refreshRef.current = refreshBootstrap
  const userRefreshRef = useRef(refreshCurrentUser)
  userRefreshRef.current = refreshCurrentUser

  useEffect(() => {
    let active = true
    let pending = false
    const poll = async () => {
      if (pending || document.hidden) return
      pending = true
      try {
        const result = await apiClient.get('/profile/verification/didit')
        if (!active) return
        setState(result.data)
        if (['Approved', 'Declined', 'Abandoned', 'Expired', 'Kyc Expired'].includes(result.data.status)) {
          setSession((current) => !current || result.data.session_id === current.session_id ? null : current)
        }
        if (result.data.status !== previousStatus.current) {
          previousStatus.current = result.data.status
          await refreshRef.current()
          await userRefreshRef.current()
        }
      } catch (e) { if (active) setError(e.message) }
      finally { pending = false }
    }
    poll()
    const interval = window.setInterval(poll, 5000)
    const reconcile = () => {
      if (!document.hidden) apiClient.post('/profile/verification/didit/refresh', {}).then(poll).catch(() => {})
    }
    reconcile()
    const fallback = window.setInterval(reconcile, 65000)
    document.addEventListener('visibilitychange', reconcile)
    return () => { active = false; clearInterval(interval); clearInterval(fallback); document.removeEventListener('visibilitychange', reconcile) }
  }, [])

  const start = async () => {
    setBusy(true); setError('')
    try {
      const result = await apiClient.post('/profile/verification/didit', { consent, notice_version: state.notice_version })
      setSession(result.data)
      setState((current) => ({ ...current, status: 'Not Started', session_id: result.data.session_id }))
    } catch (e) { setError(e.message) }
    finally { setBusy(false) }
  }
  const finish = () => {
    setSession(null)
    apiClient.post('/profile/verification/didit/refresh', {}).then((result) => {
      setState(result.data); refreshRef.current(); userRefreshRef.current()
    }).catch((e) => setError(e.message))
  }
  const mobileUrl = session ? `${window.location.origin}/${locale}/epalithefsi-kinitou#session=${encodeURIComponent(session.url)}` : ''
  const title = (labels[state.status] ?? labels['Not Started'])[en ? 1 : 0]

  return <div className="container max-w-6xl pb-16">
    <div className="mb-8 flex items-center gap-4"><ShieldCheck className="h-10 w-10 text-[#a77b38]" /><div>
      <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#946d32]">CARDORA · DIDIT</p>
      <h1 className="mt-2 font-display text-4xl text-ink">{en ? 'Account verification' : 'Επαλήθευση λογαριασμού'}</h1>
    </div></div>
    <CardSurface>
      <div role="status" aria-live="polite" className="flex items-center gap-3">
        {state.verified && <CheckCircle2 className="h-7 w-7 text-emerald-600" />}
        <h2 className="font-display text-3xl text-ink">{title}</h2>
      </div>
      {state.expires_at && <p className="mt-3 text-sm text-slate-600">{en ? 'Annual verification deadline: ' : 'Ημερομηνία ετήσιας επανεπαλήθευσης: '}{new Date(state.expires_at).toLocaleDateString(en ? 'en-GB' : 'el-GR')}</p>}
      {state.verified ? <p className="mt-4 text-sm leading-7 text-slate-600">{en ? 'Your identity verification is complete. Stripe setup and private shipping details remain available in your account.' : 'Η επαλήθευση της ταυτότητάς σου έχει ολοκληρωθεί. Η ρύθμιση Stripe και τα ιδιωτικά στοιχεία αποστολής παραμένουν διαθέσιμα στον λογαριασμό σου.'}</p> : <>
        <div className="mt-5"><DiditNotice en={en} /></div>
        {!session && state.status !== 'In Review' && <>
          <label className="mt-6 flex items-start gap-3 rounded-2xl border border-[#eadab7] bg-[#fffaf0] p-4 text-sm leading-7 text-ink">
            <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} className="mt-1.5 rounded" />
            <span>{en ? 'I have read the privacy information and explicitly consent to Didit processing my facial and biometric data to verify my identity for Cardora. I understand how to withdraw consent and request human review.' : 'Διάβασα την ενημέρωση απορρήτου και συγκατατίθεμαι ρητά στην επεξεργασία των δεδομένων προσώπου και των βιομετρικών δεδομένων μου από τη Didit για επαλήθευση ταυτότητας στην Cardora. Κατανοώ πώς μπορώ να ανακαλέσω τη συγκατάθεση και να ζητήσω ανθρώπινη επανεξέταση.'}</span>
          </label>
          <Button className="mt-5" disabled={!consent || busy || !state.available} onClick={start}>{busy ? (en ? 'Preparing…' : 'Προετοιμασία…') : (en ? 'Start / continue verification' : 'Έναρξη / συνέχεια επαλήθευσης')}</Button>
          {!state.available && <p className="mt-3 text-sm">{en ? 'Verification is temporarily unavailable. Please try again later.' : 'Η επαλήθευση δεν είναι προσωρινά διαθέσιμη. Δοκίμασε αργότερα.'}</p>}
        </>}
        {state.status === 'In Review' && <p className="mt-5 text-sm">{en ? 'You can leave this page. Your account updates when the review finishes.' : 'Μπορείς να κλείσεις τη σελίδα. Ο λογαριασμός σου θα ενημερωθεί μόλις ολοκληρωθεί ο έλεγχος.'}</p>}
      </>}
      {error && <p role="alert" className="mt-4 text-sm text-rose-700">{error}</p>}
      <div className="mt-6 flex flex-wrap gap-4 text-sm underline"><Link to="/kentro-ypostiriksis">{en ? 'Support / request human review' : 'Υποστήριξη / αίτημα επανεξέτασης'}</Link><Link to="/dashboard-politi">{en ? 'Stripe & payouts' : 'Stripe & πληρωμές'}</Link></div>
    </CardSurface>
    {session && !state.verified && <div className="mt-6 grid items-start gap-6 lg:grid-cols-[1fr_280px]">
      <EmbeddedDidit url={session.url} onComplete={finish} />
      <CardSurface className="text-center"><Smartphone className="mx-auto h-7 w-7 text-[#946d32]" />
        <h2 className="mt-3 font-display text-2xl">{en ? 'Continue on your phone' : 'Συνέχισε στο κινητό'}</h2>
        <p className="my-4 text-sm leading-6 text-slate-600">{en ? 'Scan this QR with your camera. This page will update automatically.' : 'Σκάναρε το QR με την κάμερα του κινητού. Αυτή η σελίδα ενημερώνεται αυτόματα.'}</p>
        <QRCodeSVG value={mobileUrl} size={196} marginSize={2} className="mx-auto max-w-full" title={en ? 'Continue verification on mobile' : 'Συνέχεια επαλήθευσης στο κινητό'} />
        <p className="mt-4 text-xs text-slate-500">{en ? 'This QR is private. Do not share it.' : 'Το QR είναι προσωπικό. Μην το κοινοποιείς.'}</p>
      </CardSurface>
    </div>}
  </div>
}

export default function VerificationPage() {
  const { isAuthenticated } = useAuth()
  const { accountVerification } = useMarketplace()
  if (!isAuthenticated || accountVerification?.provider !== 'didit') return <LegacyVerificationPage />
  return <DiditVerification initial={accountVerification} />
}
