import { Link } from 'react-router-dom'
import PageSeo from '@/components/PageSeo'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useI18n } from '@/hooks/useI18n'
import { localizePath } from '@/utils/helpers'
import { gradingSources } from '@/data/collectorHub'

function ConditionGuidePage() {
  const { locale } = useI18n()
  const en = locale === 'en'
  const title = en ? 'Condition and grading: a collector’s glossary' : 'Κατάσταση και grading: λεξιλόγιο συλλέκτη'
  const description = en ? 'Describe wear, packaging and professional certification separately, using the fields that apply to your collectible.' : 'Περιγράψτε χωριστά τη φθορά, τη συσκευασία και την επαγγελματική πιστοποίηση, με τα στοιχεία που αντιστοιχούν στο συλλεκτικό σας.'
  const groups = en ? [
    ['Cards', [
      ['Mint / Near Mint', 'Strong overall condition. Inspect centering, surface, corners and edges; show small marks instead of relying on the label alone. Neither description assigns a professional grade.'],
      ['Excellent / Very Good / Played', 'Descriptions of visible wear or use. Identify whitening, scratches, bends, creases or other damage and photograph them. Their extent matters more than an optimistic condition word.'],
      ['Sealed', 'The original product packaging is described as unopened. Show the seals and box, including dents or tears. Packaging damage and the condition of unseen contents are different questions.'],
    ]],
    ['Figures and other physical collectibles', [
      ['New / Sealed', 'State whether the item has been opened and whether original seals remain. Describe storage damage separately, even for an unused item.'],
      ['Open Box / Displayed', 'Explain whether the figure was removed, assembled or displayed. List the box, stand and accessories and photograph paint marks or loosened joints.'],
      ['Used / Damaged', 'Describe use, breakage, repairs and missing parts specifically. Show repaired areas and state whether the item functions or stands as intended where relevant.'],
    ]],
    ['Comics and books', [
      ['Mint / Near Mint / Very Fine / Fine', 'Describe the cover, spine, corners and pages. These seller condition labels are not a numerical CGC grade. Note writing, stains, tears, loose staples or restoration.'],
      ['Very Good / Good / Used / Reader Copy', 'Indicate reading wear and any damage or incompleteness. A reader copy should still describe missing pages or covers explicitly; do not assume a label explains every defect.'],
      ['Sealed', 'Describe the original unopened packaging and visible condition. Avoid promising the condition of pages you cannot inspect.'],
    ]],
  ] : [
    ['Κάρτες', [
      ['Mint / Near Mint', 'Περιγραφές πολύ καλής συνολικής κατάστασης. Ελέγξτε κεντράρισμα, επιφάνεια, γωνίες και άκρες. Παρουσιάστε τυχόν μικρά σημάδια στις φωτογραφίες. Καμία από τις δύο λέξεις δεν αποδίδει επαγγελματικό βαθμό στην κάρτα.'],
      ['Excellent / Very Good / Played', 'Περιγραφές ορατής φθοράς ή χρήσης. Αναφέρετε λευκά σημάδια στις άκρες, γρατσουνιές, λύγισμα, τσακίσεις ή άλλες ζημιές. Η θέση και η έκτασή τους έχουν μεγαλύτερη σημασία από μια γενική θετική διατύπωση.'],
      ['Sealed', 'Δηλώνεται κλειστή αρχική συσκευασία προϊόντος. Φωτογραφίστε σφραγίδες και κουτί, μαζί με χτυπήματα ή σκισίματα. Η φθορά της συσκευασίας και η κατάσταση του μη ορατού περιεχομένου χρειάζονται διαφορετική περιγραφή.'],
    ]],
    ['Φιγούρες και λοιπά αντικείμενα', [
      ['New / Sealed', 'Διευκρινίστε αν το αντικείμενο έχει ανοιχτεί και αν διατηρούνται οι αρχικές σφραγίδες. Περιγράψτε χωριστά τυχόν φθορές αποθήκευσης, ακόμη κι αν το ίδιο το αντικείμενο δεν έχει χρησιμοποιηθεί.'],
      ['Open Box / Displayed', 'Εξηγήστε αν η φιγούρα βγήκε από το κουτί, συναρμολογήθηκε ή εκτέθηκε. Καταγράψτε κουτί, βάση και εξαρτήματα και φωτογραφίστε σημάδια στη βαφή ή χαλαρές αρθρώσεις.'],
      ['Used / Damaged', 'Περιγράψτε συγκεκριμένα χρήση, σπασίματα, επισκευές και ελλείψεις. Δείξτε τα επισκευασμένα σημεία και αναφέρετε αν το αντικείμενο λειτουργεί ή στηρίζεται σωστά, όπου αυτό έχει σημασία.'],
    ]],
    ['Κόμικς και βιβλία', [
      ['Mint / Near Mint / Very Fine / Fine', 'Περιγράψτε εξώφυλλο, ράχη, γωνίες και σελίδες. Οι περιγραφές κατάστασης του πωλητή δεν αποτελούν αριθμητικό βαθμό CGC. Αναφέρετε γραψίματα, λεκέδες, σκισίματα, χαλαρούς συνδετήρες ή αποκατάσταση.'],
      ['Very Good / Good / Used / Reader Copy', 'Δηλώστε φθορές ανάγνωσης, ζημιές και πληρότητα. Ακόμη και σε αντίτυπο για ανάγνωση, οι σελίδες ή τα εξώφυλλα που λείπουν πρέπει να αναφέρονται ρητά. Μια σύντομη ετικέτα δεν εξηγεί κάθε ελάττωμα.'],
      ['Sealed', 'Περιγράψτε την κλειστή αρχική συσκευασία και την ορατή κατάστασή της. Αποφύγετε βεβαιώσεις για σελίδες που δεν μπορείτε να εξετάσετε.'],
    ]],
  ]
  return <div className="container space-y-9 pb-16">
    <PageSeo pageKey="condition-guide" fallbackTitle={`${title} | Cardora`} fallbackDescription={description} />
    <SectionHeader as="h1" title={title} description={description} />
    <p className="max-w-4xl text-sm leading-8 text-mist">{en ? 'Choose the condition label that best describes the actual item in its category, then explain the details in the listing. A single label cannot replace photographs. Use clear front and back views and close-ups of the areas that affect your description. State whether accessories and original packaging are included. For a group of items, disclose differences between their conditions instead of applying the best item’s label to the entire group.' : 'Επιλέξτε την περιγραφή κατάστασης που ταιριάζει στο πραγματικό αντικείμενο και στην κατηγορία του και εξηγήστε τις λεπτομέρειες στην αγγελία. Μια ετικέτα δεν αντικαθιστά τις φωτογραφίες. Χρησιμοποιήστε καθαρές λήψεις μπροστά και πίσω και κοντινά πλάνα στα σημεία που επηρεάζουν την περιγραφή σας. Δηλώστε αν περιλαμβάνονται εξαρτήματα και αρχική συσκευασία. Σε σύνολα αντικειμένων αναφέρετε τις διαφορές κατάστασης, αντί να χαρακτηρίζετε όλο το σύνολο από το καλύτερο κομμάτι.'}</p>
    {groups.map(([heading, rows]) => <section key={heading}><SectionHeader title={heading} /><div className="grid gap-4 lg:grid-cols-3">{rows.map(([term, text]) => <CardSurface key={term}><h3 className="font-semibold text-ink">{term}</h3><p className="mt-3 text-sm leading-7 text-mist">{text}</p></CardSurface>)}</div></section>)}
    <section>
      <SectionHeader title={en ? 'Professional grading is a separate attribute' : 'Η επαγγελματική βαθμολόγηση είναι ξεχωριστό στοιχείο'} />
      <p className="max-w-4xl text-sm leading-8 text-mist">{en ? 'Record the grading company, exact grade, label wording and certification number. Photograph the holder and the item inside it. PSA, Beckett and CGC publish their own standards; compare the applicable scale and category rather than treating all 10s or all labels as interchangeable. Use the company’s official certification lookup where available and compare the returned identity with the physical item. The lookup alone does not establish that the holder in a photograph is genuine. Report holder damage and any known alteration separately.' : 'Καταγράψτε εταιρεία βαθμολόγησης, ακριβή βαθμό, διατύπωση της ετικέτας και αριθμό πιστοποίησης. Φωτογραφίστε τη θήκη και το αντικείμενο μέσα της. Οι PSA, Beckett και CGC δημοσιεύουν δικά τους πρότυπα· εξετάστε την κλίμακα και την κατηγορία που εφαρμόζονται, αντί να θεωρείτε όλα τα «10» ή όλες τις ετικέτες ίδια. Χρησιμοποιήστε την επίσημη αναζήτηση πιστοποίησης της εταιρείας όπου υπάρχει και συγκρίνετε την ταυτότητα που επιστρέφει με το αντικείμενο. Η αναζήτηση μόνη της δεν αποδεικνύει τη γνησιότητα της θήκης μιας φωτογραφίας. Αναφέρετε χωριστά ζημιές στη θήκη και γνωστές επεμβάσεις.'}</p>
      <ul className="mt-4 space-y-3 text-sm">{gradingSources.map(([label, url]) => <li key={url}><a href={url} className="text-ink underline underline-offset-4">{label}</a></li>)}<li><a href="https://www.cgccomics.com/grading/grading-scale/" className="text-ink underline underline-offset-4">CGC Comics — Grading Scale</a></li></ul>
    </section>
    <div className="flex flex-wrap gap-5 text-sm"><Link className="text-ink underline" to={localizePath('/syllektika', locale)}>{en ? 'Browse collectibles' : 'Δείτε τα συλλεκτικά'}</Link><Link className="text-ink underline" to={localizePath('/odigos-polisis', locale)}>{en ? 'Prepare your listing' : 'Ετοιμάστε την αγγελία σας'}</Link></div>
  </div>
}
export default ConditionGuidePage
