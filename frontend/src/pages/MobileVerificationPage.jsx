import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useI18n } from '@/hooks/useI18n'
import { DiditNotice } from './VerificationPage'
import { safeDiditUrl } from '@/utils/diditRedirect'
import CardSurface from '@/components/ui/CardSurface'
import Button from '@/components/ui/Button'

export default function MobileVerificationPage() {
  const { locale } = useI18n()
  const en = locale === 'en'
  const [url] = useState(() => {
    const raw = new URLSearchParams(window.location.hash.slice(1)).get('session')
    return safeDiditUrl(raw)
  })
  // Fragment never reaches the server. Remove the bearer session from history.
  useEffect(() => { window.history.replaceState(null, '', window.location.pathname) }, [])
  return <div className="container max-w-3xl pb-12">
    <CardSurface>
      <p className="text-xs font-semibold tracking-widest text-[#946d32]">CARDORA · DIDIT</p>
      <h1 className="my-4 font-display text-3xl">{en ? 'Verify on your phone' : 'Επαλήθευση από το κινητό'}</h1>
      {!url ? <p>{en ? 'Start verification from your Cardora account.' : 'Ξεκίνησε την επαλήθευση από τον λογαριασμό σου στην Cardora.'}</p> : <>
        <DiditNotice en={en} />
        <p className="my-4 text-sm">{en ? 'Continue the session you started in your own Cardora account.' : 'Συνέχισε τη συνεδρία που ξεκίνησες στον δικό σου λογαριασμό Cardora.'}</p>
        <Button onClick={() => window.location.replace(url)}>{en ? 'Continue to Didit' : 'Συνέχεια στη Didit'}</Button>
      </>}
      <Link className="mt-5 block text-sm underline" to="/kentro-ypostiriksis">{en ? 'Support' : 'Υποστήριξη'}</Link>
    </CardSurface>
  </div>
}
