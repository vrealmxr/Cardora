import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { apiClient } from '@/services/apiClient'
import { diditResultKind } from '@/utils/diditRedirect'
import CardSurface from '@/components/ui/CardSurface'

const messages = {
  checking: ['Ελέγχουμε το αποτέλεσμα της επαλήθευσης…', 'Checking your verification result…'],
  success: ['Η επαλήθευση ολοκληρώθηκε επιτυχώς!', 'Verification completed successfully!'],
  failure: ['Η επαλήθευση δεν εγκρίθηκε ή δεν ολοκληρώθηκε.', 'Verification was declined or was not completed.'],
  pending: ['Η επαλήθευσή σου βρίσκεται υπό επεξεργασία.', 'Your verification is being processed.'],
  action: ['Χρειάζονται επιπλέον ενέργειες για την επαλήθευσή σου.', 'Your verification needs additional steps.'],
  mismatch: ['Αυτή η επιστροφή δεν αντιστοιχεί στην τρέχουσα επαλήθευση του λογαριασμού σου.', 'This return does not match your account’s current verification.'],
}

export default function VerificationResultPage() {
  const { isAuthReady, isAuthenticated, refreshCurrentUser } = useAuth()
  const { refreshBootstrap } = useMarketplace()
  const { locale } = useI18n()
  const en = locale === 'en'
  const [returnedId] = useState(() => new URLSearchParams(window.location.search).get('verificationSessionId'))
  const [state, setState] = useState(null)
  const [error, setError] = useState('')
  const refreshers = useRef({ refreshBootstrap, refreshCurrentUser })
  refreshers.current = { refreshBootstrap, refreshCurrentUser }
  const kind = diditResultKind(state, returnedId)

  useEffect(() => {
    // Neither the URL status nor the session id can grant verification.
    window.history.replaceState(window.history.state, '', window.location.pathname)
  }, [])

  useEffect(() => {
    if (!isAuthReady || !isAuthenticated) return undefined
    let active = true
    let pending = false
    let lastReconcile = 0
    let lastStatus = null
    const poll = async () => {
      if (pending || document.hidden) return
      pending = true
      try {
        const reconcile = Date.now() - lastReconcile > 65000
        if (reconcile) lastReconcile = Date.now()
        const result = reconcile
          ? await apiClient.post('/profile/verification/didit/refresh', {})
          : await apiClient.get('/profile/verification/didit')
        if (!active) return
        setState(result.data)
        setError('')
        if (lastStatus !== result.data.status) {
          lastStatus = result.data.status
          await Promise.all([refreshers.current.refreshBootstrap(), refreshers.current.refreshCurrentUser()])
        }
      } catch (exception) {
        if (active) setError(exception.message)
      } finally { pending = false }
    }
    poll()
    const timer = window.setInterval(poll, 5000)
    document.addEventListener('visibilitychange', poll)
    return () => { active = false; window.clearInterval(timer); document.removeEventListener('visibilitychange', poll) }
  }, [isAuthReady, isAuthenticated])

  return <div className="container max-w-3xl pb-16">
    <CardSurface>
      <p className="text-xs font-semibold tracking-widest text-[#946d32]">CARDORA · DIDIT</p>
      <h1 className="my-5 font-display text-3xl">{en ? 'Verification result' : 'Αποτέλεσμα επαλήθευσης'}</h1>
      {isAuthReady && !isAuthenticated ? <>
        <p className="leading-7">{en ? 'If you completed the steps on your phone, return to the original device where you started verification to see the confirmed result. You do not need to sign in on this device.' : 'Αν ολοκλήρωσες τα βήματα στο κινητό, επέστρεψε στην αρχική συσκευή από όπου ξεκίνησες την επαλήθευση για να δεις το επιβεβαιωμένο αποτέλεσμα. Δεν χρειάζεται να συνδεθείς σε αυτή τη συσκευή.'}</p>
      </> : <>
        <p role="status" aria-live="polite" className={`rounded-2xl p-5 text-lg ${kind === 'success' ? 'bg-emerald-50 text-emerald-800' : kind === 'failure' ? 'bg-rose-50 text-rose-800' : 'bg-amber-50 text-amber-900'}`}>{messages[kind][en ? 1 : 0]}</p>
        {kind === 'pending' && <p className="mt-4 leading-7">{en ? 'This page updates automatically. You can also return later; your account will update when a confirmed decision arrives.' : 'Η σελίδα ενημερώνεται αυτόματα. Μπορείς επίσης να επιστρέψεις αργότερα· ο λογαριασμός σου θα ενημερωθεί όταν ληφθεί επιβεβαιωμένη απόφαση.'}</p>}
        {error && <p role="alert" className="mt-4 text-rose-700">{en ? 'We could not refresh the result. Retrying…' : 'Δεν μπορέσαμε να ανανεώσουμε το αποτέλεσμα. Γίνεται νέα προσπάθεια…'}</p>}
        <div className="mt-6 flex flex-wrap gap-5 underline">
          <Link to={`/${locale}/profil`}>{en ? 'My account' : 'Ο λογαριασμός μου'}</Link>
          <Link to={`/${locale}/epalithefsi-logariasmou`}>{en ? 'Verification details / continue' : 'Λεπτομέρειες / συνέχεια επαλήθευσης'}</Link>
          <Link to={`/${locale}/kentro-ypostiriksis`}>{en ? 'Support' : 'Υποστήριξη'}</Link>
        </div>
      </>}
    </CardSurface>
  </div>
}
