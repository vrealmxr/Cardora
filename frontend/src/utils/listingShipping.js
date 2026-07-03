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
  calculation: 'auto_rate_by_package',
  carrier: 'DHL Express',
  defaultFee: 0,
  packageDefaults: DEFAULT_PACKAGE_DETAILS,
})

export const DEFAULT_DOMESTIC_SHIPPING = DHL_DOMESTIC_SHIPPING

export const DHL_VOLUMETRIC_DIVISOR = 5000

export const DHL_DOMESTIC_BASE_RATE_UP_TO_1KG = 7.04
export const DHL_DOMESTIC_HALF_KG_STEP_FEE = 1.69
export const DHL_DOMESTIC_ONE_KG_STEP_FEE = 3.38
export const DHL_DOMESTIC_BASE_RATE_AT_30KG = 105.03
export const DHL_DOMESTIC_SURCHARGES = Object.freeze({
  oversizePiece: Object.freeze({
    code: 'oversize_piece',
    fee: 12,
    maxLongestSideCm: 100,
    maxSecondLongestSideCm: 80,
  }),
  nonConveyableWeight: Object.freeze({
    code: 'non_conveyable_weight',
    fee: 12,
    minActualWeightKg: 25,
    maxActualWeightKg: 70,
  }),
  overweightPiece: Object.freeze({
    code: 'overweight_piece',
    fee: 50,
    minBillableWeightKg: 70,
  }),
})

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
  const parcelType = firstDefined(payload.domesticParcelType, payload.parcelType, payload?.domestic?.parcel_type)
  const usesBoxNowParcelRate =
    payload.domesticShippingMode === 'legacy_boxnow' ||
    String(payload.domesticShippingCarrier ?? payload?.domestic?.carrier ?? '')
      .trim()
      .toLowerCase() === 'boxnow'

  if (usesBoxNowParcelRate && parcelType !== undefined) {
    return resolveParcelRate({ parcelType, countryCode: 'GR' })
  }

  return resolveDhlDomesticShippingFee(normalizePackageDetails(payload))
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

export const calculateDhlBillableWeightKg = (payload = {}) => {
  const packageDetails = normalizePackageDetails(payload)
  const actualWeight = Math.max(parseAmountInput(packageDetails.weightKg), DEFAULT_PACKAGE_DETAILS.weightKg)
  const volumetricWeight =
    (Math.max(parseAmountInput(packageDetails.lengthCm), 1) *
      Math.max(parseAmountInput(packageDetails.widthCm), 1) *
      Math.max(parseAmountInput(packageDetails.heightCm), 1)) /
    DHL_VOLUMETRIC_DIVISOR

  return Math.max(actualWeight, volumetricWeight)
}

const roundWeightUp = (weightKg, stepKg) =>
  Math.ceil((Math.max(parseAmountInput(weightKg), 0) + Number.EPSILON) / stepKg) * stepKg

export const resolveDhlDomesticBaseFee = (payload = {}) => {
  const billableWeightKg = Math.max(calculateDhlBillableWeightKg(payload), DEFAULT_PACKAGE_DETAILS.weightKg)

  if (billableWeightKg <= 1) {
    return roundMoney(DHL_DOMESTIC_BASE_RATE_UP_TO_1KG)
  }

  if (billableWeightKg <= 30) {
    const ratedWeightKg = roundWeightUp(billableWeightKg - 1, 0.5)
    return roundMoney(
      DHL_DOMESTIC_BASE_RATE_UP_TO_1KG + (ratedWeightKg / 0.5) * DHL_DOMESTIC_HALF_KG_STEP_FEE,
    )
  }

  const ratedWeightKg = roundWeightUp(billableWeightKg - 30, 1)
  return roundMoney(
    DHL_DOMESTIC_BASE_RATE_AT_30KG + ratedWeightKg * DHL_DOMESTIC_ONE_KG_STEP_FEE,
  )
}

export const resolveDhlDomesticSurcharges = (payload = {}) => {
  const packageDetails = normalizePackageDetails(payload)
  const actualWeightKg = Math.max(parseAmountInput(packageDetails.weightKg), DEFAULT_PACKAGE_DETAILS.weightKg)
  const billableWeightKg = Math.max(calculateDhlBillableWeightKg(payload), DEFAULT_PACKAGE_DETAILS.weightKg)
  const sortedSides = [
    Math.max(parseAmountInput(packageDetails.lengthCm), 1),
    Math.max(parseAmountInput(packageDetails.widthCm), 1),
    Math.max(parseAmountInput(packageDetails.heightCm), 1),
  ].sort((left, right) => right - left)
  const [longestSideCm, secondLongestSideCm] = sortedSides
  const surcharges = []

  if (
    longestSideCm > DHL_DOMESTIC_SURCHARGES.oversizePiece.maxLongestSideCm ||
    secondLongestSideCm > DHL_DOMESTIC_SURCHARGES.oversizePiece.maxSecondLongestSideCm
  ) {
    surcharges.push(DHL_DOMESTIC_SURCHARGES.oversizePiece)
  }

  if (billableWeightKg > DHL_DOMESTIC_SURCHARGES.overweightPiece.minBillableWeightKg) {
    surcharges.push(DHL_DOMESTIC_SURCHARGES.overweightPiece)
  } else if (
    actualWeightKg >= DHL_DOMESTIC_SURCHARGES.nonConveyableWeight.minActualWeightKg &&
    actualWeightKg <= DHL_DOMESTIC_SURCHARGES.nonConveyableWeight.maxActualWeightKg &&
    !surcharges.some((entry) => entry.code === DHL_DOMESTIC_SURCHARGES.oversizePiece.code)
  ) {
    surcharges.push(DHL_DOMESTIC_SURCHARGES.nonConveyableWeight)
  }

  return surcharges.map((entry) => ({
    ...entry,
    fee: roundMoney(entry.fee),
  }))
}

export const resolveDhlDomesticShippingBreakdown = (payload = {}) => {
  const packageDetails = normalizePackageDetails(payload)
  const actualWeightKg = Math.max(parseAmountInput(packageDetails.weightKg), DEFAULT_PACKAGE_DETAILS.weightKg)
  const volumetricWeightKg = roundMoney(
    (Math.max(parseAmountInput(packageDetails.lengthCm), 1) *
      Math.max(parseAmountInput(packageDetails.widthCm), 1) *
      Math.max(parseAmountInput(packageDetails.heightCm), 1)) /
      DHL_VOLUMETRIC_DIVISOR,
  )
  const billableWeightKg = roundMoney(Math.max(actualWeightKg, volumetricWeightKg))
  const baseFee = resolveDhlDomesticBaseFee(payload)
  const surcharges = resolveDhlDomesticSurcharges(payload)
  const surchargeTotal = roundMoney(surcharges.reduce((total, entry) => total + entry.fee, 0))

  return {
    actualWeightKg: roundMoney(actualWeightKg),
    volumetricWeightKg,
    billableWeightKg,
    baseFee,
    surcharges,
    surchargeTotal,
    totalFee: roundMoney(baseFee + surchargeTotal),
  }
}

export const resolveDhlDomesticShippingFee = (payload = {}) => {
  return resolveDhlDomesticShippingBreakdown(payload).totalFee
}

export const calculateLotSelectionDomesticShipping = (selectedCardsCount) => {
  const count = Math.max(parseAmountInput(selectedCardsCount), 0)

  if (count <= 10) {
    return 2.5
  }

  return roundMoney(2.5 + (count - 10) * 0.25)
}
