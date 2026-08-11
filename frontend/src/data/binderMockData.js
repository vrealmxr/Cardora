// Mock data for the Cardora Binder sub-application.
// Everything here stands in for what will eventually come from the backend
// (set/card catalogs imported from CSV, per-user ownership, scraped prices).
// Shapes are deliberately close to what the real API will likely return so
// swapping this out later is mostly a data-fetching change, not a UI rewrite.

export const BINDER_CATEGORIES = [
  { value: 'pokemon', label: { el: 'Pokémon', en: 'Pokémon' } },
  { value: 'basketball', label: { el: 'Μπάσκετ', en: 'Basketball' } },
  { value: 'football', label: { el: 'Ποδόσφαιρο', en: 'Football' } },
]

export const BINDER_BRANDS = [
  { value: 'pokemon-company', label: 'The Pokémon Company' },
  { value: 'panini', label: 'Panini' },
  { value: 'topps', label: 'Topps' },
]

const RARITY_THEME = {
  common: { el: 'Common', en: 'Common' },
  uncommon: { el: 'Uncommon', en: 'Uncommon' },
  rare: { el: 'Rare', en: 'Rare' },
  holo: { el: 'Holo Rare', en: 'Holo Rare' },
  rookie: { el: 'Rookie', en: 'Rookie' },
  insert: { el: 'Insert', en: 'Insert' },
  auto: { el: 'Autograph', en: 'Autograph' },
  secret: { el: 'Secret Rare', en: 'Secret Rare' },
}

export const RARITY_OPTIONS = Object.entries(RARITY_THEME).map(([value, label]) => ({ value, label }))

function buildCards({ setId, category, count, names, rarities, priceRange, teamOrType }) {
  const cards = []
  for (let i = 0; i < count; i += 1) {
    const number = String(i + 1).padStart(3, '0')
    const rarity = rarities[i % rarities.length]
    const [min, max] = priceRange
    const price = Math.round((min + ((max - min) * ((i * 37) % 100)) / 100) * 100) / 100
    cards.push({
      id: `${setId}-${number}`,
      setId,
      number: `${number}/${String(count).padStart(3, '0')}`,
      name: names[i % names.length],
      category,
      rarity,
      subtitle: teamOrType[i % teamOrType.length],
      estimatedPrice: price,
    })
  }
  return cards
}

const pokemonNames = [
  'Charizard ex', 'Pikachu', 'Mew ex', 'Gardevoir ex', 'Sprigatito', 'Fuecoco', 'Quaxly',
  'Greninja', 'Lucario', 'Dragapult ex', 'Iron Hands ex', 'Miraidon ex', 'Koraidon ex',
  'Eevee', 'Umbreon', 'Espeon', 'Gengar', 'Snorlax', 'Bulbasaur', 'Squirtle', 'Rayquaza',
  'Tyranitar', 'Absol', 'Lapras', 'Aerodactyl',
]
const pokemonTypes = ['Fire', 'Water', 'Grass', 'Psychic', 'Electric', 'Dragon', 'Dark', 'Fighting']

const euroleagueNames = [
  'Shane Larkin', 'Vasilije Micić', 'Mike James', 'Kostas Sloukas', 'Nikola Milutinov',
  'Sasha Vezenkov', 'Facundo Campazzo', 'Nigel Williams-Goss', 'Achille Polonara',
  'Giannoulis Larentzakis', 'Dinos Mitoglou', 'Thomas Walkup', 'Nando De Colo',
  'Alec Peters', 'Kendrick Perry', 'Marius Grigonis', 'Tornike Shengelia', 'Wade Baldwin IV',
  'Chima Moneke', 'Lorenzo Brown',
]
const euroleagueClubs = ['Panathinaikos', 'Olympiacos', 'Real Madrid', 'Fenerbahçe', 'Barcelona', 'AS Monaco']

const nbaNames = [
  'Victor Wembanyama', 'Luka Dončić', 'Giannis Antetokounmpo', 'Nikola Jokić',
  'Anthony Edwards', 'Chet Holmgren', 'Paolo Banchero', 'Jayson Tatum',
  'Shai Gilgeous-Alexander', 'Devin Booker', 'LaMelo Ball', 'Ja Morant',
  'Zion Williamson', 'Tyrese Haliburton', 'Scottie Barnes', 'Franz Wagner',
  'Cade Cunningham', 'Evan Mobley', 'Jalen Green', 'Alperen Şengün',
]
const nbaTeams = ['Spurs', 'Mavericks', 'Bucks', 'Nuggets', 'Timberwolves', 'Thunder', 'Celtics']

const footballNames = [
  'Jude Bellingham', 'Kylian Mbappé', 'Erling Haaland', 'Vinícius Júnior', 'Bukayo Saka',
  'Pedri', 'Jamal Musiala', 'Florian Wirtz', 'Lamine Yamal', 'Rodrygo',
  'Victor Osimhen', 'Kobbie Mainoo', 'Federico Valverde', 'Rafael Leão',
  'Khvicha Kvaratskhelia', 'Cole Palmer', 'Declan Rice', 'Gavi',
]
const footballClubs = ['Real Madrid', 'PSG', 'Man City', 'Barcelona', 'Arsenal', 'Bayern']

