import { useI18n } from '@/hooks/useI18n'

// Public operator information already published in TermsPage; no office hours or visit promises.
export default function OperatorDetails() {
  const { locale } = useI18n()
  const en = locale === 'en'
  return <dl className="mt-4 space-y-3 text-sm leading-6 text-mist">
    {[
      [en ? 'Operator' : 'Εταιρεία', en ? 'VRealm I.K.E.' : 'VRealm Ι.Κ.Ε.'],
      [en ? 'Registered address' : 'Έδρα', en ? '2 Minos Street, Athens, 117 43, Greece' : 'Μίνωος 2, Αθήνα, 117 43, Ελλάδα'],
      [en ? 'VAT / Tax ID' : 'Α.Φ.Μ.', '802264482'],
      [en ? 'G.E.MI.' : 'Γ.Ε.ΜΗ.', '173245503000'],
    ].map(([label, value]) => <div key={label}><dt className="font-semibold text-ink">{label}</dt><dd>{value}</dd></div>)}
  </dl>
}
