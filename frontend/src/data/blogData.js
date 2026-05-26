export const blogCategories = [
  'Όλα',
  'Οδηγοί Αγοράς',
  'Ασφάλεια Συναλλαγών',
  'Trading Cards',
  'Φιγούρες & Collectibles',
]

export const blogPosts = [
  {
    id: 1,
    slug: 'pos-na-agorazeis-graded-kartes-me-asfaleia',
    title: 'Πώς να αγοράζεις graded κάρτες με ασφάλεια χωρίς να πληρώνεις premium στα τυφλά',
    excerpt:
      'Checklist για slab verification, market comps, escrow mindset και βασικά λάθη που κοστίζουν ακριβά σε high-end κάρτες.',
    category: 'Trading Cards',
    tags: ['PSA', 'BGS', 'graded cards', 'αγορές'],
    author: {
      name: 'Συντακτική ομάδα Cardora',
      role: 'Trust & marketplace editorial',
    },
    publishedAt: '2026-03-29T10:15:00',
    readTime: '6 λεπτά',
    featured: true,
    visual: {
      gradient: 'from-[#173153] via-[#0e1c33] to-[#090f1d]',
      label: 'Guided Buying',
    },
    sections: [
      {
        title: 'Ξεκίνα από το slab και όχι από το hype',
        paragraphs: [
          'Στις graded κάρτες, το πρώτο που πρέπει να ελέγχεις δεν είναι μόνο το artwork ή το πόσο viral είναι ένα set. Το slab, η εταιρεία grading και η αντιστοίχιση serial είναι η βάση κάθε σοβαρής αγοράς.',
          'Στο Cardora η λογική escrow βοηθά ώστε να μην πληρώνεις στα τυφλά. Παρ’ όλα αυτά, ο buyer οφείλει να συγκρίνει πραγματικά comps, πρόσφατες πωλήσεις και την ποιότητα του ίδιου του slab πριν προχωρήσει.',
        ],
        bullets: [
          'Έλεγξε αν ο serial αριθμός ταιριάζει ακριβώς με το listing',
          'Δες αν υπάρχουν γρατζουνιές ή fogging στο πλαστικό',
          'Σύγκρινε τελευταία sold prices, όχι μόνο ενεργά listings',
        ],
      },
      {
        title: 'Όταν μια κάρτα δείχνει “πολύ καλή ευκαιρία”',
        paragraphs: [
          'Στα premium collectibles, οι υπερβολικά χαμηλές τιμές είναι συχνά red flag. Δεν σημαίνει πάντα απάτη, αλλά σημαίνει ότι χρειάζεται περισσότερη τεκμηρίωση, φωτογραφίες close-up και σαφής ιστορικότητα από τον πωλητή.',
        ],
      },
    ],
  },
  {
    id: 2,
    slug: 'escrow-gia-collectibles-ti-prostatevei-pragmatika',
    title: 'Escrow για collectibles: τι προστατεύει πραγματικά και πού πρέπει να είσαι ακόμα προσεκτικός',
    excerpt:
      'Αναλυτική εξήγηση για protected payments, confirmation windows, disputes και τι πρέπει να κρατά ο συλλέκτης ως αποδεικτικά.',
    category: 'Ασφάλεια Συναλλαγών',
    tags: ['escrow', 'ασφάλεια', 'disputes'],
    author: {
      name: 'Μαρία Κανελλοπούλου',
      role: 'Cardora trust operations',
    },
    publishedAt: '2026-03-27T14:45:00',
    readTime: '7 λεπτά',
    featured: true,
    visual: {
      gradient: 'from-[#5a4320] via-[#1a1f33] to-[#09111d]',
      label: 'Trust Layer',
    },
    sections: [
      {
        title: 'Τι κάνει το escrow',
        paragraphs: [
          'Το escrow δεσμεύει το ποσό ώστε ο πωλητής να μην πληρώνεται πριν ο αγοραστής επιβεβαιώσει ότι παρέλαβε το σωστό αντικείμενο στην κατάσταση που είχε περιγραφεί.',
          'Αυτό δεν σημαίνει ότι ο αγοραστής δεν χρειάζεται να είναι οργανωμένος. Φωτογραφίες unboxing, tracking και συνομιλίες μέσα στην πλατφόρμα παραμένουν κρίσιμα στοιχεία.',
        ],
        bullets: [
          'Κράτα φωτογραφίες συσκευασίας και unboxing',
          'Μη μεταφέρεις τη συζήτηση εκτός πλατφόρμας',
          'Άνοιξε dispute νωρίς αν υπάρχει απόκλιση από την περιγραφή',
        ],
      },
      {
        title: 'Τι δεν λύνει από μόνο του',
        paragraphs: [
          'Το escrow δεν αντικαθιστά την έρευνα αγοράς, ούτε την ανάγκη για καθαρό listing. Αν ένα collectible έχει ασαφείς φωτογραφίες ή λείπουν στοιχεία grading, το ρίσκο παραμένει.',
        ],
      },
    ],
  },
  {
    id: 3,
    slug: 'ti-koitane-oi-sovaroi-agorastes-se-anime-figoures',
    title: 'Τι κοιτάνε οι σοβαροί αγοραστές σε anime figures, statues και sealed boxes',
    excerpt:
      'Από manufacturer marks και box condition μέχρι μεταπωλητική αξία και display damage, αυτός είναι ο οδηγός για premium figure listings.',
    category: 'Φιγούρες & Collectibles',
    tags: ['anime figures', 'sealed box', 'collectibles'],
    author: {
      name: 'Ελένη Παπαδοπούλου',
      role: 'Figure marketplace curator',
    },
    publishedAt: '2026-03-24T12:20:00',
    readTime: '5 λεπτά',
    featured: false,
    visual: {
      gradient: 'from-[#552143] via-[#1b2239] to-[#09111d]',
      label: 'Figure Guide',
    },
    sections: [
      {
        title: 'Το κουτί έχει μεγαλύτερη σημασία απ’ όσο νομίζεις',
        paragraphs: [
          'Σε sealed statues και premium figures, το box condition μπορεί να αλλάξει σημαντικά τη μεταπωλητική αξία. Γωνίες, παράθυρο, αυτοκόλλητα authenticity και εσωτερικά blister πρέπει να περιγράφονται καθαρά.',
        ],
      },
      {
        title: 'Manufacturer και reissues',
        paragraphs: [
          'Οι serious buyers ελέγχουν πάντα αν πρόκειται για πρώτη κυκλοφορία, reissue ή special edition. Αυτό επηρεάζει και την τιμή και το ενδιαφέρον των συλλεκτών.',
        ],
        bullets: [
          'Ανάφερε manufacturer, line και scale',
          'Δήλωσε αν είναι sealed, open box ή display only',
          'Τεκμηρίωσε τυχόν scuffs ή μεταχρωματισμούς',
        ],
      },
    ],
  },
  {
    id: 4,
    slug: 'pos-na-stineis-aggelia-pou-poulaei-choris-na-fonaizei',
    title: 'Πώς να στήσεις αγγελία που πουλάει χωρίς να “φωνάζει”',
    excerpt:
      'Οδηγός για premium product presentation: τίτλος, φωτογραφίες, condition notes, shipping policy και trust στοιχεία που αυξάνουν conversion.',
    category: 'Οδηγοί Αγοράς',
    tags: ['αγγελία', 'presentation', 'selling'],
    author: {
      name: 'Cardora Marketplace Team',
      role: 'Seller growth',
    },
    publishedAt: '2026-03-22T09:40:00',
    readTime: '8 λεπτά',
    featured: false,
    visual: {
      gradient: 'from-[#27445b] via-[#142134] to-[#08101d]',
      label: 'Seller Playbook',
    },
    sections: [
      {
        title: 'Ο σωστός τίτλος είναι περιγραφικός, όχι θορυβώδης',
        paragraphs: [
          'Οι πιο αποδοτικές αγγελίες ξεκινούν με ξεκάθαρο τίτλο: franchise, set ή σειρά, grading/condition και ειδικά χαρακτηριστικά. Οι υπερβολικοί τίτλοι κουράζουν και συχνά ρίχνουν την εμπιστοσύνη.',
        ],
      },
      {
        title: 'Η περιγραφή πρέπει να κλείνει ερωτήσεις πριν γίνουν μήνυμα',
        paragraphs: [
          'Αναφορά σε flaws, τρόπο αποστολής, προστασία συσκευασίας, δυνατότητα προσφορών και χρόνους dispatch κάνει τον αγοραστή να νιώθει ότι αγοράζει από σοβαρό seller.',
        ],
        bullets: [
          'Γράψε τι ακριβώς θα παραλάβει ο buyer',
          'Δήλωσε αν δέχεσαι προσφορές και από ποιο επίπεδο',
          'Πρόσθεσε σαφή policy για shipping και ασφάλιση',
        ],
      },
    ],
  },
]
