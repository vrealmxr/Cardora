export const BOXNOW_PARCEL_TYPES = Object.freeze(['mini', 'small', 'medium', 'large'])

export const BOXNOW_PARCEL_DIMENSIONS_CM = Object.freeze({
  mini: Object.freeze({ length: 17, width: 45, height: 8 }),
  small: Object.freeze({ length: 17, width: 45, height: 20 }),
  medium: Object.freeze({ length: 36, width: 45, height: 20 }),
  large: Object.freeze({ length: 60, width: 45, height: 36 }),
})

export const BOXNOW_PARCEL_RATES = Object.freeze({
  gr: Object.freeze({
    mini: 1.5,
    small: 3,
    medium: 4,
    large: 8,
  }),
  cy: Object.freeze({
    small: 7,
    medium: 10,
  }),
})

export const DEFAULT_DOMESTIC_SHIPPING = Object.freeze({
  calculation: 'parcel_type',
  carrier: 'BoxNow',
  defaultParcelType: 'small',
  supportedParcelTypes: BOXNOW_PARCEL_TYPES,
})

export const PARCEL_TYPE_DOMESTIC_SHIPPING = Object.freeze({
  ...DEFAULT_DOMESTIC_SHIPPING,
})

export const parseAmountInput = (value) => {
  if (typeof value === 'number') return Number.isFinite(value) ? value : 0

  const normalized = String(value ?? '')
    .trim()
    .replace(/\s+/g, '')
    .replace(',', '.')

  if (!normalized) return 0

  const numericValue = Number(normalized)
  return Number.isFinite(numericValue) ? numericValue : 0
}

export const roundMoney = (value) =>
  Math.round((Number(value ?? 0) + Number.EPSILON) * 100) / 100

export const getDomesticShippingConfig = (categoryId = 'cards') =>
  ['cards', 'figures', 'comics', 'misc'].includes(categoryId)
    ? PARCEL_TYPE_DOMESTIC_SHIPPING
    : DEFAULT_DOMESTIC_SHIPPING

export const normalizeParcelType = (value, fallback = DEFAULT_DOMESTIC_SHIPPING.defaultParcelType) => {
  const normalized = String(value ?? '').trim().toLowerCase()
  if (BOXNOW_PARCEL_TYPES.includes(normalized)) {
    return normalized
  }

  return BOXNOW_PARCEL_TYPES.includes(fallback) ? fallback : 'small'
}

export const getParcelDimensionsCm = (parcelType) => {
  const normalizedType = normalizeParcelType(parcelType)
  return BOXNOW_PARCEL_DIMENSIONS_CM[normalizedType] ?? BOXNOW_PARCEL_DIMENSIONS_CM.small
}

export const resolveParcelRate = ({
  parcelType,
  countryCode = 'GR',
} = {}) => {
  const normalizedType = normalizeParcelType(parcelType)
  const normalizedCountryCode = String(countryCode ?? '').trim().toUpperCase()
  const countryKey = normalizedCountryCode === 'CY' ? 'cy' : 'gr'
  const countryRates = BOXNOW_PARCEL_RATES[countryKey] ?? BOXNOW_PARCEL_RATES.gr
  const parcelRate = countryRates?.[normalizedType]

  return Number.isFinite(parcelRate) ? roundMoney(parcelRate) : 0
}

export const resolveDomesticShippingFee = (payload = {}) => {
  const config = getDomesticShippingConfig(payload.categoryId)
  const parcelType = normalizeParcelType(
    payload.domesticParcelType ?? payload.parcelType ?? config.defaultParcelType,
    config.defaultParcelType,
  )

  return resolveParcelRate({ parcelType, countryCode: 'GR' })
}

export const resolveCyprusShippingFee = (payload = {}) => {
  const config = getDomesticShippingConfig(payload.categoryId)
  const parcelType = normalizeParcelType(
    payload.domesticParcelType ?? payload.parcelType ?? config.defaultParcelType,
    config.defaultParcelType,
  )

  return resolveParcelRate({ parcelType, countryCode: 'CY' })
}

export const calculateLotSelectionDomesticShipping = (selectedCardsCount) => {
  const count = Math.max(parseAmountInput(selectedCardsCount), 0)

  if (count <= 10) {
    return 2.5
  }

  return roundMoney(2.5 + (count - 10) * 0.25)
}
