import { Cookie, ShieldCheck } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import Button from '@/components/ui/Button'
import { useI18n } from '@/hooks/useI18n'
import {
  defaultCookieConsent,
  readCookieConsent,
  writeCookieConsent,
} from '@/utils/cookieConsent'

function CookieToggle({ title, description, checked, disabled, onToggle }) {
  return (
    <div className="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="text-sm font-semibold text-white">{title}</p>
          <p className="mt-1 text-xs leading-6 text-white/66">{description}</p>
        </div>
        <button
          type="button"
          disabled={disabled}
          onClick={onToggle}
          className={`relative inline-flex h-7 w-14 items-center rounded-full border transition ${
            checked
              ? 'border-gold-300/45 bg-gold-300/25'
              : 'border-white/10 bg-white/8'
          } ${disabled ? 'cursor-not-allowed opacity-70' : ''}`}
          aria-pressed={checked}
        >
          <span
            className={`inline-block h-5 w-5 rounded-full transition ${
              checked ? 'translate-x-8 bg-gold-100' : 'translate-x-1 bg-white/80'
            }`}
          />
        </button>
      </div>
    </div>
  )
}

function CookieBanner() {
  const { locale } = useI18n()
  const [open, setOpen] = useState(false)
  const [expanded, setExpanded] = useState(false)
  const [consent, setConsent] = useState(defaultCookieConsent)

  useEffect(() => {
    const existing = readCookieConsent()

    if (existing) {
      setConsent(existing)
      return
    }

    setConsent(defaultCookieConsent)
    setOpen(true)
  }, [])

  const copy = useMemo(
    () =>
      locale === 'en'
        ? {
            title: 'Cardora uses cookies and similar storage for a safer marketplace experience',
            text:
              'We use essential technologies for sign-in, cart, language, security and protected checkout flows. You can also allow preference and analytics cookies.',
            more: 'Adjust settings',
            less: 'Hide settings',
            acceptAll: 'Accept all',
            necessaryOnly: 'Necessary only',
            save: 'Save choices',
            policy: 'Cookie Policy',
            necessaryTitle: 'Necessary',
            necessaryText:
              'Required for sign-in, sessions, cart, language persistence, security and core marketplace flows.',
            preferencesTitle: 'Preferences',
            preferencesText:
              'Used to remember non-essential interface choices and make your next visit smoother.',
            analyticsTitle: 'Analytics',
            analyticsText:
              'Used to understand how pages perform and how users navigate the product, only if enabled.',
          }
        : {
            title: 'Η Cardora χρησιμοποιεί cookies και παρόμοια αποθήκευση για πιο ασφαλή εμπειρία marketplace',
            text:
              'Χρησιμοποιούμε απαραίτητες τεχνολογίες για σύνδεση, καλάθι, γλώσσα, ασφάλεια και protected checkout flows. Μπορείς επίσης να επιλέξεις αν θέλεις preference και analytics cookies.',
            more: 'Ρυθμίσεις',
            less: 'Απόκρυψη ρυθμίσεων',
            acceptAll: 'Αποδοχή όλων',
            necessaryOnly: 'Μόνο απαραίτητα',
            save: 'Αποθήκευση επιλογών',
            policy: 'Πολιτική Cookies',
            necessaryTitle: 'Απαραίτητα',
            necessaryText:
              'Χρειάζονται για σύνδεση, sessions, καλάθι, γλώσσα, ασφάλεια και βασικά marketplace flows.',
            preferencesTitle: 'Προτιμήσεις',
            preferencesText:
              'Θυμούνται μη απαραίτητες επιλογές εμφάνισης ή εμπειρίας ώστε η επόμενη επίσκεψη να είναι πιο άνετη.',
            analyticsTitle: 'Αναλυτικά στοιχεία',
            analyticsText:
              'Βοηθούν να καταλαβαίνουμε πώς αποδίδουν οι σελίδες και πώς χρησιμοποιείται το προϊόν, μόνο αν τα ενεργοποιήσεις.',
          },
    [locale],
  )

  if (!open) {
    return null
  }

  const closeWithConsent = (nextConsent) => {
    const stored = writeCookieConsent(nextConsent)
    setConsent(stored)
    setOpen(false)
  }

  return (
    <div className="pointer-events-none fixed inset-x-0 bottom-4 z-[70] px-4">
      <div className="container">
        <div className="pointer-events-auto overflow-hidden rounded-[28px] border border-gold-300/18 bg-[#091322]/96 shadow-glass backdrop-blur-xl">
          <div className="grid gap-5 p-5 lg:grid-cols-[1.1fr,0.9fr] lg:p-6">
            <div>
              <div className="flex items-center gap-3">
                <div className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-gold-300/20 bg-gold-300/10 text-gold-100">
                  <Cookie className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">Cardora cookies</p>
                  <h2 className="mt-1 text-xl font-semibold text-white">{copy.title}</h2>
                </div>
              </div>
              <p className="mt-4 text-sm leading-7 text-white/78">{copy.text}</p>
              <div className="mt-4 flex flex-wrap gap-3">
                <Button size="sm" onClick={() => closeWithConsent({ preferences: true, analytics: true })}>
                  {copy.acceptAll}
                </Button>
                <Button
                  size="sm"
                  variant="secondary"
                  onClick={() => closeWithConsent({ preferences: false, analytics: false })}
                >
                  {copy.necessaryOnly}
                </Button>
                <Button size="sm" variant="ghost" onClick={() => setExpanded((value) => !value)}>
                  {expanded ? copy.less : copy.more}
                </Button>
              </div>
            </div>

            <div className="rounded-[24px] border border-white/10 bg-white/5 p-4">
              <div className="flex items-center gap-2 text-gold-100">
                <ShieldCheck className="h-4 w-4" />
                <p className="text-[11px] uppercase tracking-[0.28em]">Privacy & control</p>
              </div>

              {expanded ? (
                <div className="mt-4 space-y-3">
                  <CookieToggle
                    title={copy.necessaryTitle}
                    description={copy.necessaryText}
                    checked
                    disabled
                  />
                  <CookieToggle
                    title={copy.preferencesTitle}
                    description={copy.preferencesText}
                    checked={consent.preferences}
                    onToggle={() =>
                      setConsent((previous) => ({ ...previous, preferences: !previous.preferences }))
                    }
                  />
                  <CookieToggle
                    title={copy.analyticsTitle}
                    description={copy.analyticsText}
                    checked={consent.analytics}
                    onToggle={() =>
                      setConsent((previous) => ({ ...previous, analytics: !previous.analytics }))
                    }
                  />

                  <div className="flex flex-wrap gap-3 pt-1">
                    <Button size="sm" onClick={() => closeWithConsent(consent)}>
                      {copy.save}
                    </Button>
                    <Button as={Link} to="/politiki-cookies" size="sm" variant="secondary">
                      {copy.policy}
                    </Button>
                  </div>
                </div>
              ) : (
                <div className="mt-4 rounded-2xl border border-white/8 bg-[#0c1628]/90 px-4 py-3 text-sm leading-7 text-white/74">
                  <Link to="/politiki-cookies" className="font-semibold text-gold-100 hover:text-gold-50">
                    {copy.policy}
                  </Link>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default CookieBanner
