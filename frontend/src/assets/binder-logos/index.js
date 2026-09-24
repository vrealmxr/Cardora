import pokemon from './pokemon.png'
import yugioh from './yugioh.png'
import magicTheGathering from './magic-the-gathering.png'
import onePiece from './one-piece.png'
import disney from './disney.png'
import starWars from './star-wars.png'
import riftbound from './riftbound.png'

// Real franchise logos, keyed by the BinderGame slug. Games without a real
// logo yet just fall back to a plain text tile — nothing breaks.
//
// 'disney'/'star-wars' were renamed to 'disney-lorcana'/'star-wars-miniatures'
// (disney always meant Lorcana specifically; star-wars turned out to be the
// old Star Wars Miniatures game, not the current Star Wars: Unlimited) --
// both old and new keys stay mapped so an already-bookmarked /binder/star-wars
// URL (whose gameSlug param is read straight from the URL, not re-resolved
// through the renamed API row) still finds its logo.
const GAME_LOGOS = {
  pokemon,
  yugioh,
  'magic-the-gathering': magicTheGathering,
  'one-piece': onePiece,
  disney,
  'disney-lorcana': disney,
  'star-wars': starWars,
  'star-wars-miniatures': starWars,
  riftbound,
}

export default GAME_LOGOS

// Slugs whose source art is a plain white/monochrome mark meant for a dark
// surface — on our light cream cards they need to be flipped dark to read.
export const MONOCHROME_LOGO_SLUGS = new Set(['disney', 'disney-lorcana'])
