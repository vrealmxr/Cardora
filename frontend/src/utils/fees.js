// Mirrors the flat-fee tier in backend/app/Support/MarketplaceSellerFeeCalculator.php.
// Below this threshold the seller keeps the full item price and Cardora's flat fee is
// added to what the buyer pays instead of being deducted from the seller's payout.
export const LOW_VALUE_FEE_THRESHOLD = 5.0
export const LOW_VALUE_FEE_STANDARD = 1.0
export const LOW_VALUE_FEE_PRO = 0.75

export function isLowValueFeeTier(subtotal) {
  const amount = Number(subtotal ?? 0)
  return amount > 0 && amount <= LOW_VALUE_FEE_THRESHOLD
}

export function calculateLowValueFee(subtotal, isPro) {
  if (!isLowValueFeeTier(subtotal)) return 0
  return isPro ? LOW_VALUE_FEE_PRO : LOW_VALUE_FEE_STANDARD
}
