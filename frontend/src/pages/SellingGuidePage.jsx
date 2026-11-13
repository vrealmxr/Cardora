import { Link } from 'react-router-dom'
import PageSeo from '@/components/PageSeo'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useI18n } from '@/hooks/useI18n'
import { localizePath } from '@/utils/helpers'

function SellingGuidePage() {
  const { locale } = useI18n()
  const en = locale === 'en'
  const path = value => localizePath(value, locale)
  const title = en ? 'Sell your collectibles on Cardora' : 'Πώληση συλλεκτικών στην Cardora'
  const intro = en ? 'Prepare a clear listing, understand the charges and follow your order through shipping and payment release.' : 'Ετοιμάστε μια σαφή αγγελία, γνωρίστε τις χρεώσεις και παρακολουθήστε την παραγγελία σας από την αποστολή μέχρι την αποδέσμευση πληρωμής.'
  const steps = en ? [
    ['Identify the item', 'Choose the correct category and give the item a precise title. For a card, include the game or player, set, collector number, language and confirmed variant. For figures and comics, state the manufacturer or publisher, edition and what is included. A named edition helps buyers compare the right items.'],
    ['Photograph and describe it', 'Use photographs of the actual item, including front, back, packaging and close-ups of imperfections. Describe wear, missing accessories, repairs and restoration where relevant. Keep a seller’s condition description separate from professional grading. If the item has a certification, state its company, grade and number exactly as shown.'],
    ['Set a price and shipping options', 'Compare items of the same edition and condition. An asking price is not evidence of a completed sale. Check the cost and packaging needed for your parcel, then review the delivery options offered for the listing. State clearly whether you are selling a single item or a group and list the contents.'],
    ['Prepare the account and follow the order', 'Complete the account steps shown in your seller tools and connect the payout account requested by the platform. After a paid order, follow its shipping instructions and keep tracking information and messages on Cardora. Inspect the fee breakdown and the order’s release status. Bank payout timing is a separate stage from order release.'],
  ] : [
    ['Αναγνωρίστε το αντικείμενο', 'Επιλέξτε τη σωστή κατηγορία και γράψτε έναν ακριβή τίτλο. Για κάρτα αναφέρετε παιχνίδι ή παίκτη, set, αριθμό, γλώσσα και επιβεβαιωμένη παραλλαγή. Για φιγούρες και κόμικς δηλώστε κατασκευαστή ή εκδότη, έκδοση και όσα περιλαμβάνονται. Η συγκεκριμένη ονομασία βοηθά τον αγοραστή να συγκρίνει τα σωστά αντικείμενα.'],
    ['Φωτογραφίστε και περιγράψτε', 'Χρησιμοποιήστε φωτογραφίες του πραγματικού αντικειμένου: μπροστά, πίσω, συσκευασία και κοντινά πλάνα ατελειών. Αναφέρετε φθορές, ελλείψεις, επισκευές και αποκατάσταση όπου υπάρχουν. Κρατήστε χωριστά την περιγραφή κατάστασης από το επαγγελματικό grading. Αν υπάρχει πιστοποίηση, αντιγράψτε σωστά την εταιρεία, τον βαθμό και τον αριθμό που αναγράφονται στην ετικέτα.'],
    ['Ορίστε τιμή και αποστολή', 'Συγκρίνετε αντικείμενα ίδιας έκδοσης και κατάστασης. Μια ζητούμενη τιμή δεν αποδεικνύει ολοκληρωμένη πώληση. Εξετάστε κόστος και συσκευασία για το δέμα σας και ελέγξτε τις επιλογές παράδοσης της αγγελίας. Διευκρινίστε αν πουλάτε ένα αντικείμενο ή σύνολο και καταγράψτε αναλυτικά το περιεχόμενο, ώστε να είναι σαφές τι θα παραλάβει ο αγοραστής.'],
    ['Ετοιμάστε τον λογαριασμό και παρακολουθήστε την παραγγελία', 'Ολοκληρώστε τα βήματα που εμφανίζονται στα εργαλεία πωλητή και συνδέστε τον λογαριασμό πληρωμών που ζητά η πλατφόρμα. Μετά την πληρωμή μιας παραγγελίας, ακολουθήστε τις οδηγίες αποστολής και κρατήστε την παρακολούθηση και τα μηνύματα στην Cardora. Ελέγξτε την ανάλυση χρεώσεων και την κατάσταση αποδέσμευσης. Η πίστωση στην τράπεζα αποτελεί ξεχωριστό στάδιο.'],
  ]
  const fees = en ? [
    ['Up to and including €5', '€1 added to buyer total', '€0.75 added to buyer total'],
    ['Over €5, up to €300', '6.5%', '5%'], ['Over €300, up to €2,000', '5%', '4%'], ['Over €2,000', '4%', '3%'],
  ] : [
    ['Έως και 5 €', '1 € στο σύνολο του αγοραστή', '0,75 € στο σύνολο του αγοραστή'],
    ['Πάνω από 5 €, έως 300 €', '6,5%', '5%'], ['Πάνω από 300 €, έως 2.000 €', '5%', '4%'], ['Πάνω από 2.000 €', '4%', '3%'],
  ]
  return <div className="container space-y-10 pb-16">
    <PageSeo pageKey="selling-guide" fallbackTitle={`${title} | Cardora`} fallbackDescription={intro} />
    <SectionHeader as="h1" title={title} description={intro} />
    <div className="grid gap-6 md:grid-cols-2">{steps.map(([heading, text], index) => <CardSurface key={heading}>
      <h2 className="font-display text-3xl text-ink">{index + 1}. {heading}</h2><p className="mt-4 text-sm leading-7 text-mist">{text}</p>
    </CardSurface>)}</div>
    <section>
      <SectionHeader title={en ? 'Marketplace charges' : 'Χρεώσεις marketplace'} description={en ? 'The tier is based on the order’s item value, excluding shipping. Percentage rates apply to that whole value, not progressively to each band.' : 'Η κλίμακα υπολογίζεται στην αξία προϊόντων της παραγγελίας, χωρίς τα μεταφορικά. Το ποσοστό εφαρμόζεται σε ολόκληρη αυτή την αξία, όχι προοδευτικά ανά κλιμάκιο.'} />
      <div className="overflow-x-auto rounded-2xl border border-gold-200/40"><table className="w-full bg-white text-left text-sm">
        <caption className="sr-only">{en ? 'Standard and Cardora Pro charges' : 'Σύγκριση χρεώσεων Standard και Cardora Pro'}</caption>
        <thead><tr>{[en ? 'Item value' : 'Αξία προϊόντων', 'Standard', 'Cardora Pro'].map(text => <th scope="col" key={text} className="p-4 text-ink">{text}</th>)}</tr></thead>
        <tbody>{fees.map(([tier, regular, pro]) => <tr key={tier} className="border-t border-gold-200/30"><th scope="row" className="p-4 font-medium text-ink">{tier}</th><td className="p-4 text-mist">{regular}</td><td className="p-4 text-mist">{pro}</td></tr>)}</tbody>
      </table></div>
      <p className="mt-4 text-sm leading-7 text-mist">{en ? 'The percentage commission is capped at €400 and deducted from the seller’s item proceeds. In the tier up to €5, the seller keeps the item value and the fixed fee is added to the buyer’s total. Cardora Pro rates require active Pro status for the seller. Shipping and any separately displayed charges are reviewed in checkout and the order breakdown.' : 'Η ποσοστιαία προμήθεια έχει ανώτατο όριο 400 € και αφαιρείται από την αξία προϊόντων που λαμβάνει ο πωλητής. Στην κλίμακα έως 5 €, ο πωλητής κρατά την αξία προϊόντων και η σταθερή χρέωση προστίθεται στο σύνολο του αγοραστή. Οι τιμές Cardora Pro προϋποθέτουν ενεργό Pro στον πωλητή. Ελέγξτε τα μεταφορικά και όποιες επιπλέον χρεώσεις εμφανίζονται χωριστά στο checkout και στην ανάλυση παραγγελίας.'}</p>
      <p className="mt-3 text-sm leading-7 text-mist">{en ? 'Example: on €100 of item value, the Standard commission is €6.50 and the item proceeds are €93.50 before any separate adjustments. With active Pro, they are €5 and €95 respectively. On a €4 item order, the item value remains €4 for the seller; the buyer sees the applicable €1 or €0.75 fixed fee separately, alongside shipping.' : 'Παράδειγμα: σε αξία προϊόντων 100 €, η Standard προμήθεια είναι 6,50 € και το ποσό προϊόντων για τον πωλητή 93,50 €, πριν από τυχόν ξεχωριστές προσαρμογές. Με ενεργό Pro είναι αντίστοιχα 5 € και 95 €. Σε παραγγελία προϊόντων 4 €, ο πωλητής κρατά τα 4 € και ο αγοραστής βλέπει χωριστά τη σταθερή χρέωση 1 € ή 0,75 €, μαζί με τα μεταφορικά.'}</p>
    </section>
    <section>
      <SectionHeader title={en ? 'When is the seller paid?' : 'Πότε πληρώνεται ο πωλητής;'} description={en ? 'After delivery, the order follows its buyer-review and release process. A reported problem may require review before release. Once funds have been released to the connected seller account, the bank payout follows that account’s payout status and schedule. Use the information shown in your order and seller account; do not treat a release confirmation as a promise of same-day bank credit.' : 'Μετά την παράδοση, η παραγγελία ακολουθεί τη διαδικασία ελέγχου από τον αγοραστή και αποδέσμευσης. Ένα αναφερόμενο πρόβλημα μπορεί να χρειάζεται εξέταση πριν από την αποδέσμευση. Αφού αποδεσμευτούν τα χρήματα στον συνδεδεμένο λογαριασμό πωλητή, η τραπεζική κατάθεση ακολουθεί την κατάσταση και το πρόγραμμά του. Συμβουλευτείτε την παραγγελία και τον λογαριασμό σας· η επιβεβαίωση αποδέσμευσης δεν σημαίνει απαραίτητα πίστωση στην τράπεζα την ίδια ημέρα.'} />
      <div className="flex flex-wrap gap-4 text-sm"><Link className="text-ink underline" to={path('/epistrofes-kai-diafores')}>{en ? 'Refunds and disputes' : 'Επιστροφές και διαφορές'}</Link><Link className="text-ink underline" to={path('/oroi-xrisis')}>{en ? 'Terms of Use' : 'Όροι Χρήσης'}</Link><Link className="text-ink underline" to={path('/kentro-ypostiriksis')}>{en ? 'Support' : 'Υποστήριξη'}</Link></div>
    </section>
    <div className="flex flex-wrap gap-3"><Button as={Link} to={path('/dimiourgia-aggelias')}>{en ? 'Create a listing' : 'Δημιουργία αγγελίας'}</Button><Button as={Link} to={path('/syllektika')} variant="secondary">{en ? 'Browse collectibles' : 'Δείτε τα συλλεκτικά'}</Button></div>
  </div>
}
export default SellingGuidePage
