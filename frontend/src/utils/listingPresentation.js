const roundMoney = value => Math.round(Number(value ?? 0) * 100) / 100

export const normalizeAuction = (auction) => {
  if (!auction) return null

  const currentBid = Number(auction.currentBid ?? auction.startingBid ?? 0)
  const bidIncrement = Number(auction.bidIncrement ?? 1)
  const reservePrice = Number(auction.reservePrice ?? 0)

  return {
    ...auction,
    startingBid: Number(auction.startingBid ?? 0),
    currentBid,
    reservePrice,
    bidIncrement,
    bidCount: Number(auction.bidCount ?? 0),
    watchers: Number(auction.watchers ?? 0),
    buyoutPrice: auction.buyoutPrice ? Number(auction.buyoutPrice) : null,
    reserveMet: reservePrice > 0 ? currentBid >= reservePrice : true,
  }
}

export const normalizeLot = (lot) => {
  if (!lot) return null

  const previewCards = Array.isArray(lot.previewCards) ? lot.previewCards.filter(Boolean) : []
  const totalCards = Number(lot.totalCards ?? previewCards.length ?? 0)
  const individualCards = Array.isArray(lot.individualCards)
    ? lot.individualCards
        .map((card) => ({
          id: String(card?.id ?? '').trim(),
          title: String(card?.title ?? '').trim(),
          price: roundMoney(card?.price ?? 0),
        }))
        .filter((card) => card.id && card.title && card.price > 0)
    : []

  return {
    ...lot,
    totalCards,
    guaranteedHits: Number(lot.guaranteedHits ?? 0),
    previewCards,
    themes: Array.isArray(lot.themes) ? lot.themes.filter(Boolean) : [],
    overflowCount: Math.max(totalCards - previewCards.length, 0),
    allowsIndividualPurchase: Boolean(lot.allowsIndividualPurchase),
    wholeLotPurchaseAvailable:
      lot.wholeLotPurchaseAvailable == null ? true : Boolean(lot.wholeLotPurchaseAvailable),
    individualCards,
    availableIndividualCardsCount: Number(
      lot.availableIndividualCardsCount ?? individualCards.length,
    ),
  }
}

export const normalizePublicListing = product => product ? ({ ...product, auction: normalizeAuction(product.auction), lot: normalizeLot(product.lot) }) : null
