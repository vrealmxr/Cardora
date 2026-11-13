// Display labels for known choices in listingFormData. Never rewrite stored/query values
// or translate seller-authored titles, descriptions, series and certification text.
const typePairs = [
  ['Μεμονωμένες κάρτες', 'Single Cards'], ['Πακέτο Booster', 'Booster Pack'],
  ['Κουτί Booster', 'Booster Box'], ['Elite Trainer Boxes', 'Elite Trainer Box'],
  ['Promo κάρτες', 'Promo Cards'], ['Graded κάρτες', 'Graded Cards'],
  ['Αγάλματα & Premium Statues', 'Statues & Premium Pieces'],
  ['Κόμικς', 'Comic Books'], ['Hardcover Εκδόσεις', 'Hardcover Editions'],
  ['Omnibus / Συλλογές', 'Omnibus / Collections'], ['Μυθιστορήματα', 'Novels (Fiction)'],
  ['Οδηγοί / Εγκυκλοπαίδειες', 'Guidebooks / Encyclopedias'], ['Περιοδικά', 'Magazines'],
  ['Αξεσουάρ', 'Accessories'], ['Επιτραπέζια', 'Board Games'], ['Βινύλια', 'Vinyl Records'],
  ['Νομίσματα & Μετάλλια', 'Coins & Medals'], ['Συλλεκτικά μουσικής', 'Music Memorabilia'],
]
const rarityPairs = [
  ['Κοινή (Common)', 'Common'], ['Ασυνήθιστη (Uncommon)', 'Uncommon'],
  ['Σπάνια (Rare)', 'Rare'], ['Προωθητική (Promo)', 'Promo'],
]
const languagePairs = [
  ['Ελληνικά', 'Greek'], ['Αγγλικά', 'English'], ['Ιαπωνικά', 'Japanese'], ['Κινεζικά', 'Chinese'],
  ['Γερμανικά', 'German'], ['Γαλλικά', 'French'], ['Ιταλικά', 'Italian'], ['Ισπανικά', 'Spanish'],
]
const other = [['Άλλο', 'Other']]
const dictionaries = Object.fromEntries(Object.entries({
  typeLabel: [...typePairs, ...other], productType: [...typePairs, ...other],
  rarity: [...rarityPairs, ...other], language: [...languagePairs, ...other],
  brand: other, franchise: [...other, ['Στρατηγικής & Euro', 'Strategy & Euro']],
}).map(([field, pairs]) => [field, new Map(pairs.flatMap(pair => pair.map(label => [label.normalize('NFC'), pair])))]))

export function catalogValueLabel(field, value, locale) {
  if (typeof value !== 'string') return value
  const pair = dictionaries[field]?.get(value.normalize('NFC').trim())
  return pair ? pair[locale === 'en' ? 1 : 0] : value
}

// A series repeated verbatim as a type/franchise need not be displayed twice.
export const distinctCatalogSeries = product => [product?.typeLabel, product?.franchise].includes(product?.series) ? null : product?.series
