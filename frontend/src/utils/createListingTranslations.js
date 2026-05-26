import { normalizePotentialMojibake, normalizeTextTree } from '@/utils/textEncoding'

const createListingGreekCopy = normalizeTextTree({
  Auction: 'Δημοπρασία',
  'Single card': 'Μεμονωμένη κάρτα',
  'Card lot / multiple cards': 'Loot lot / πολλές κάρτες',
  'Auction live': 'Δημοπρασία σε εξέλιξη',
  'For international shipping, DHL is enabled and you set separate costs per region.':
    'Για εξωτερικό ενεργοποιείται DHL και ορίζεις ξεχωριστό κόστος ανά ζώνη.',
  'New card lot listing': 'Νέα αγγελία lot καρτών',
  'New auction listing': 'Νέα δημοπρασία',
  'New listing': 'Νέα αγγελία',
  'Grouped cards with a clean summary and clear highlights.':
    'Ομαδική αγγελία καρτών με καθαρή σύνοψη και βασικά highlights.',
  'Timed sale with bidding and optional reserve or buyout.':
    'Χρονική πώληση με προσφορές και προαιρετικό reserve ή buyout.',
  'Choose a category and a subcategory before continuing.':
    'Επίλεξε κατηγορία και υποκατηγορία πριν συνεχίσεις.',
  'Add a title, description, franchise, brand and condition.':
    'Συμπλήρωσε τίτλο, περιγραφή, franchise, brand και κατάσταση.',
  'Upload at least one photo before moving on.':
    'Ανέβασε τουλάχιστον μία φωτογραφία πριν προχωρήσεις.',
  'A card lot should include at least 2 cards.':
    'Για lot καρτών χρειάζονται τουλάχιστον 2 κάρτες.',
  'Add at least one card so buyers can immediately understand what is inside the lot.':
    'Πρόσθεσε τουλάχιστον μία κάρτα ώστε να καταλαβαίνει άμεσα ο αγοραστής τι υπάρχει μέσα στο lot.',
  'Total cards must be equal to or higher than the named cards you added.':
    'Το συνολικό πλήθος πρέπει να είναι ίσο ή μεγαλύτερο από τις κάρτες που έχεις ήδη προσθέσει.',
  'Add a short summary that explains the lot clearly.':
    'Γράψε μία σύντομη σύνοψη που να εξηγεί καθαρά το lot.',
  'Set a valid opening bid.': 'Όρισε έγκυρη αρχική προσφορά.',
  'Set a valid bid increment.': 'Όρισε έγκυρο βήμα προσφορών.',
  'Choose when the auction ends.': 'Όρισε ημερομηνία και ώρα λήξης.',
  'The reserve price must be equal to or higher than the opening bid.':
    'Το reserve πρέπει να είναι ίσο ή μεγαλύτερο από την αρχική προσφορά.',
  'The buyout price must be higher than the opening bid.':
    'Το buyout πρέπει να είναι μεγαλύτερο από την αρχική προσφορά.',
  'Set a valid price and available quantity.':
    'Όρισε έγκυρη τιμή και διαθέσιμη ποσότητα.',
  'The minimum offer should stay lower than the sale price.':
    'Η ελάχιστη προσφορά πρέπει να είναι χαμηλότερη από την τελική τιμή.',
  Active: 'Ενεργό',
  Add: 'Προσθήκη',
  Availability: 'Διαθεσιμότητα',
  Carrier: 'Μεταφορική',
  Category: 'Κατηγορία',
  Condition: 'Κατάσταση',
  Continue: 'Συνέχεια',
  Description: 'Περιγραφή',
  Domestic: 'Ελλάδα',
  Login: 'Είσοδος',
  Packaging: 'Συσκευασία',
  Photos: 'Φωτογραφίες',
  Previous: 'Προηγούμενο',
  Price: 'Τιμή',
  Register: 'Εγγραφή',
  Shipping: 'Μεταφορικά',
  Subcategory: 'Υποκατηγορία',
  Themes: 'Θεματικές',
  cards: 'κάρτες',
  'parcel weight': 'βάρος συσκευασίας',
  'parcel length': 'μήκος συσκευασίας',
  'parcel width': 'πλάτος συσκευασίας',
  'parcel height': 'ύψος συσκευασίας',
  'Accept all listing confirmations before submitting.':
    'Πρέπει να αποδεχτείς όλα τα σημεία επιβεβαίωσης πριν από την υποβολή.',
  'Seller access': 'Πρόσβαση πωλητή',
  'Sign in to create a listing': 'Σύνδεση για να ανεβάσεις αγγελία',
  'Listing tools are available only to signed-in collectors so every listing stays tied to a real account.':
    'Τα εργαλεία καταχώρισης είναι διαθέσιμα μόνο σε συνδεδεμένους συλλέκτες ώστε κάθε αγγελία να συνδέεται με πραγματικό λογαριασμό.',
  Login: 'Είσοδος',
  Register: 'Εγγραφή',
  'Listing saved': 'Η αγγελία αποθηκεύτηκε',
  'Your item has been submitted': 'Το αντικείμενό σου καταχωρίστηκε',
  'Listing ID': 'ID αγγελίας',
  'Sale format': 'Μοντέλο πώλησης',
  'Fixed price': 'Σταθερή τιμή',
  'Starting bid': 'Αρχική προσφορά',
  Price: 'Τιμή',
  Category: 'Κατηγορία',
  'View my listings': 'Δες τις αγγελίες μου',
  'Open seller dashboard': 'Μετάβαση στο seller dashboard',
  'Create another listing': 'Νέα καταχώριση',
  Subcategory: 'Υποκατηγορία',
  'Final price (with calculated domestic shipping)':
    'Τελική τιμή (με υπολογισμένα μεταφορικά)',
  'Final price (with BoxNow)': 'Τελική τιμή (με BoxNow)',
  'Franchise group': 'Ομάδα franchise',
  'Franchise / series': 'Franchise / σειρά',
  Condition: 'Κατάσταση',
  Shipping: 'Μεταφορικά',
  'Domestic auto-calculated from parcel size and weight':
    'Ελλάδα με αυτόματο υπολογισμό από βάρος και διαστάσεις',
  'Greece only with BoxNow': 'Μόνο Ελλάδα με BoxNow',
  Photos: 'Φωτογραφίες',
  'Domestic parcel': 'Πακέτο Ελλάδας',
  'Lot summary': 'Σύνοψη lot',
  'Create a listing buyers can trust': 'Φτιάξε μία αγγελία που εμπνέει εμπιστοσύνη',
  'Fill in the details that matter, upload clear photos and send the listing for review before it goes live.':
    'Συμπλήρωσε όσα χρειάζεται ο επόμενος αγοραστής, ανέβασε καθαρές φωτογραφίες και στείλε την αγγελία για έλεγχο πριν βγει δημόσια.',
  'Best for one card where grading, set, serial and condition should stand out.':
    'Ιδανικό για μία κάρτα όπου θέλεις να ξεχωρίζουν grading, set, serial και κατάσταση.',
  'Best for grouped listings with many cards and a cleaner, easier-to-scan presentation.':
    'Ιδανικό για lot με πολλές κάρτες και πιο καθαρή, εύκολη παρουσίαση χωρίς μακροσκελές κείμενο.',
  Active: 'Ενεργό',
  'Lot mode is active. In the next step you will add the total card count, the key cards you want to show, and the short summary buyers will actually read.':
    'Το lot mode είναι ενεργό. Στο επόμενο βήμα θα προσθέσεις το συνολικό πλήθος, τις βασικές κάρτες που θέλεις να φαίνονται και τη σύντομη σύνοψη που θα διαβάσει ο αγοραστής.',
  'Listing title': 'Τίτλος αγγελίας',
  'For example: Pokemon lot with 54 cards, holo mix and guaranteed hits':
    'π.χ. Pokemon lot με 54 κάρτες, holo mix και guaranteed hits',
  'For example: Hot Toys Darth Vader 1/6 Deluxe with full box':
    'π.χ. Hot Toys Darth Vader 1/6 Deluxe με πλήρες κουτί',
  'For example: Charizard ex Special Illustration Rare PSA 10':
    'π.χ. Charizard ex Special Illustration Rare PSA 10',
  'Short subtitle': 'Σύντομος υπότιτλος',
  'Short context for the buyer': 'Σύντομο context για τον αγοραστή',
  'Choose the broad group first, such as Sports or Anime, and then only the matching franchises appear instead of one long list.':
    'Διάλεξε πρώτα τη βασική ομάδα, όπως Sports ή Anime, και μετά εμφανίζονται μόνο τα σχετικά franchises αντί για μία ατελείωτη λίστα.',
  'Brand / company': 'Brand / εταιρεία',
  'Year / release': 'Έτος / release',
  'e.g. 2024': 'π.χ. 2024',
  'e.g. alt art, PSA 10, sealed, modern': 'π.χ. alt art, PSA 10, sealed, modern',
  Description: 'Περιγραφή',
  'Explain the mix, the overall condition, the origin of the lot and what the buyer should realistically expect.':
    'Εξήγησε το mix, τη συνολική κατάσταση, την προέλευση του lot και τι πρέπει να περιμένει ρεαλιστικά ο αγοραστής.',
  'Describe the condition, any flaws, the origin of the item and what the buyer should know before purchasing.':
    'Περιέγραψε την κατάσταση, τυχόν flaws, την προέλευση του αντικειμένου και όσα πρέπει να ξέρει ο αγοραστής πριν αγοράσει.',
  'Upload photos': 'Ανέβασε φωτογραφίες',
  'Photos will be attached when you save or submit the listing.':
    'Οι φωτογραφίες θα συνδεθούν με την αγγελία όταν κάνεις αποθήκευση ή υποβολή.',
  'Selected photos': 'Επιλεγμένες φωτογραφίες',
  'Photo notes': 'Σημειώσεις για τις φωτογραφίες',
  'e.g. close-ups on corners, print lines, serial, seals or box wear.':
    'π.χ. close-ups σε corners, print lines, serial, seals ή box wear.',
  'Details buyers will read first': 'Στοιχεία που θα διαβάσει πρώτα ο αγοραστής',
  'You do not need to write all 100 cards one by one. Add the overall count, then list the cards you want buyers to notice first.':
    'Δεν χρειάζεται να γράψεις και τις 100 κάρτες μία μία. Βάλε το συνολικό πλήθος και μετά πρόσθεσε όσες θέλεις να ξεχωρίσουν πρώτες.',
  'Lot mode active': 'Lot mode ενεργό',
  'Total cards': 'Συνολικές κάρτες',
  'e.g. 70% Near Mint / 30% Excellent': 'π.χ. 70% Near Mint / 30% Excellent',
  'Cards shown first': 'Κάρτες που θα φαίνονται πρώτες',
  'Add a card you want shown in the preview':
    'Πρόσθεσε κάρτα που θέλεις να φαίνεται στο preview',
  Add: 'Προσθήκη',
  'No cards added yet.': 'Δεν έχουν προστεθεί ακόμα κάρτες.',
  Themes: 'Θεματικές',
  'Add a theme': 'Πρόσθεσε θεματική',
  'No themes added yet.': 'Δεν έχουν προστεθεί ακόμα θεματικές.',
  'Short lot summary': 'Σύντομη σύνοψη lot',
  'Explain what the lot is, what kind of mix it has, and what the buyer should realistically expect.':
    'Εξήγησε τι είναι το lot, τι mix έχει και τι πρέπει να περιμένει ρεαλιστικά ο αγοραστής.',
  'Auction listings show the opening bid, bid step, end time and any optional reserve or buyout.':
    'Στη δημοπρασία φαίνονται καθαρά η αρχική προσφορά, το βήμα, η λήξη και τυχόν reserve ή buyout.',
  'Fixed-price listings can include quantity, an older reference price and whether offers are welcome.':
    'Η σταθερή τιμή υποστηρίζει ποσότητα, παλαιότερη τιμή αναφοράς και επιλογή για προσφορές από αγοραστές.',
  'Bid increment': 'Βήμα προσφορών',
  'Auction end': 'Λήξη δημοπρασίας',
  Availability: 'Διαθεσιμότητα',
  'Item price (before domestic shipping)': 'Τιμή αντικειμένου (χωρίς μεταφορικά Ελλάδας)',
  'Previous price / reference': 'Παλιότερη τιμή / αναφορά',
  'Available quantity': 'Διαθέσιμη ποσότητα',
  'I am open to offers from buyers': 'Δέχομαι προσφορές από αγοραστές',
  'Minimum offer': 'Ελάχιστη προσφορά',
  'If you accept offers, set a realistic floor so messages stay useful and serious.':
    'Αν δέχεσαι προσφορές, όρισε ένα ρεαλιστικό κατώφλι ώστε τα μηνύματα να μένουν ουσιαστικά.',
  'Request featured placement for stronger visibility':
    'Ζήτησε featured προβολή για πιο έντονη παρουσίαση',
  Domestic: 'Ελλάδα',
  Carrier: 'Μεταφορική',
  'Parcel weight (kg)': 'Βάρος πακέτου (kg)',
  'Length (cm)': 'Μήκος (cm)',
  'Width (cm)': 'Πλάτος (cm)',
  'Height (cm)': 'Ύψος (cm)',
  'Item price': 'Τιμή αντικειμένου',
  'Volumetric weight': 'Ογκομετρικό βάρος',
  'Billable weight': 'Χρεώσιμο βάρος',
  'Calculated domestic shipping': 'Υπολογισμένα μεταφορικά Ελλάδας',
  'Final price': 'Τελική τιμή',
  'Automatic BoxNow fee': 'Αυτόματο κόστος BoxNow',
  'Final price (item + BoxNow)': 'Τελική τιμή (τιμή + BoxNow)',
  'I also ship internationally': 'Στέλνω και εξωτερικό',
  'International carrier': 'Μεταφορική εξωτερικού',
  'DHL cost': 'Κόστος DHL',
  'Dispatch time': 'Χρόνος αποστολής',
  Packaging: 'Συσκευασία',
  'Shipping notes': 'Σημειώσεις αποστολής',
  cards: 'κάρτες',
  'No lot summary has been added yet.': 'Δεν έχει προστεθεί ακόμα σύνοψη για το lot.',
  Previous: 'Προηγούμενο',
  Continue: 'Συνέχεια',
  'Saving...': 'Αποθήκευση...',
  'Save as draft': 'Αποθήκευση ως πρόχειρο',
  'Submitting...': 'Υποβολή...',
  'Submit for review': 'Υποβολή για έλεγχο',
  'Before you publish': 'Πριν δημοσιεύσεις',
  'Use the title for the main hook and the subtitle for quick value signals.':
    'Χρησιμοποίησε τον τίτλο για το βασικό hook και τον υπότιτλο για γρήγορα value signals.',
  'For lots, show a few recognisable cards and explain the overall mix clearly.':
    'Στα lots δείξε λίγες αναγνωρίσιμες κάρτες και εξήγησε καθαρά το συνολικό mix.',
  'For graded or sealed items, make sure the label, corners and packaging are visible in good light.':
    'Σε graded ή sealed κομμάτια, φρόντισε να φαίνονται καθαρά label, γωνίες και συσκευασία.',

  'Marketplace access': 'Πρόσβαση marketplace',
  'Open verification center': 'Άνοιγμα verification center',
  'Open seller dashboard / Stripe setup': 'Άνοιγμα seller dashboard / Stripe setup',
  'Back to profile': 'Επιστροφή στο προφίλ',
  'Issue / volume': 'Τεύχος / volume',
  'Printing / edition': 'Εκτύπωση / έκδοση',
  'For example: Pink Floyd limited vinyl / collectible 2? proof coin / signed tour poster':
    'π.χ. Pink Floyd limited vinyl / συλλεκτικό νόμισμα 2€ proof / signed tour poster',
  'Individual cards for checkout': 'Μεμονωμένες κάρτες για checkout',
  'Allow buyers to purchase single cards from the lot':
    'Επίτρεψε στους αγοραστές να αγοράζουν μεμονωμένες κάρτες από το lot',
  'If enabled, buyers can choose specific cards from the lot and buy them separately.':
    'Αν ενεργοποιηθεί, οι αγοραστές μπορούν να διαλέγουν συγκεκριμένες κάρτες από το lot και να τις αγοράζουν ξεχωριστά.',
  'Enabled': 'Ενεργό',
  'Card title for individual purchase': 'Τίτλος κάρτας για μεμονωμένη αγορά',
  'Add card': 'Προσθήκη κάρτας',
  'Shipping and insurance for individual card purchases start at ?2.50 for up to 10 cards. From the 11th card onward, ?0.25 is added for each extra card.':
    'Τα μεταφορικά και η ασφάλιση για μεμονωμένες αγορές καρτών ξεκινούν από 2,50€ έως 10 κάρτες. Από την 11η κάρτα και μετά προστίθενται 0,25€ για κάθε επιπλέον κάρτα.',
  'Remove': 'Αφαίρεση',
  'No individual cards added yet.': 'Δεν έχουν προστεθεί ακόμα μεμονωμένες κάρτες.',
  'No franchise subcategory is required for the Other group.':
    'Για την επιλογή Άλλο δεν χρειάζεται επιπλέον franchise.',
  'Choose the main franchise group first and then you will only see the relevant options.':
    'Διάλεξε πρώτα την κύρια ομάδα franchise και μετά θα δεις μόνο τις σχετικές επιλογές.',
  'Changes saved': 'Οι αλλαγές αποθηκεύτηκαν',
  'For example: Pink Floyd limited vinyl / collectible 2? proof coin / signed tour poster':
    'π.χ. Pink Floyd limited vinyl / συλλεκτικό νόμισμα 2€ proof / signed tour poster',
  'Shipping and insurance for individual card purchases start at ?2.50 for up to 10 cards. From the 11th card onward, ?0.25 is added for each extra card.':
    'Τα μεταφορικά και η ασφάλιση για μεμονωμένες αγορές καρτών ξεκινούν από 2,50€ έως 10 κάρτες. Από την 11η κάρτα και μετά προστίθενται 0,25€ για κάθε επιπλέον κάρτα.',
})

export function translateCreateListingGreek(en) {
  let result = createListingGreekCopy[en] ?? null
  if (result) return normalizePotentialMojibake(result)

  const domesticIntlMatch = en.match(
    /^Domestic auto-calculated from parcel size\/weight \+ DHL international \((.+)\)$/,
  )
  if (domesticIntlMatch) {
    result = `???????????? ???? ???????????????? ???????????????????? ?????? ??????????/???????????????????? + DHL ???????????????????? (${domesticIntlMatch[1]})`
  }

  const boxNowIntlMatch = en.match(/^BoxNow domestic \+ DHL international \((.+)\)$/)
  if (boxNowIntlMatch) {
    result = `BoxNow ?????????????? + DHL ???????????????????? (${boxNowIntlMatch[1]})`
  }

  return result ? normalizePotentialMojibake(result) : null
}

