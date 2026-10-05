const IMG = '/tutorials/stripe-connected-account'

const stripeConnectedAccount = {
  slug: 'stripe-connected-account',
  audience: 'seller',
  cover: `${IMG}/step-02.webp`,
  cta: { href: '/profil' },
  copy: {
    el: {
      title: 'Οδηγός δημιουργίας Stripe Connected Account',
      short: 'Ολοκλήρωσε το Stripe Express onboarding και ενεργοποίησε τις αποδεσμεύσεις σου ως πωλητής.',
      description:
        'Βήμα-βήμα οδηγίες για να ολοκληρώσεις το Stripe Express onboarding, να προσθέσεις στοιχεία ταυτοποίησης και τραπεζικό λογαριασμό και να ενεργοποιήσεις τις αποδεσμεύσεις σου ως πωλητής στο Cardora.',
      audienceLabel: 'Για πωλητές',
      needs: [
        'Ένα email που θα χρησιμοποιήσεις για το Stripe Express',
        'Το κινητό σου, για τον κωδικό επιβεβαίωσης με SMS',
        'Έγγραφο ταυτοποίησης (ταυτότητα ή διαβατήριο) και κινητό με κάμερα',
        'Το IBAN ενός τραπεζικού λογαριασμού που δικαιούσαι να διαχειρίζεσαι',
      ],
      phases: ['Cardora', 'Stripe Express', 'Ταυτοποίηση', 'Business details', 'IBAN', 'Review & Submit'],
      note: 'Οι οθόνες της Stripe μπορεί να διαφέρουν ελαφρά ανάλογα με τη χώρα, τον τύπο λογαριασμού και τις απαιτήσεις επαλήθευσης. Οι εικόνες είναι από δοκιμαστικό λογαριασμό και τα προσωπικά στοιχεία έχουν κρυφτεί.',
    },
    en: {
      title: 'How to create your Stripe Connected Account',
      short: 'Complete Stripe Express onboarding and activate your payouts as a seller.',
      description:
        'Step-by-step instructions to complete Stripe Express onboarding, add your identity details and bank account, and activate your payouts as a seller on Cardora.',
      audienceLabel: 'For sellers',
      needs: [
        'An email address to use for Stripe Express',
        'Your mobile phone, to receive the SMS verification code',
        'An identity document (ID card or passport) and a phone with a camera',
        'The IBAN of a bank account you are entitled to manage',
      ],
      phases: ['Cardora', 'Stripe Express', 'Identity', 'Business details', 'IBAN', 'Review & Submit'],
      note: 'Stripe screens may differ slightly depending on your country, account type and verification requirements. Screenshots come from a test account and personal details have been hidden.',
    },
  },
  steps: [
    {
      image: `${IMG}/step-01.webp`,
      el: {
        title: 'Άνοιξε το Stripe setup από το προφίλ σου',
        body: 'Από τη σελίδα του προφίλ σου, εντόπισε επάνω δεξιά το κουμπί Stripe setup και πάτησέ το. Αυτό ανοίγει την περιοχή όπου συνδέεις τον λογαριασμό σου με τη Stripe για να μπορείς να λαμβάνεις πληρωμές ως πωλητής.',
      },
      en: {
        title: 'Open Stripe setup from your profile',
        body: 'On your profile page, find the Stripe setup button at the top right and click it. This opens the area where you connect your account to Stripe so you can receive payments as a seller.',
      },
    },
    {
      image: `${IMG}/step-02.webp`,
      el: {
        title: 'Ξεκίνα τη δημιουργία Stripe Connected Account',
        body: 'Στο Seller Dashboard πάτησε Δημιουργία Stripe Connected Account. Θα μεταφερθείς στο ασφαλές περιβάλλον της Stripe για να ολοκληρώσεις τα στοιχεία που απαιτούνται για payouts.',
      },
      en: {
        title: 'Start creating your Stripe Connected Account',
        body: 'In the Seller Dashboard, click Create Stripe Connected Account. You will be taken to Stripe’s secure environment to complete the details required for payouts.',
      },
    },
    {
      image: `${IMG}/step-03.webp`,
      el: {
        title: 'Σύνδεση ή έναρξη στο Stripe Express',
        body: 'Στη σελίδα Sign in to Express, γράψε το email που θέλεις να χρησιμοποιήσεις για Stripe Express και πάτησε Continue. Αν υπάρχει ήδη λογαριασμός με αυτό το email, η Stripe θα σε καθοδηγήσει στη σύνδεση.',
      },
      en: {
        title: 'Sign in or get started with Stripe Express',
        body: 'On the Sign in to Express page, enter the email you want to use for Stripe Express and click Continue. If an account already exists with that email, Stripe will guide you through signing in.',
      },
    },
    {
      image: `${IMG}/step-04.webp`,
      el: {
        title: 'Επιβεβαίωσε email και κινητό',
        body: 'Έλεγξε ότι το email είναι σωστό, συμπλήρωσε τον αριθμό κινητού σου και πάτησε Submit. Χρησιμοποίησε αριθμό που έχεις μαζί σου, γιατί στο επόμενο βήμα θα σταλεί κωδικός επιβεβαίωσης.',
      },
      en: {
        title: 'Confirm your email and mobile number',
        body: 'Check that the email is correct, enter your mobile number and click Submit. Use a number you have with you, because a verification code will be sent in the next step.',
      },
    },
    {
      image: `${IMG}/step-05.webp`,
      el: {
        title: 'Βάλε τον κωδικό που θα λάβεις με SMS',
        body: 'Πληκτρολόγησε τον κωδικό επιβεβαίωσης που έστειλε η Stripe στο κινητό σου. Αν δεν λάβεις μήνυμα, χρησιμοποίησε το Resend code ή επίλεξε διαφορετικό αριθμό τηλεφώνου.',
      },
      en: {
        title: 'Enter the code you receive by SMS',
        body: 'Type the verification code Stripe sent to your phone. If you do not receive a message, use Resend code or choose a different phone number.',
      },
    },
    {
      image: `${IMG}/step-06.webp`,
      el: {
        title: 'Συμπλήρωσε τα προσωπικά σου στοιχεία',
        body: 'Συμπλήρωσε το νόμιμο όνομα και επώνυμο, ημερομηνία γέννησης, διεύθυνση κατοικίας και τηλέφωνο. Τα στοιχεία πρέπει να είναι ακριβή και να συμφωνούν με τα επίσημα έγγραφά σου, γιατί χρησιμοποιούνται για την επαλήθευση ταυτότητας.',
      },
      en: {
        title: 'Fill in your personal details',
        body: 'Enter your legal first and last name, date of birth, home address and phone number. The details must be accurate and match your official documents, because they are used to verify your identity.',
      },
    },
    {
      image: `${IMG}/step-07.webp`,
      el: {
        title: 'Ολοκλήρωσε την επαλήθευση ταυτότητας όταν ζητηθεί',
        body: 'Στο Verify your identity επίλεξε Scan photo ID και ακολούθησε τις οδηγίες της Stripe από κινητό για φωτογράφιση εγγράφου ταυτοποίησης και, όπου ζητηθεί, selfie. Η οθόνη δείχνει και επιλογή Skip for now, όμως η Stripe μπορεί να ζητήσει την επαλήθευση πριν ενεργοποιηθούν πλήρως οι πληρωμές ή οι αποδεσμεύσεις.',
      },
      en: {
        title: 'Complete identity verification when asked',
        body: 'On Verify your identity, choose Scan photo ID and follow Stripe’s instructions on your phone to photograph your ID document and, where asked, take a selfie. The screen also offers Skip for now, but Stripe may require verification before payments or payouts are fully enabled.',
      },
    },
    {
      image: `${IMG}/step-08.webp`,
      el: {
        title: 'Δήλωσε τα στοιχεία δραστηριότητας',
        body: 'Στην ενότητα Business details πρέπει να δηλώσεις τον κλάδο δραστηριότητας και είτε website είτε περιγραφή των προϊόντων/υπηρεσιών που πουλάς. Αν δεν χρησιμοποιείς δικό σου website, πάτησε το link Don’t have a website? Add product description instead.',
      },
      en: {
        title: 'Provide your business details',
        body: 'In the Business details section you need to state your industry and either a website or a description of the products or services you sell. If you do not have your own website, click the link Don’t have a website? Add product description instead.',
      },
    },
    {
      image: `${IMG}/step-09.webp`,
      el: {
        title: 'Στο Industry επίλεξε την κατηγορία Retail',
        body: 'Άνοιξε το πεδίο Industry και επίλεξε την κατηγορία Retail, όπως φαίνεται στην εικόνα. Η επιλογή πρέπει να περιγράφει όσο γίνεται καλύτερα τη δραστηριότητά σου.',
      },
      en: {
        title: 'In Industry, choose the Retail category',
        body: 'Open the Industry field and choose the Retail category, as shown in the image. Your choice should describe your activity as accurately as possible.',
      },
    },
    {
      image: `${IMG}/step-10.webp`,
      el: {
        title: 'Επίλεξε Other merchandise',
        body: 'Μέσα στην κατηγορία Retail, επίλεξε Other merchandise. Στο παράδειγμα αυτό χρησιμοποιείται ως γενική κατηγορία για πώληση συλλεκτικών αντικειμένων.',
      },
      en: {
        title: 'Choose Other merchandise',
        body: 'Inside the Retail category, choose Other merchandise. In this example it is used as a general category for selling collectible items.',
      },
    },
    {
      image: `${IMG}/step-11.webp`,
      el: {
        title: 'Γράψε μια σύντομη περιγραφή προϊόντων',
        body: 'Στο πεδίο Product description γράψε με απλά λόγια τι πουλάς. Παράδειγμα: “I sell collectible items as an individual.” Μετά πάτησε Continue. Η περιγραφή πρέπει να ανταποκρίνεται στην πραγματική σου δραστηριότητα.',
      },
      en: {
        title: 'Write a short product description',
        body: 'In the Product description field, say in plain words what you sell. Example: “I sell collectible items as an individual.” Then click Continue. The description must match your real activity.',
      },
    },
    {
      image: `${IMG}/step-12.webp`,
      el: {
        title: 'Πρόσθεσε τραπεζικό λογαριασμό για payouts',
        body: 'Στο Add an account for payouts έλεγξε τη χώρα του τραπεζικού λογαριασμού, γράψε το IBAN και ξαναγράψε το στο Confirm IBAN. Χρησιμοποίησε λογαριασμό που δικαιούσαι να διαχειρίζεσαι και έλεγξε προσεκτικά το IBAN πριν πατήσεις Continue.',
      },
      en: {
        title: 'Add a bank account for payouts',
        body: 'In Add an account for payouts, check the country of the bank account, enter your IBAN and enter it again in Confirm IBAN. Use an account you are entitled to manage and double-check the IBAN before you click Continue.',
      },
    },
    {
      image: `${IMG}/step-13.webp`,
      el: {
        title: 'Έλεγξε όλα τα στοιχεία και κάνε υποβολή',
        body: 'Στη σελίδα Review and submit έλεγξε μία-μία τις ενότητες: Business type, Professional details, Personal details και Payout details. Αν κάτι είναι λάθος, πάτησε Edit. Όταν όλες οι ενότητες έχουν ολοκληρωθεί, προχώρησε στην τελική υποβολή. Σημείωση: το παράδειγμα της εικόνας δείχνει συγκεκριμένο business type· εσύ πρέπει να δηλώσεις τη μορφή που ισχύει πραγματικά για εσένα.',
      },
      en: {
        title: 'Review everything and submit',
        body: 'On the Review and submit page, check each section one by one: Business type, Professional details, Personal details and Payout details. If something is wrong, click Edit. Once every section is complete, go ahead with the final submission. Note: the example in the image shows a specific business type; you must declare the form that truly applies to you.',
      },
    },
  ],
  // Index (0-based) of the first step belonging to each phase in copy.phases.
  phaseStart: [0, 2, 5, 7, 11, 12],
}

export const tutorials = [stripeConnectedAccount]

export const getTutorial = (slug) => tutorials.find((item) => item.slug === slug) ?? null
