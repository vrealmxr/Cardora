// Read the capability for the action, not the seller onboarding completion flag.
// Authentication and authoritative enforcement remain in the API.
export function marketplaceGateMessage(user, access, mode = 'trade', locale = 'el') {
  if (!user || !access) return null
  const key = { buy: 'can_buy', sell: 'can_sell', bid: 'can_bid', draw: 'can_join_draws' }[mode] ?? 'is_marketplace_ready'
  if (access[key] === true) return null
  const en = locale === 'en'
  if (mode === 'buy') return en ? 'Purchases are currently unavailable for this account.' : 'Οι αγορές δεν είναι διαθέσιμες αυτή τη στιγμή για αυτόν τον λογαριασμό.'
  if (mode === 'bid') return en ? 'Bidding is currently unavailable for this account.' : 'Οι προσφορές δεν είναι διαθέσιμες αυτή τη στιγμή για αυτόν τον λογαριασμό.'
  if (mode === 'draw') {
    return access.participation_blocking_message || access.blocking_message || (en
      ? 'Complete the required verification steps to join a draw.'
      : 'Ολοκλήρωσε την απαιτούμενη επαλήθευση για κληρώσεις.')
  }
  return access.blocking_message || (en
    ? 'Complete verification, Stripe payouts and your shipping details to sell.'
    : 'Ολοκλήρωσε την επαλήθευση, τις πληρωμές Stripe και τα στοιχεία αποστολής για να πουλήσεις.')
}