export const BINDER_SETS = [
  {
    id: 'sv-151',
    slug: 'pokemon-scarlet-violet-151',
    name: 'Scarlet & Violet: 151',
    category: 'pokemon',
    brand: 'pokemon-company',
    brandLabel: 'The Pokémon Company',
    year: 2023,
    totalCards: 25,
    theme: 'pokemon',
    description: {
      el: 'Το θρυλικό σετ με τα πρώτα 151 Pokémon, σε ανανεωμένη premium έκδοση.',
      en: 'The legendary set featuring the original 151 Pokémon, in a refreshed premium edition.',
    },
  },
  {
    id: 'euro-25-26',
    slug: 'panini-euroleague-2025-26',
    name: 'Euroleague 2025-26',
    category: 'basketball',
    brand: 'panini',
    brandLabel: 'Panini',
    year: 2025,
    totalCards: 20,
    theme: 'basketball',
    description: {
      el: 'Οι πρωταγωνιστές της φετινής Euroleague σε επίσημες κάρτες συλλογής Panini.',
      en: "This season's Euroleague protagonists on official Panini collector cards.",
    },
  },
  {
    id: 'nba-chrome-25-26',
    slug: 'topps-nba-chrome-2025-26',
    name: 'NBA Chrome 2025-26',
    category: 'basketball',
    brand: 'topps',
    brandLabel: 'Topps',
    year: 2025,
    totalCards: 20,
    theme: 'basketball',
    description: {
      el: 'Το κλασικό chrome finish της Topps με τα μεγαλύτερα ονόματα του NBA.',
      en: "Topps' signature chrome finish featuring the biggest names in the NBA.",
    },
  },
  {
    id: 'match-attax-25-26',
    slug: 'topps-match-attax-2025-26',
    name: 'Match Attax 2025-26',
    category: 'football',
    brand: 'topps',
    brandLabel: 'Topps',
    year: 2025,
    totalCards: 18,
    theme: 'football',
    description: {
      el: 'Οι κορυφαίοι παίκτες της Ευρώπης στο πιο δημοφιλές football trading card game.',
      en: "Europe's top players in the most popular football trading card game.",
    },
  },
]

export const BINDER_CARDS = {
  'sv-151': buildCards({
    setId: 'sv-151',
    category: 'pokemon',
    count: 25,
    names: pokemonNames,
    rarities: ['common', 'uncommon', 'rare', 'holo', 'secret'],
    priceRange: [0.5, 180],
    teamOrType: pokemonTypes,
  }),
  'euro-25-26': buildCards({
    setId: 'euro-25-26',
    category: 'basketball',
    count: 20,
    names: euroleagueNames,
    rarities: ['common', 'rare', 'insert', 'auto'],
    priceRange: [1, 65],
    teamOrType: euroleagueClubs,
  }),
  'nba-chrome-25-26': buildCards({
    setId: 'nba-chrome-25-26',
    category: 'basketball',
    count: 20,
    names: nbaNames,
    rarities: ['common', 'rookie', 'rare', 'auto'],
    priceRange: [2, 240],
    teamOrType: nbaTeams,
  }),
  'match-attax-25-26': buildCards({
    setId: 'match-attax-25-26',
    category: 'football',
    count: 18,
    names: footballNames,
    rarities: ['common', 'rare', 'insert'],
    priceRange: [0.8, 40],
    teamOrType: footballClubs,
  }),
}

// Mock "signed in user" ownership state — which sets are registered in the
// user's binder, and which specific card ids they already have.
function ownedIds(setId, indices) {
  return indices.map((i) => `${setId}-${String(i).padStart(3, '0')}`)
}

export const MY_BINDER = {
  registeredSetIds: ['sv-151', 'nba-chrome-25-26'],
  ownedCardIds: [
    ...ownedIds('sv-151', [1, 2, 4, 5, 6, 8, 9, 11, 12, 14, 15, 18, 20, 23]),
    ...ownedIds('nba-chrome-25-26', [1, 2, 3, 5, 7, 10, 13, 16, 19]),
  ],
}

export function getSetById(setId) {
  return BINDER_SETS.find((set) => set.id === setId) ?? null
}

export function getCardsForSet(setId) {
  return BINDER_CARDS[setId] ?? []
}

export function isCardOwned(cardId) {
  return MY_BINDER.ownedCardIds.includes(cardId)
}

export function getSetCompletion(setId) {
  const cards = getCardsForSet(setId)
  if (cards.length === 0) return { owned: 0, total: 0, percent: 0, value: 0 }
  const owned = cards.filter((card) => isCardOwned(card.id))
  const value = owned.reduce((sum, card) => sum + card.estimatedPrice, 0)
  return {
    owned: owned.length,
    total: cards.length,
    percent: Math.round((owned.length / cards.length) * 100),
    value: Math.round(value * 100) / 100,
  }
}

export function getMyRegisteredSets() {
  return MY_BINDER.registeredSetIds.map((setId) => ({
    set: getSetById(setId),
    completion: getSetCompletion(setId),
  }))
}

export function getTopValueOwnedCards(limit = 5) {
  const all = MY_BINDER.registeredSetIds.flatMap((setId) =>
    getCardsForSet(setId)
      .filter((card) => isCardOwned(card.id))
      .map((card) => ({ ...card, setName: getSetById(setId)?.name })),
  )
  return all.sort((a, b) => b.estimatedPrice - a.estimatedPrice).slice(0, limit)
}

export function getPortfolioTotals() {
  const registered = getMyRegisteredSets()
  const totalValue = registered.reduce((sum, r) => sum + r.completion.value, 0)
  const totalOwned = registered.reduce((sum, r) => sum + r.completion.owned, 0)
  const totalCards = registered.reduce((sum, r) => sum + r.completion.total, 0)
  const avgCompletion = registered.length
    ? Math.round(registered.reduce((sum, r) => sum + r.completion.percent, 0) / registered.length)
    : 0
  return {
    totalValue: Math.round(totalValue * 100) / 100,
    totalOwned,
    totalCards,
    setsRegistered: registered.length,
    avgCompletion,
  }
}
