import { collectorHub } from './collectorHub.js'

// Reconcile legacy answers with verified marketplace behavior.
// Preserve other API questions, including future additions, rather than hiding them.
const verifiedAnswers = {
  el: {
    'Πώς γίνεται η επαλήθευση Cardora;': {
      question: 'Πώς γίνεται η επαλήθευση Cardora;',
      answer: 'Η επαλήθευση ταυτότητας γίνεται στη σελίδα της Didit. Συνδέσου στην Cardora, διάβασε την ενημέρωση απορρήτου και ακολούθησε τα βήματα που εμφανίζονται. Στο τέλος επέστρεψε στην Cardora για το αποτέλεσμα και την ημερομηνία ετήσιας ανανέωσης. Η ρύθμιση πληρωμών μέσω Stripe είναι ξεχωριστή διαδικασία. Η επαλήθευση αφορά τον χρήστη και δεν αποτελεί πιστοποίηση γνησιότητας κάθε αντικειμένου που αναρτά. Για δυσκολίες ή επανεξέταση, επικοινώνησε με την υποστήριξη.',
    },
    'Πότε πληρώνεται ο πωλητής;': {
      question: 'Πότε πληρώνεται ο πωλητής;',
      answer: 'Μετά την παράδοση, η παραγγελία ακολουθεί τη διαδικασία ελέγχου και αποδέσμευσης. Αν αναφερθεί πρόβλημα, μπορεί να χρειαστεί εξέταση πριν προχωρήσει η αποδέσμευση. Η μεταφορά στον συνδεδεμένο λογαριασμό πωλητή και η πίστωση στην τράπεζά του είναι διαφορετικά στάδια. Παρακολούθησε την κατάσταση της παραγγελίας και του λογαριασμού πληρωμών σου· η αποδέσμευση δεν σημαίνει απαραίτητα τραπεζική κατάθεση την ίδια ημέρα.',
    },
    'Μέσω τίνος γίνονται το checkout και τα seller payouts;': {
      question: 'Πώς γίνεται η πληρωμή στην Cardora;',
      answer: 'Οι πληρωμές διεκπεραιώνονται μέσω Stripe. Πριν πληρώσεις, έλεγξε την αξία των αντικειμένων, τα μεταφορικά και τις χρεώσεις που εμφανίζονται στο σύνολο. Ο πωλητής συνδέει τον λογαριασμό πληρωμών του μέσω Stripe και παρακολουθεί χωριστά την αποδέσμευση της παραγγελίας και την τραπεζική κατάθεση. Η επαλήθευση λογαριασμού Cardora δεν αντικαθιστά τα βήματα που ζητά η Stripe.',
    },
  },
  en: {
    'How does Cardora verification work?': {
      question: 'How does Cardora verification work?',
      answer: 'Identity verification takes place on Didit’s website. Sign in to Cardora, read the privacy information and follow the steps shown. Afterwards, return to Cardora for the result and your annual renewal date. Setting up payments through Stripe is a separate process. Verification concerns the user and does not certify the authenticity of every item they list. Contact support if you need help or wish to request a review.',
    },
    'When does the seller receive the money?': {
      question: 'When does the seller receive the money?',
      answer: 'After delivery, the order follows its review and release process. A reported problem may need review before release proceeds. Transfer to the connected seller account and payment into the seller’s bank are separate stages. Follow the status of your order and payout account; release does not necessarily mean a bank deposit on the same day.',
    },
    'What powers checkout and seller payouts?': {
      question: 'How are Cardora payments processed?',
      answer: 'Payments are processed through Stripe. Before paying, check the item value, shipping and charges shown in your total. Sellers connect their payout account through Stripe and follow order release and bank payout separately. Cardora account verification does not replace the steps required by Stripe.',
    },
  },
}

export function getMarketplaceFaq(groups = [], locale = 'el') {
  const lang = locale === 'en' ? 'en' : 'el'
  return [
    { title: collectorHub[lang].faqTitle, items: collectorHub[lang].faqs.map(([question, answer]) => ({ question, answer })) },
    ...groups.map(group => ({ ...group, items: group.items.map(item => verifiedAnswers[lang][item.question] || item) })),
  ]
}
