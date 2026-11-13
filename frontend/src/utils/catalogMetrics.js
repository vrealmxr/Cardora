// Counts describe the current browsable catalogue, not all historical listings.
export function categoriesWithCatalogMetrics(categories, products) {
  const counts = new Map()
  for (const product of products) {
    if (!counts.has(product.categoryId)) counts.set(product.categoryId, { listings: 0, sellers: new Set() })
    const entry = counts.get(product.categoryId)
    entry.listings++
    if (product.seller?.verified && product.sellerId != null) entry.sellers.add(product.sellerId)
  }
  return categories.map(category => ({
    ...category,
    metrics: { ...category.metrics, listings: counts.get(category.id)?.listings ?? 0,
      verified: counts.get(category.id)?.sellers.size ?? 0 },
  }))
}
