import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useI18n } from '@/hooks/useI18n'
import { DiditNotice, EmbeddedDidit } from './VerificationPage'
import CardSurface from '@/components/ui/CardSurface'
import Button from '@/components/ui/Button'

export default function MobileVerificationPage() {
  const { locale } = useI18n()
  const en = locale === 'en'
  const [started, setStarted] = useState(false)
  const [complete, setComplete] = useState(false)
  const [url] = useState(() => {
    const raw = new URLSearchParams(window.location.hash.slice(1)).get('session')
    try {
      const parsed = new URL(raw)
      return parsed.protocol === 'https:' && parsed.hostname === 'verify.didit.me' && !parsed.username && !parsed.password && !parsed.port ? parsed.href : null
    } catch { return null }
  })
  // Fragment never reaches the server. Remove the bearer session from history.
  useEffect(() => { window.history.replaceState(null, '', window.location.pathname) }, [])
  return <div className="container max-w-3xl pb-12">
    <CardSurface>
      <p className="text-xs font-semibold tracking-widest text-[#946d32]">CARDORA · DIDIT</p>
      <h1 className="my-4 font-display text-3xl">{en ? 'Verify on your phone' : 'Επαλήθευση από το κινητό'}</h1>
      {!url ? <p>{en ? 'Scan the QR again from your Cardora account.' : 'Σκάναρε ξανά το QR από τον λογαριασμό σου στην Cardora.'}</p> : !started ? <>
        <DiditNotice en={en} />
        <p className="my-4 text-sm">{en ? 'Continue the session you started in your own Cardora account.' : 'Συνέχισε τη συνεδρία που ξεκίνησες στον δικό σου λογαριασμό Cardora.'}</p>
        <Button onClick={() => setStarted(true)}>{en ? 'Continue verification' : 'Συνέχεια επαλήθευσης'}</Button>
      </> : <>
        {complete && <p role="status" className="mb-4 text-sm">{en ? 'The verification step finished. Your Cardora account will show the confirmed result.' : 'Το βήμα επαλήθευσης ολοκληρώθηκε. Ο λογαριασμός σου στην Cardora θα εμφανίσει το επιβεβαιωμένο αποτέλεσμα.'}</p>}
        <EmbeddedDidit url={url} onComplete={(result) => setComplete(result.type === 'completed')} />
      </>}
      <Link className="mt-5 block text-sm underline" to="/kentro-ypostiriksis">{en ? 'Support' : 'Υποστήριξη'}</Link>
    </CardSurface>
  </div>
}
