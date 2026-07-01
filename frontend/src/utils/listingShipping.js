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

export const DEFAULT_PACKAGE_DETAILS = Object.freeze({
  weightKg: 0.5,
  lengthCm: 20,
  widthCm: 15,
  heightCm: 8,
})

export const DHL_DOMESTIC_SHIPPING = Object.freeze({
  calculation: 'manual_rate',
  carrier: 'DHL Express',
  defaultFee: 0,
  packageDefaults: DEFAULT_PACKAGE_DETAILS,
})

export const DEFAULT_DOMESTIC_SHIPPING = DHL_DOMESTIC_SHIPPING

export const PARCEL_TYPE_DOMESTIC_SHIPPING = Object.freeze({
  calculation: 'parcel_type',
  carrier: 'BoxNow',
  defaultParcelType: 'small',
  supportedParcelTypes: BOXNOW_PARCEL_TYPES,
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

export const getDomesticShippingConfig = () => DEFAULT_DOMESTIC_SHIPPING

export const normalizeParcelType = (
  value,
  fallback = PARCEL_TYPE_DOMESTIC_SHIPPING.defaultParcelType,
) => {
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

const firstDefined = (...values) => values.find((value) => value !== undefined && value !== null && value !== '')

export const resolveDomesticShippingFee = (payload = {}) => {
  const explicitFee = firstDefined(
    payload.domesticShippingFee,
    payload.shippingCost,
    payload.shipping_cost,
    payload?.domestic?.fee,
  )

  if (explicitFee !== undefined) {
    return Math.max(0, roundMoney(parseAmountInput(explicitFee)))
  }

  const parcelType = firstDefined(payload.domesticParcelType, payload.parcelType, payload?.domestic?.parcel_type)
  if (parcelType !== undefined) {
    return resolveParcelRate({ parcelType, countryCode: 'GR' })
  }

  return 0
}

export const resolveCyprusShippingFee = (payload = {}) => {
  const parcelType = firstDefined(payload.domesticParcelType, payload.parcelType, payload?.domestic?.parcel_type)
  if (parcelType === undefined) {
    return 0
  }

  return resolveParcelRate({ parcelType, countryCode: 'CY' })
}

const normalizePositiveNumber = (value, fallback) => {
  const numericValue = parseAmountInput(value)
  return numericValue > 0 ? numericValue : fallback
}

export const normalizePackageDetails = (payload = {}) => {
  const packageDetails = payload.package ?? payload.packageDetails ?? payload.attributes?.shipping?.package ?? {}

  return {
    weightKg: normalizePositiveNumber(
      firstDefined(packageDetails.weight_kg, packageDetails.weightKg, payload.packageWeightKg),
      DEFAULT_PACKAGE_DETAILS.weightKg,
    ),
    lengthCm: normalizePositiveNumber(
      firstDefined(packageDetails.length_cm, packageDetails.lengthCm, payload.packageLengthCm),
      DEFAULT_PACKAGE_DETAILS.lengthCm,
    ),
    widthCm: normalizePositiveNumber(
      firstDefined(packageDetails.width_cm, packageDetails.widthCm, payload.packageWidthCm),
      DEFAULT_PACKAGE_DETAILS.widthCm,
    ),
    heightCm: normalizePositiveNumber(
      firstDefined(packageDetails.height_cm, packageDetails.heightCm, payload.packageHeightCm),
      DEFAULT_PACKAGE_DETAILS.heightCm,
    ),
  }
}

export const calculateLotSelectionDomesticShipping = (selectedCardsCount) => {
  const count = Math.max(parseAmountInput(selectedCardsCount), 0)

  if (count <= 10) {
    return 2.5
  }

  return roundMoney(2.5 + (count - 10) * 0.25)
}
