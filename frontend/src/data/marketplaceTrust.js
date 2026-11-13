// Descriptive customer copy reconciled with the live checkout/release services.
// No absolute protection, item-authenticity or bank-payout guarantees.
export const marketplaceTrust = {
  el: {
    description: 'Αγόρασε και πούλησε κάρτες, φιγούρες, κόμικς και άλλα συλλεκτικά. Σύγκρινε φωτογραφίες και περιγραφές και δες το συνολικό κόστος πριν πληρώσεις.',
    title: 'Πληρωμές και παρακολούθηση παραγγελίας',
    payment: 'Οι πληρωμές γίνονται μέσω Stripe. Μετά την παράδοση ακολουθεί ο έλεγχος της παραγγελίας και η διαδικασία αποδέσμευσης. Αν υπάρχει πρόβλημα, ανέφερέ το μέσα στην προθεσμία που εμφανίζεται στην παραγγελία σου.',
    highlights: ['Φωτογραφίες και περιγραφή κατάστασης', 'Συνολικό κόστος πριν την πληρωμή', 'Ιστορικό μηνυμάτων και παραγγελίας', 'Υποστήριξη για αναφορά προβλήματος'],
    paymentPoints: ['Πληρωμή μέσω Stripe', 'Έλεγχος μετά την παράδοση', 'Χωριστή ενημέρωση για την τραπεζική κατάθεση'],
    verification: 'Η επαλήθευση αφορά τον λογαριασμό, όχι πιστοποίηση γνησιότητας κάθε αντικειμένου.',
    steps: [
      { id: 'choose', title: 'Διάλεξε το σωστό αντικείμενο', text: 'Σύγκρινε έκδοση, κατάσταση και φωτογραφίες. Ζήτησε διευκρινίσεις από τον πωλητή πριν αγοράσεις.' },
      { id: 'pay', title: 'Έλεγξε το σύνολο και πλήρωσε', text: 'Δες την αξία των αντικειμένων, τα μεταφορικά και τυχόν χρεώσεις πριν ολοκληρώσεις την πληρωμή μέσω Stripe.' },
      { id: 'receive', title: 'Παρακολούθησε και έλεγξε την παραγγελία', text: 'Μετά την παράδοση σύγκρινε το αντικείμενο με την αγγελία. Αν υπάρχει πρόβλημα, κράτησε φωτογραφίες και ανέφερέ το από την παραγγελία εγκαίρως.' },
      { id: 'release', title: 'Ακολούθησε την ενημέρωση πληρωμής', text: 'Η παραγγελία ακολουθεί τη διαδικασία ελέγχου και αποδέσμευσης. Η τραπεζική κατάθεση στον πωλητή είναι ξεχωριστό στάδιο με δική του ενημέρωση.' },
    ],
  },
  en: {
    description: 'Buy and sell cards, figures, comics and other collectibles. Compare photographs and descriptions and review the total cost before paying.',
    title: 'Payments and order tracking',
    payment: 'Payments are processed through Stripe. After delivery, the order follows its review and release process. If there is a problem, report it within the deadline shown in your order.',
    highlights: ['Photographs and condition descriptions', 'Total cost before payment', 'Order and message history', 'Support for reporting a problem'],
    paymentPoints: ['Payment through Stripe', 'Review after delivery', 'Separate bank payout status'],
    verification: 'Verification concerns the account; it does not certify the authenticity of every listed item.',
    steps: [
      { id: 'choose', title: 'Choose the right item', text: 'Compare edition, condition and photographs. Ask the seller to clarify anything uncertain before buying.' },
      { id: 'pay', title: 'Review the total and pay', text: 'Check the item value, shipping and any charges before completing payment through Stripe.' },
      { id: 'receive', title: 'Track and inspect your order', text: 'After delivery, compare the item with the listing. If there is a problem, keep photographs and report it through your order within its review deadline.' },
      { id: 'release', title: 'Follow the payment status', text: 'The order follows its review and release process. The seller’s bank payout is a separate stage with its own status.' },
    ],
  },
}
