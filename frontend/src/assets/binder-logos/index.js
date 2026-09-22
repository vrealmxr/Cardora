import pokemon from './pokemon.png'
import yugioh from './yugioh.png'
import magicTheGathering from './magic-the-gathering.png'
import onePiece from './one-piece.png'
import disney from './disney.png'
import starWars from './star-wars.png'
import riftbound from './riftbound.png'

// Real franchise logos, keyed by the BinderGame slug. Games without a real
// logo yet just fall back to a plain text tile — nothing breaks.
const GAME_LOGOS = {
  pokemon,
  yugioh,
  'magic-the-gathering': magicTheGathering,
  'one-piece': onePiece,
  disney,
  'star-wars': starWars,
  riftbound,
}

export default GAME_LOGOS

// Slugs whose source art is a plain white/monochrome mark meant for a dark
// surface — on our light cream cards they need to be flipped dark to read.
export const MONOCHROME_LOGO_SLUGS = new Set(['disney'])
