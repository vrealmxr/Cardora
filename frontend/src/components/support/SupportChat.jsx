import { useEffect, useRef, useState } from 'react'
import { MessageCircle, X } from 'lucide-react'
import { Link } from 'react-router-dom'
import { useI18n } from '@/hooks/useI18n'
import Button from '@/components/ui/Button'
import { getSupportChat } from '@/utils/supportChat'

export default function SupportChat() {
  const { locale } = useI18n()
  const en = locale === 'en'
  const [open, setOpen] = useState(false)
  const [state, setState] = useState('idle')
  const launcher = useRef(null)
  const closeButton = useRef(null)
  const close = () => { setOpen(false); launcher.current?.focus() }
  useEffect(() => {
    if (open) closeButton.current?.focus()
  }, [open])
  async function start() {
    setState('loading')
    try { await getSupportChat().open(locale); setState('ready'); setOpen(false) }
    catch (error) { setState(['preview', 'reload'].includes(error.message) ? error.message : 'error') }
  }
  // The existing SDK owns its launcher once the visitor requests the service.
  if (state === 'ready') return null
  return <div className="fixed bottom-4 right-4 z-[80] max-w-[calc(100vw-2rem)]">
    {open && <section role="dialog" aria-label={en ? 'Contact support' : 'Επικοινωνία με την υποστήριξη'}
      onKeyDown={event => { if (event.key === 'Escape' && state !== 'loading') close() }}
      className="mb-3 w-80 max-w-full rounded-2xl border border-[#ead7ae] bg-white p-5 text-sm leading-6 text-slate-700 shadow-xl">
      <div className="mb-3 flex items-center justify-between gap-3">
        <h2 className="font-semibold text-ink">{en ? 'How can we help?' : 'Πώς μπορούμε να βοηθήσουμε;'}</h2>
        <button ref={closeButton} type="button" onClick={close} disabled={state === 'loading'}
          aria-label={en ? 'Close support options' : 'Κλείσιμο επιλογών υποστήριξης'} className="rounded p-1 disabled:opacity-40"><X size={20} /></button>
      </div>
      <p>{en
        ? 'Chat is provided by Chatwoot. Opening it connects you to the service, which uses cookies and receives technical information about your visit to provide support.'
        : 'Η συνομιλία παρέχεται από το Chatwoot. Όταν την ανοίγεις, συνδέεσαι στην υπηρεσία, η οποία χρησιμοποιεί cookies και λαμβάνει τεχνικά στοιχεία της επίσκεψής σου για την υποστήριξη.'}</p>
      <Link to={`/${locale}/politiki-aporritou`} className="mt-2 inline-block underline">{en ? 'Privacy policy' : 'Πολιτική απορρήτου'}</Link>
      <p className="mt-3">{en ? 'You can also email us at ' : 'Μπορείς επίσης να μας γράψεις στο '}<a href="mailto:support@cardora.gr" className="underline">support@cardora.gr</a>.</p>
      <Button className="mt-4 w-full" onClick={start} disabled={['loading', 'preview', 'reload'].includes(state)}>
        {state === 'loading' ? (en ? 'Opening chat…' : 'Άνοιγμα συνομιλίας…') : (en ? 'Open chat' : 'Άνοιγμα συνομιλίας')}
      </Button>
      {['error', 'preview', 'reload'].includes(state) && <p role="status" className="mt-3">
        {state === 'preview'
          ? (en ? 'Live chat is disabled in this preview. No connection was made.' : 'Η ζωντανή συνομιλία είναι απενεργοποιημένη στην προεπισκόπηση. Δεν έγινε σύνδεση.')
          : state === 'reload'
            ? (en ? 'Chat could not open. Refresh the page to try again, or email us.' : 'Η συνομιλία δεν άνοιξε. Ανανέωσε τη σελίδα για νέα προσπάθεια ή γράψε μας με email.')
            : (en ? 'Chat could not open. Please try again or email us.' : 'Η συνομιλία δεν άνοιξε. Δοκίμασε ξανά ή γράψε μας με email.')}
      </p>}
    </section>}
    <button ref={launcher} type="button" onClick={() => open ? close() : setOpen(true)} aria-expanded={open}
      disabled={state === 'loading'} className="ml-auto flex items-center gap-2 rounded-full border border-[#d7b57b] bg-[#f7ebd1] px-4 py-3 text-sm font-semibold text-[#5a3a13] shadow-lg disabled:opacity-50">
      <MessageCircle size={20} />{en ? 'Support' : 'Υποστήριξη'}
    </button>
  </div>
}
