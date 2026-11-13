// Metadata only: full guide text is loaded with the article route, not every page.
export const editorialGuideIndex = [
  {
    slug: 'pos-na-steileis-kartes-me-asfaleia', tags: ['Shipping', 'Cards', 'Packaging'],
    el: { title: 'Πώς να στείλεις κάρτες με ασφάλεια', excerpt: 'Πρακτικά βήματα για μεμονωμένες κάρτες, σύνολα και graded θήκες: προετοιμασία, προστασία, έλεγχος δέματος και τεκμηρίωση.' },
    en: { title: 'How to pack and ship cards safely', excerpt: 'Practical steps for single cards, lots and graded holders: preparation, protection, parcel checks and documentation.' },
  },
  {
    slug: 'pos-na-fylaxeis-kartes-kai-komiks', tags: ['Storage', 'Cards', 'Comics'],
    el: { title: 'Πώς να φυλάξεις κάρτες και κόμικς', excerpt: 'Οργάνωσε χώρο, προστατευτικά υλικά και κατάλογο συλλογής, με προσεκτικό χειρισμό και έγκαιρο έλεγχο για αλλαγές.' },
    en: { title: 'How to store cards and comics', excerpt: 'Organise your storage space, protective materials and collection inventory, with careful handling and regular checks for changes.' },
  },
  {
    slug: 'pos-na-elegxeis-gnisiotita-kartas-pokemon', tags: ['Pokémon', 'Authenticity', 'Cards'],
    el: { title: 'Πώς να ελέγξεις αν μια κάρτα Pokémon είναι γνήσια', excerpt: 'Ένας πρακτικός έλεγχος έκδοσης, φωτογραφιών, λεπτομερειών και πιστοποίησης πριν αγοράσεις μια κάρτα Pokémon.' },
    en: { title: 'How to check whether a Pokémon card is genuine', excerpt: 'A practical review of the edition, photographs, physical details and certification before buying a Pokémon card.' },
  },
  {
    slug: 'ti-simainei-psa-10-kai-pote-axizei-to-grading', tags: ['Grading', 'PSA', 'CGC', 'Cards'],
    el: { title: 'Τι σημαίνει PSA 10 και πότε αξίζει το grading', excerpt: 'Πώς διαβάζεις έναν βαθμό, τι ελέγχεις στο πιστοποιητικό και πώς υπολογίζεις το συνολικό κόστος πριν στείλεις μια κάρτα.' },
    en: { title: 'What PSA 10 means and when grading makes sense', excerpt: 'Read a grade correctly, check the certification and work through the full cost before submitting a card.' },
  },
]
export const editorialGuideSlugs = new Set(editorialGuideIndex.map(guide => guide.slug))
export const getEditorialGuideIndex = (locale = 'el') => editorialGuideIndex.map(guide => ({
  ...guide[locale === 'en' ? 'en' : 'el'], id: `editorial:${guide.slug}`, slug: guide.slug, tags: guide.tags,
  category: locale === 'en' ? 'Collector guides' : 'Οδηγοί συλλεκτών',
  visual: { label: locale === 'en' ? 'Collector guide' : 'Οδηγός συλλέκτη' },
  publishedAt: '2026-10-23T00:00:00Z', updatedAt: '2026-10-23T00:00:00Z', editorial: true,
  readTime: locale === 'en' ? '6–8 min' : '6–8 λεπτά', featured: false,
}))

export const mergeEditorialGuides = (posts = [], locale = 'el') => [
  ...posts.filter(post => !editorialGuideSlugs.has(post.slug)), ...getEditorialGuideIndex(locale),
]
