import { useEffect, useMemo, useRef, useState } from 'react'
import { X } from 'lucide-react'
import { Link, useSearchParams } from 'react-router-dom'
import ListingPreviewCard from '@/components/listings/ListingPreviewCard'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input, Select, Textarea } from '@/components/ui/Input'
import SectionHeader from '@/components/ui/SectionHeader'
import { getListingFormData } from '@/data/listingFormData'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { formatCurrency } from '@/utils/formatters'
import { translateCreateListingGreek } from '@/utils/createListingTranslations'
import { cn } from '@/utils/helpers'
import { normalizePotentialMojibake } from '@/utils/textEncoding'
import {
  BOXNOW_PARCEL_TYPES,
  DEFAULT_DOMESTIC_SHIPPING,
  getDomesticShippingConfig,
  getParcelDimensionsCm,
  normalizePackageDetails,
  normalizeParcelType,
  parseAmountInput,
  resolveCyprusShippingFee,
  resolveDomesticShippingFee,
  roundMoney,
} from '@/utils/listingShipping'

const categoryVisuals = {
  cards: 'from-[#1d3963] via-[#111f36] to-[#08111d]',
  figures: 'from-[#5f233d] via-[#1a2034] to-[#08111d]',
  comics: 'from-[#3f2b6e] via-[#161f35] to-[#08111d]',
  misc: 'from-[#214f4d] via-[#122033] to-[#08111d]',
}

const CARD_BUNDLE_MODES = {
  single: 'single_card',
  lot: 'loot_lot',
}

const splitCommaList = (value) =>
  String(value || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)

const appendUniqueListItem = (items, value) => {
  const normalized = String(value || '').trim()
  if (!normalized) return items
  if (items.some((item) => item.toLowerCase() === normalized.toLowerCase())) return items
  return [...items, normalized]
}

const getDynamicFieldNames = (templates) => [
  ...new Set(Object.values(templates).flatMap((template) => template.attributeFields.map((field) => field.name))),
]

const getTemplateFranchiseGroups = (template, subcategory = '') =>
  template?.franchiseGroupsBySubcategory?.[subcategory] ?? template?.franchiseGroups ?? []

const getFranchisesForGroup = (template, franchiseGroup = '', subcategory = '') => {
  const groups = getTemplateFranchiseGroups(template, subcategory)
  if (!groups.length) return template?.franchises ?? []

  const activeGroup = groups.find((group) => group.value === franchiseGroup) ?? groups[0]
  return activeGroup?.options ?? []
}

const getFranchiseGroupLabel = (template, franchiseGroup = '', subcategory = '') => {
  const groups = getTemplateFranchiseGroups(template, subcategory)
  if (!groups.length) return ''

  return (groups.find((group) => group.value === franchiseGroup) ?? groups[0])?.label ?? ''
}

const getDefaultFranchiseValueForGroup = (template, franchiseGroup = '', subcategory = '') => {
  const options = getFranchisesForGroup(template, franchiseGroup, subcategory)
  if (options.length) return options[0]
  return getFranchiseGroupLabel(template, franchiseGroup, subcategory)
}

const getRarityOptionsForGroup = (template, franchiseGroup = '') => {
  const optionsByGroup = template?.rarityOptionsByFranchiseGroup ?? {}
  return optionsByGroup[franchiseGroup] ?? optionsByGroup.tcg ?? []
}

const getDomesticShippingProfileFromOptions = (commonOptions, categoryId = 'cards') =>
  commonOptions.domesticShippingByCategory?.[categoryId] ??
  commonOptions.domesticShippingByCategory?.default ??
  commonOptions.domesticShipping ??
  getDomesticShippingConfig(categoryId)

const getPackagingOptionsForCategory = (commonOptions, categoryId = 'cards') =>
  commonOptions.packagingByCategory?.[categoryId] ??
  commonOptions.packagingByCategory?.default ??
  commonOptions.packaging ??
  []

const getPhotoChecklistForCategory = (commonOptions, categoryId = 'cards') =>
  commonOptions.photoChecklistByCategory?.[categoryId] ??
  commonOptions.photoChecklistByCategory?.default ??
  commonOptions.photoChecklist ??
  []

const getParcelTypeLabel = (locale, parcelType) => {
  const labels = {
    mini: locale === 'en' ? 'Mini parcel' : 'Mini δέμα',
    small: locale === 'en' ? 'Small parcel' : 'Μικρό δέμα',
    medium: locale === 'en' ? 'Medium parcel' : 'Μεσαίο δέμα',
    large: locale === 'en' ? 'Large parcel' : 'Μεγάλο δέμα',
  }

  return labels[parcelType] ?? labels.small
}

const getParcelTypeOptions = (locale) =>
  BOXNOW_PARCEL_TYPES.map((parcelType) => ({
    value: parcelType,
    label: getParcelTypeLabel(locale, parcelType),
  }))

const getTemplateDefaults = (templates, categoryId = 'cards') => {
  const template = templates[categoryId]
  const dynamicFieldNames = getDynamicFieldNames(templates)
  const subcategory = template?.subcategories?.[0] ?? ''
  const franchiseGroup = getTemplateFranchiseGroups(template, subcategory)[0]?.value ?? ''
  const franchises = getFranchisesForGroup(template, franchiseGroup, subcategory)
  const rarityOptions = getRarityOptionsForGroup(template, franchiseGroup)

  return {
    categoryId,
    subcategory,
    franchiseGroup,
    franchise: franchises[0] ?? template?.franchises?.[0] ?? '',
    brand: template?.brands?.[0] ?? '',
    condition: template?.conditions?.[0] ?? '',
    ...Object.fromEntries(
      dynamicFieldNames.map((fieldName) => {
        const fieldConfig = template?.attributeFields?.find((field) => field.name === fieldName)
        if (!fieldConfig) return [fieldName, '']
        if (fieldName === 'rarity' && categoryId === 'cards') {
          return [fieldName, rarityOptions[0] ?? fieldConfig.options?.[0] ?? '']
        }
        return [fieldName, fieldConfig.type === 'select' ? fieldConfig.options?.[0] ?? '' : '']
      }),
    ),
  }
}

const createDefaultForm = (templates, commonOptions) => ({
  ...getTemplateDefaults(templates, 'cards'),
  ...(() => {
    const domesticShipping = getDomesticShippingProfileFromOptions(commonOptions, 'cards')
    const packagingOptions = getPackagingOptionsForCategory(commonOptions, 'cards')
    const packageDefaults = domesticShipping.packageDefaults ?? DEFAULT_DOMESTIC_SHIPPING.packageDefaults

    return {
      shippingCost: 0,
      shippingMethods: [domesticShipping.carrier ?? DEFAULT_DOMESTIC_SHIPPING.carrier],
      packaging: packagingOptions[0] ?? '',
      domesticShippingCarrier: domesticShipping.carrier ?? DEFAULT_DOMESTIC_SHIPPING.carrier,
      domesticShippingMode: 'dhl_manual',
      domesticShippingFee: '',
      domesticParcelType: '',
      packageWeightKg: String(packageDefaults?.weightKg ?? 0.5),
      packageLengthCm: String(packageDefaults?.lengthCm ?? 20),
      packageWidthCm: String(packageDefaults?.widthCm ?? 15),
      packageHeightCm: String(packageDefaults?.heightCm ?? 8),
    }
  })(),
  title: '',
  subtitle: '',
  description: '',
  year: '',
  quantity: 1,
  tags: '',
  mediaFiles: [],
  uploadedMedia: [],
  mediaNotes: '',
  price: '',
  oldPrice: '',
  minimumOffer: '',
  acceptOffers: true,
  availability: commonOptions.availability[0] ?? '',
  dispatchTime: commonOptions.dispatchTimes[1] ?? commonOptions.dispatchTimes[0] ?? '',
  shipInternational: false,
  internationalCarrier: commonOptions.internationalShipping?.carrier ?? 'DHL Express',
  internationalRates: Object.fromEntries(
    (commonOptions.internationalShipping?.zones ?? []).map((zone) => [zone.key, '']),
  ),
  shippingNotes: '',
  complianceAcknowledgements: [],
  saleFormat: commonOptions.saleFormats[0] ?? '',
  startingBid: '',
  reservePrice: '',
  bidIncrement: '5',
  auctionEndsAt: '',
  buyoutPrice: '',
  cardBundleMode: CARD_BUNDLE_MODES.single,
  lotCardCount: '',
  lotGuaranteedHits: '',
  lotSummary: '',
  lotCardConditionMix: '',
  allowIndividualLotPurchase: false,
  lotIndividualCards: [],
  lotNamedCards: [],
  lotThemeTags: [],
  lotCardDraft: '',
  lotIndividualCardTitleDraft: '',
  lotIndividualCardPriceDraft: '',
  lotThemeDraft: '',
})

const toDateTimeLocalValue = (value) => {
  if (!value) return ''

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''

  date.setMinutes(date.getMinutes() - date.getTimezoneOffset())
  return date.toISOString().slice(0, 16)
}

const toInputAmount = (value) => {
  if (value == null || value === '') return ''

  const numericValue = Number(value)
  if (!Number.isFinite(numericValue)) return ''

  return String(roundMoney(numericValue))
}

const subtractDomesticShippingFromAmount = (value, shippingFee = 0) => {
  if (value == null || value === '') return ''

  const numericValue = Number(value)
  if (!Number.isFinite(numericValue)) return ''

  return String(Math.max(roundMoney(numericValue - Number(shippingFee || 0)), 0))
}

const getExistingListingShipping = (listing) =>
  listing?.attributes?.shipping ??
  listing?.product?.metadata?.shipping ??
  {}

const normalizeExistingListingMedia = (listing) =>
  (Array.isArray(listing?.product?.media) ? listing.product.media : [])
    .filter((item) => item?.url || item?.path)
    .map((item, index) => ({
      path: item.path ?? null,
      url: item.url ?? null,
      kind: item.kind ?? 'image',
      label: item.label ?? item.original_name ?? `Image ${index + 1}`,
      original_name: item.original_name ?? item.label ?? `Image ${index + 1}`,
    }))

const buildFormFromExistingListing = ({
  listing,
  categories,
  templates,
  commonOptions,
  auctionLabel,
  fixedPriceLabel,
  tradeLabel,
  includeExistingMedia = true,
}) => {
  const product = listing?.product ?? {}
  const attributes = listing?.attributes ?? {}
  const specifications = product?.specifications ?? {}
  const shipping = getExistingListingShipping(listing)
  const domesticShipping = shipping?.domestic ?? {}
  const internationalShipping = shipping?.international ?? {}
  const storedDomesticShippingFee = Number(domesticShipping?.fee ?? 0)
  const categoryId =
    categories.find((item) => Number(item.databaseId ?? 0) === Number(listing?.category_id))?.id ??
    categories.find((item) => item.slug === listing?.category?.slug)?.id ??
    product?.category?.slug ??
    listing?.category?.slug ??
    'cards'
  const template = templates[categoryId] ?? templates.cards
  const defaults = createDefaultForm(templates, commonOptions)
  const isLegacyBoxNow = Boolean(
    domesticShipping?.parcel_type ??
      domesticShipping?.parcelType ??
      domesticShipping?.package?.parcel_type ??
      String(listing?.shipping_profile ?? '').startsWith('boxnow'),
  )
  const domesticParcelType = isLegacyBoxNow
    ? normalizeParcelType(
        domesticShipping?.parcel_type ??
          domesticShipping?.parcelType ??
          domesticShipping?.package?.parcel_type ??
          'small',
        'small',
      )
    : ''
  const domesticShippingFee =
    storedDomesticShippingFee > 0
      ? storedDomesticShippingFee
      : resolveDomesticShippingFee({
          domesticShippingFee: listing?.shipping_cost ?? 0,
          domesticParcelType,
        })
  const isShippingIncludedInPrice = Boolean(domesticShipping?.included_in_price ?? isLegacyBoxNow)
  const packageDetails = normalizePackageDetails({
    package: domesticShipping?.package ?? shipping?.package ?? {},
    packageWeightKg: domesticShipping?.weight_kg ?? shipping?.weight_kg,
    packageLengthCm: domesticShipping?.length_cm ?? shipping?.length_cm,
    packageWidthCm: domesticShipping?.width_cm ?? shipping?.width_cm,
    packageHeightCm: domesticShipping?.height_cm ?? shipping?.height_cm,
  })
  const subcategoryCandidate =
    attributes.type_label ??
    product.product_type ??
    product.series ??
    attributes.series ??
    template?.subcategories?.[0] ??
    ''
  const subcategory = template?.subcategories?.includes(subcategoryCandidate)
    ? subcategoryCandidate
    : template?.subcategories?.[0] ?? subcategoryCandidate
  const franchiseGroups = getTemplateFranchiseGroups(template, subcategory)
  const existingFranchiseGroup =
    attributes.franchise_group ??
    specifications.franchise_group ??
    ''
  const franchiseGroup =
    franchiseGroups.find((group) => group.value === existingFranchiseGroup)?.value ??
    franchiseGroups[0]?.value ??
    ''
  const franchiseOptions = getFranchisesForGroup(template, franchiseGroup, subcategory)
  const existingFranchise = product.franchise ?? attributes.franchise ?? ''
  const lotConfiguration = product.lot_configuration ?? listing?.lot_snapshot ?? {}
  const namedCards = Array.isArray(lotConfiguration.named_cards)
    ? lotConfiguration.named_cards
    : Array.isArray(lotConfiguration.namedCards)
      ? lotConfiguration.namedCards
      : Array.isArray(lotConfiguration.preview_cards)
        ? lotConfiguration.preview_cards
        : Array.isArray(lotConfiguration.previewCards)
          ? lotConfiguration.previewCards
          : []
  const individualCards = Array.isArray(lotConfiguration.individual_cards)
    ? lotConfiguration.individual_cards
    : Array.isArray(lotConfiguration.individualCards)
      ? lotConfiguration.individualCards
      : []
  const lotThemes = Array.isArray(lotConfiguration.themes) ? lotConfiguration.themes : []
  const isLootLot =
    categoryId === 'cards' &&
    Boolean(
      product.is_lot ||
        lotConfiguration.total_cards ||
        lotConfiguration.totalCards ||
        namedCards.length,
    )

  return {
    ...defaults,
    categoryId,
    subcategory,
    franchiseGroup,
    franchise:
      existingFranchise ||
      franchiseOptions[0] ||
      getDefaultFranchiseValueForGroup(template, franchiseGroup, subcategory) ||
      '',
    brand:
      product.brand ??
      attributes.brand ??
      attributes.publisher ??
      template?.brands?.[0] ??
      '',
    condition:
      listing?.condition ??
      attributes.condition ??
      template?.conditions?.[0] ??
      '',
    rarity: listing?.rarity ?? attributes.rarity ?? '',
    title: product.title ?? listing?.title_snapshot ?? '',
    subtitle: product.subtitle ?? '',
    description: product.description ?? '',
    year:
      product.year != null
        ? String(product.year)
        : attributes.year != null
          ? String(attributes.year)
          : '',
    tags: Array.isArray(product.tags) ? product.tags.join(', ') : '',
    mediaFiles: [],
    uploadedMedia: includeExistingMedia ? normalizeExistingListingMedia(listing) : [],
    mediaNotes: listing?.packaging_notes ?? '',
    price:
      listing?.sale_format === 'auction'
        ? ''
        : isShippingIncludedInPrice
          ? subtractDomesticShippingFromAmount(listing?.price, domesticShippingFee)
          : toInputAmount(listing?.price),
    oldPrice: isShippingIncludedInPrice
      ? subtractDomesticShippingFromAmount(listing?.old_price, domesticShippingFee)
      : toInputAmount(listing?.old_price),
    minimumOffer: subtractDomesticShippingFromAmount(
      listing?.minimum_offer,
      isShippingIncludedInPrice ? domesticShippingFee : 0,
    ),
    acceptOffers: Boolean(listing?.accept_offers),
    availability: listing?.availability ?? defaults.availability,
    dispatchTime: listing?.dispatch_time ?? defaults.dispatchTime,
    shippingCost: domesticShippingFee,
    shippingMethods: Array.isArray(listing?.shipping_methods)
      ? listing.shipping_methods
      : defaults.shippingMethods,
    packaging: defaults.packaging,
    domesticShippingCarrier:
      domesticShipping?.carrier ??
      listing?.shipping_methods?.[0] ??
      defaults.domesticShippingCarrier,
    domesticShippingMode: isLegacyBoxNow ? 'legacy_boxnow' : 'dhl_manual',
    domesticShippingFee: domesticShippingFee > 0 ? String(domesticShippingFee) : '',
    domesticParcelType,
    packageWeightKg: String(packageDetails.weightKg),
    packageLengthCm: String(packageDetails.lengthCm),
    packageWidthCm: String(packageDetails.widthCm),
    packageHeightCm: String(packageDetails.heightCm),
    shipInternational: Boolean(internationalShipping?.enabled),
    internationalCarrier:
      internationalShipping?.carrier ??
      commonOptions?.internationalShipping?.carrier ??
      'DHL Express',
    internationalRates: Object.fromEntries(
      (commonOptions?.internationalShipping?.zones ?? []).map((zone) => [
        zone.key,
        internationalShipping?.rates?.[zone.key] != null
          ? String(internationalShipping.rates[zone.key])
          : '',
      ]),
    ),
    shippingNotes: listing?.packaging_notes ?? '',
    complianceAcknowledgements: Array.isArray(listing?.compliance_flags)
      ? listing.compliance_flags
      : [],
    saleFormat:
      listing?.sale_format === 'auction'
        ? auctionLabel
        : listing?.sale_format === 'trade'
          ? tradeLabel
          : fixedPriceLabel,
    startingBid: subtractDomesticShippingFromAmount(
      listing?.starting_bid,
      isShippingIncludedInPrice ? domesticShippingFee : 0,
    ),
    reservePrice: subtractDomesticShippingFromAmount(
      listing?.reserve_price,
      isShippingIncludedInPrice ? domesticShippingFee : 0,
    ),
    bidIncrement: toInputAmount(listing?.bid_increment ?? 5),
    auctionEndsAt: toDateTimeLocalValue(listing?.auction_ends_at),
    buyoutPrice: subtractDomesticShippingFromAmount(
      listing?.buyout_price,
      isShippingIncludedInPrice ? domesticShippingFee : 0,
    ),
    quantity: String(
      Number(listing?.available_quantity ?? listing?.quantity ?? 1) || 1,
    ),
    cardBundleMode: isLootLot ? CARD_BUNDLE_MODES.lot : CARD_BUNDLE_MODES.single,
    lotCardCount:
      lotConfiguration?.total_cards != null
        ? String(lotConfiguration.total_cards)
        : lotConfiguration?.totalCards != null
          ? String(lotConfiguration.totalCards)
          : '',
    lotGuaranteedHits:
      lotConfiguration?.guaranteed_hits != null
        ? String(lotConfiguration.guaranteed_hits)
        : lotConfiguration?.guaranteedHits != null
          ? String(lotConfiguration.guaranteedHits)
          : '',
    lotSummary: lotConfiguration?.note ?? '',
    lotCardConditionMix:
      lotConfiguration?.condition_mix ??
      lotConfiguration?.conditionMix ??
      '',
    allowIndividualLotPurchase: Boolean(
      lotConfiguration?.allow_individual_purchase ??
        lotConfiguration?.allowIndividualPurchase ??
        lotConfiguration?.allowsIndividualPurchase,
    ),
    lotIndividualCards: individualCards.map((card, index) => ({
      id: card.id ?? `lot-card-${index + 1}`,
      title: card.title ?? '',
      price: Number(card.price ?? 0),
    })),
    lotNamedCards: namedCards,
    lotThemeTags: lotThemes,
    lotCardDraft: '',
    lotIndividualCardTitleDraft: '',
    lotIndividualCardPriceDraft: '',
    lotThemeDraft: '',
    typeLabel: attributes.type_label ?? product.product_type ?? subcategory,
    gradedCompany:
      attributes.graded_company ?? specifications.graded_company ?? '',
    grade: attributes.grade ?? specifications.grade ?? '',
    setName: attributes.set_name ?? product.set_name ?? '',
    cardNumber: attributes.card_number ?? product.item_number ?? '',
    issueNumber:
      attributes.issue_number ?? specifications.issue_number ?? '',
    printing: attributes.printing ?? specifications.printing ?? '',
    language: product.language ?? attributes.language ?? '',
    characterName:
      attributes.character_name ?? specifications.character_name ?? '',
    manufacturerLine:
      attributes.manufacturer_line ?? specifications.manufacturer_line ?? '',
    scale: attributes.scale ?? specifications.scale ?? '',
    figureHeight: attributes.figure_height ?? specifications.figure_height ?? '',
    material: attributes.material ?? specifications.material ?? '',
    miniatureSubtype:
      attributes.miniature_subtype ?? specifications.miniature_subtype ?? '',
    displayStatus:
      attributes.display_status ?? specifications.display_status ?? '',
    boxCondition:
      attributes.box_condition ?? specifications.box_condition ?? '',
    accessories: attributes.accessories ?? specifications.accessories ?? '',
    edition: attributes.edition ?? specifications.edition ?? '',
    serialReference:
      attributes.serial_reference ?? specifications.serial_reference ?? '',
    bundleContents:
      attributes.bundle_contents ?? specifications.bundle_contents ?? '',
    writer: attributes.writer ?? specifications.writer ?? '',
    artist: attributes.artist ?? specifications.artist ?? '',
    coverArtist:
      attributes.cover_artist ?? specifications.cover_artist ?? '',
    signedBy: attributes.signed_by ?? specifications.signed_by ?? '',
    pageCount: attributes.page_count ?? specifications.page_count ?? '',
    isbn: attributes.isbn ?? specifications.isbn ?? '',
    authenticity: attributes.authenticity ?? product.authenticity_notes ?? '',
  }
}

function CreateListingPage() {
  const [searchParams] = useSearchParams()
  const editListingId = searchParams.get('edit')
  const duplicateListingId = searchParams.get('duplicate')
  const sourceListingId = editListingId ?? duplicateListingId
  const isEditing = Boolean(editListingId)
  const isDuplicating = !isEditing && Boolean(duplicateListingId)
  const { currentUser, isAuthenticated } = useAuth()
  const { locale } = useI18n()
  const { categories, createListing, updateListing, getListingForEdit, marketplaceAccess } = useMarketplace()
  const { listingCategoryTemplates, listingCommonOptions, listingSteps } = useMemo(() => getListingFormData(locale), [locale])

  const t = (el, en) => {
    if (locale === 'en') return en
    const translated = translateCreateListingGreek(en)
    if (translated) {
      const normalized = normalizePotentialMojibake(translated)
      if (!/\?{2,}/.test(normalized)) return normalized
    }
    const fallback = normalizePotentialMojibake(el)
    if (/\?{2,}/.test(fallback) && en) return en
    return fallback
  }
  const auctionLabel = listingCommonOptions.saleFormats[1] ?? t('Auction', 'Auction')
  const fixedPriceLabel = listingCommonOptions.saleFormats[0] ?? t('Fixed price', 'Fixed price')
  const tradeLabel = listingCommonOptions.saleFormats[2] ?? t('Trade', 'Trade')
  const singleCardLabel = t('Single card', 'Single card')
  const lotLabel = t('Card lot / multiple cards', 'Card lot / multiple cards')
  const auctionAvailability = listingCommonOptions.auctionAvailability ?? t('Auction live', 'Auction live')
  const lotThemeSuggestions = ['Holo', 'Vintage', 'Modern', 'Japanese', 'Promo', 'Binder-ready']
  const internationalShipping = listingCommonOptions.internationalShipping ?? {
    carrier: 'DHL Express',
    note: t(
      'For international shipping, DHL is enabled and you set separate costs per region.',
      'For international shipping, DHL is enabled and you set separate costs per region.',
    ),
    zones: [],
  }

  const [step, setStep] = useState(1)
  const [form, setForm] = useState(() => createDefaultForm(listingCategoryTemplates, listingCommonOptions))
  const [stepError, setStepError] = useState('')
  const [createdListing, setCreatedListing] = useState(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [previewImageUrl, setPreviewImageUrl] = useState('')
  const [isLoadingExistingListing, setIsLoadingExistingListing] = useState(false)
  const [editLoadError, setEditLoadError] = useState('')
  const listingFlowTopRef = useRef(null)

  const categoryOptions = useMemo(
    () =>
      Object.entries(listingCategoryTemplates).map(([id, template]) => {
        const liveCategory = categories.find((item) => item.id === id)

        return {
          id,
          name: template.title,
          slug: liveCategory?.slug,
        }
      }),
    [categories, listingCategoryTemplates],
  )
  const activeTemplate = listingCategoryTemplates[form.categoryId]
  const currentCategory =
    categoryOptions.find((item) => item.id === form.categoryId) ??
    categories.find((item) => item.id === form.categoryId)
  const isFigureListing = form.categoryId === 'figures'
  const isComicListing = form.categoryId === 'comics'
  const usesParcelTypeDomesticShipping = form.domesticShippingMode === 'legacy_boxnow'
  const isAuctionFormat = form.saleFormat === auctionLabel
  const isTradeFormat = form.saleFormat === tradeLabel
  const isLootLot = form.categoryId === 'cards' && form.cardBundleMode === CARD_BUNDLE_MODES.lot
  const activeFranchiseGroups = getTemplateFranchiseGroups(activeTemplate, form.subcategory)
  const activeFranchiseOptions = getFranchisesForGroup(activeTemplate, form.franchiseGroup, form.subcategory)
  const hasFranchiseSuboptions = activeFranchiseOptions.length > 0
  const activeFranchiseGroupLabel = getFranchiseGroupLabel(activeTemplate, form.franchiseGroup, form.subcategory)
  const lotPreviewCards = form.lotNamedCards
  const lotThemes = form.lotThemeTags
  const domesticShipping = getDomesticShippingProfileFromOptions(listingCommonOptions, form.categoryId)
  const domesticParcelType = usesParcelTypeDomesticShipping
    ? normalizeParcelType(
        form.domesticParcelType ?? 'small',
        form.domesticParcelType ?? 'small',
      )
    : ''
  const parcelTypeOptions = getParcelTypeOptions(locale)
  const selectedParcelDimensions = usesParcelTypeDomesticShipping ? getParcelDimensionsCm(domesticParcelType) : null
  const domesticShippingFee = resolveDomesticShippingFee({
    categoryId: form.categoryId,
    domesticShippingFee: form.domesticShippingFee,
    domesticParcelType,
  })
  const cyprusShippingFee = usesParcelTypeDomesticShipping
    ? resolveCyprusShippingFee({
        categoryId: form.categoryId,
        domesticParcelType,
      })
    : 0
  const packageDetails = normalizePackageDetails({
    packageWeightKg: form.packageWeightKg,
    packageLengthCm: form.packageLengthCm,
    packageWidthCm: form.packageWidthCm,
    packageHeightCm: form.packageHeightCm,
  })
  const packagingOptions = getPackagingOptionsForCategory(listingCommonOptions, form.categoryId)
  const photoChecklist = getPhotoChecklistForCategory(listingCommonOptions, form.categoryId)
  const saleFormatOptions = form.categoryId === 'cards'
    ? listingCommonOptions.saleFormats
    : listingCommonOptions.saleFormats.filter((item) => item !== tradeLabel)

  useEffect(() => {
    const leadFile = Array.isArray(form.mediaFiles) ? form.mediaFiles[0] : null

    if (typeof File !== 'undefined' && leadFile instanceof File) {
      const objectUrl = URL.createObjectURL(leadFile)
      setPreviewImageUrl(objectUrl)

      return () => {
        URL.revokeObjectURL(objectUrl)
      }
    }

    const existingPreview = Array.isArray(form.uploadedMedia)
      ? form.uploadedMedia.find((item) => item?.url)
      : null

    setPreviewImageUrl(existingPreview?.url ?? '')
    return undefined
  }, [form.mediaFiles, form.uploadedMedia])

  useEffect(() => {
    let isActive = true

    if (!sourceListingId || !isAuthenticated) {
      setEditLoadError('')
      setIsLoadingExistingListing(false)
      return () => {
        isActive = false
      }
    }

    setIsLoadingExistingListing(true)
    setEditLoadError('')

    ;(async () => {
      try {
        const listing = await getListingForEdit(sourceListingId)
        if (!isActive || !listing) return

        setForm(
          buildFormFromExistingListing({
            listing,
            categories,
            templates: listingCategoryTemplates,
            commonOptions: listingCommonOptions,
            auctionLabel,
            fixedPriceLabel,
            tradeLabel,
            includeExistingMedia: !isDuplicating,
          }),
        )
        setCreatedListing(null)
        setStep(1)
        setStepError('')
      } catch (error) {
        if (!isActive) return
        setEditLoadError(
          error?.message ||
            (locale === 'en'
              ? 'We could not load this listing for editing.'
              : 'We could not load this listing for editing.'),
        )
      } finally {
        if (isActive) {
          setIsLoadingExistingListing(false)
        }
      }
    })()

    return () => {
      isActive = false
    }
  }, [sourceListingId, isAuthenticated, isEditing, isDuplicating, locale])

  useEffect(() => {
    const frame = window.requestAnimationFrame(() => {
      scrollListingFlowToTop(step === 1 ? 'auto' : 'smooth')
    })

    return () => window.cancelAnimationFrame(frame)
  }, [step])

  const updateForm = (field, value) => setForm((previous) => ({ ...previous, [field]: value }))

  const appendMediaFiles = (files) => {
    const nextFiles = Array.isArray(files) ? files.filter(Boolean) : []
    if (!nextFiles.length) return

    setForm((previous) => ({
      ...previous,
      mediaFiles: [...(Array.isArray(previous.mediaFiles) ? previous.mediaFiles : []), ...nextFiles],
    }))
  }

  const removeUploadedMedia = (indexToRemove) => {
    setForm((previous) => ({
      ...previous,
      uploadedMedia: Array.isArray(previous.uploadedMedia)
        ? previous.uploadedMedia.filter((_, index) => index != indexToRemove)
        : [],
    }))
  }

  const removePendingMedia = (indexToRemove) => {
    setForm((previous) => ({
      ...previous,
      mediaFiles: Array.isArray(previous.mediaFiles)
        ? previous.mediaFiles.filter((_, index) => index != indexToRemove)
        : [],
    }))
  }

  const toggleArrayValue = (field, value) => {
    setForm((previous) => ({
      ...previous,
      [field]: previous[field].includes(value)
        ? previous[field].filter((item) => item !== value)
        : [...previous[field], value],
    }))
  }

  const handleCategoryChange = (categoryId) => {
    const nextDefaults = getTemplateDefaults(listingCategoryTemplates, categoryId)
    const nextDomesticShipping = getDomesticShippingProfileFromOptions(listingCommonOptions, categoryId)
    const nextPackageDefaults = nextDomesticShipping.packageDefaults ?? DEFAULT_DOMESTIC_SHIPPING.packageDefaults
    const nextPackagingOptions = getPackagingOptionsForCategory(listingCommonOptions, categoryId)
    setForm((previous) => ({
      ...previous,
      ...nextDefaults,
      saleFormat:
        categoryId === 'cards'
          ? previous.saleFormat
          : previous.saleFormat === tradeLabel
            ? fixedPriceLabel
            : previous.saleFormat,
      cardBundleMode: categoryId === 'cards' ? previous.cardBundleMode : CARD_BUNDLE_MODES.single,
      lotCardCount: categoryId === 'cards' ? previous.lotCardCount : '',
      lotGuaranteedHits: categoryId === 'cards' ? previous.lotGuaranteedHits : '',
      lotSummary: categoryId === 'cards' ? previous.lotSummary : '',
      lotCardConditionMix: categoryId === 'cards' ? previous.lotCardConditionMix : '',
      allowIndividualLotPurchase: categoryId === 'cards' ? previous.allowIndividualLotPurchase : false,
      lotIndividualCards: categoryId === 'cards' ? previous.lotIndividualCards : [],
      lotNamedCards: categoryId === 'cards' ? previous.lotNamedCards : [],
      lotThemeTags: categoryId === 'cards' ? previous.lotThemeTags : [],
      lotCardDraft: categoryId === 'cards' ? previous.lotCardDraft : '',
      lotIndividualCardTitleDraft: categoryId === 'cards' ? previous.lotIndividualCardTitleDraft : '',
      lotIndividualCardPriceDraft: categoryId === 'cards' ? previous.lotIndividualCardPriceDraft : '',
      lotThemeDraft: categoryId === 'cards' ? previous.lotThemeDraft : '',
      shippingCost: 0,
      shippingMethods: [
        nextDomesticShipping.carrier ?? DEFAULT_DOMESTIC_SHIPPING.carrier,
        ...(previous.shipInternational ? [previous.internationalCarrier || internationalShipping.carrier || 'DHL Express'] : []),
      ].filter((value, index, items) => value && items.indexOf(value) === index),
      packaging: nextPackagingOptions[0] ?? previous.packaging ?? '',
      domesticShippingCarrier: nextDomesticShipping.carrier ?? DEFAULT_DOMESTIC_SHIPPING.carrier,
      domesticShippingMode: 'dhl_manual',
      domesticShippingFee: '',
      domesticParcelType: '',
      packageWeightKg: String(nextPackageDefaults?.weightKg ?? 0.5),
      packageLengthCm: String(nextPackageDefaults?.lengthCm ?? 20),
      packageWidthCm: String(nextPackageDefaults?.widthCm ?? 15),
      packageHeightCm: String(nextPackageDefaults?.heightCm ?? 8),
      internationalCarrier: previous.internationalCarrier || internationalShipping.carrier || 'DHL Express',
      internationalRates: Object.fromEntries((internationalShipping.zones ?? []).map((zone) => [zone.key, previous.internationalRates?.[zone.key] ?? ''])),
    }))
  }

  const handleSubcategoryChange = (value) => {
    const nextGroups = getTemplateFranchiseGroups(activeTemplate, value)
    const nextFranchiseGroup = nextGroups[0]?.value ?? ''
    const nextFranchises = getFranchisesForGroup(activeTemplate, nextFranchiseGroup, value)

    setForm((previous) => ({
      ...previous,
      subcategory: value,
      typeLabel: value,
      franchiseGroup: nextFranchiseGroup,
      franchise: nextFranchises[0] ?? getDefaultFranchiseValueForGroup(activeTemplate, nextFranchiseGroup, value) ?? '',
    }))
  }

  const handleFranchiseGroupChange = (value) => {
    const nextOptions = getFranchisesForGroup(activeTemplate, value, form.subcategory)
    const nextRarityOptions = getRarityOptionsForGroup(activeTemplate, value)
    setForm((previous) => ({
      ...previous,
      franchiseGroup: value,
      franchise: nextOptions[0] ?? getDefaultFranchiseValueForGroup(activeTemplate, value, form.subcategory) ?? '',
      rarity: nextRarityOptions.includes(previous.rarity) ? previous.rarity : nextRarityOptions[0] ?? previous.rarity,
    }))
  }

  const getAttributeFieldOptions = (field) => {
    if (field.type !== 'select') return field.options ?? []
    if (field.name === 'rarity' && form.categoryId === 'cards') {
      const rarityOptions = getRarityOptionsForGroup(activeTemplate, form.franchiseGroup)
      return rarityOptions.length ? rarityOptions : field.options
    }
    return field.options
  }

  const updateInternationalRate = (zoneKey, value) => {
    setForm((previous) => ({
      ...previous,
      internationalRates: {
        ...(previous.internationalRates ?? {}),
        [zoneKey]: value,
      },
    }))
  }

  const setInternationalShippingEnabled = (enabled) => {
    setForm((previous) => ({
      ...previous,
      shipInternational: enabled,
      shippingMethods: [
        previous.domesticShippingCarrier || domesticShipping.carrier || 'DHL Express',
        ...(enabled ? [previous.internationalCarrier || internationalShipping.carrier || 'DHL Express'] : []),
      ].filter((value, index, items) => value && items.indexOf(value) === index),
      internationalRates: Object.fromEntries(
        (internationalShipping.zones ?? []).map((zone) => [
          zone.key,
          previous.internationalRates?.[zone.key] ?? '',
        ]),
      ),
    }))
  }

  const handleSaleFormatChange = (value) => {
    const isAuction = value === auctionLabel
    const isTrade = value === tradeLabel

    setForm((previous) => ({
      ...previous,
      saleFormat: value,
      quantity: isAuction || isTrade ? 1 : previous.quantity || 1,
      acceptOffers: isAuction || isTrade ? false : previous.acceptOffers,
      minimumOffer: isAuction || isTrade ? '' : previous.minimumOffer,
      availability: isAuction
        ? auctionAvailability
        : previous.availability === auctionAvailability
          ? listingCommonOptions.availability[0] ?? ''
          : previous.availability,
    }))
  }

  const setBundleMode = (mode) => {
    setForm((previous) => ({
      ...previous,
      cardBundleMode: mode,
      lotCardCount: mode === CARD_BUNDLE_MODES.lot ? previous.lotCardCount || (previous.lotNamedCards.length ? String(previous.lotNamedCards.length) : '') : previous.lotCardCount,
    }))
  }

  const addLotCard = () => {
    setForm((previous) => {
      const nextCards = appendUniqueListItem(previous.lotNamedCards, previous.lotCardDraft)
      if (nextCards === previous.lotNamedCards) return previous
      return {
        ...previous,
        lotNamedCards: nextCards,
        lotCardDraft: '',
        lotCardCount: String(Math.max(Number(previous.lotCardCount || 0), nextCards.length)),
      }
    })
  }

  const addLotTheme = (value = form.lotThemeDraft) => {
    setForm((previous) => {
      const nextThemes = appendUniqueListItem(previous.lotThemeTags, value)
      if (nextThemes === previous.lotThemeTags) return previous
      return {
        ...previous,
        lotThemeTags: nextThemes,
        lotThemeDraft: String(value || '').trim() === previous.lotThemeDraft.trim() ? '' : previous.lotThemeDraft,
      }
    })
  }

  const addLotIndividualCard = () => {
    const title = String(form.lotIndividualCardTitleDraft || '').trim()
    const price = roundMoney(parseAmountInput(form.lotIndividualCardPriceDraft))

    if (!title || price <= 0) {
      return
    }

    setForm((previous) => {
      const nextCards = [
        ...previous.lotIndividualCards,
        {
          id: `lot-card-${previous.lotIndividualCards.length + 1}`,
          title,
          price,
        },
      ]

      return {
        ...previous,
        lotIndividualCards: nextCards,
        lotIndividualCardTitleDraft: '',
        lotIndividualCardPriceDraft: '',
        lotCardCount: String(Math.max(Number(previous.lotCardCount || 0), nextCards.length)),
      }
    })
  }

  const existingMediaCount = Array.isArray(form.uploadedMedia) ? form.uploadedMedia.length : 0
  const totalMediaCount = existingMediaCount + form.mediaFiles.length
  const baseListingPrice = parseAmountInput(isAuctionFormat ? form.startingBid : form.price)
  const effectivePreviewPrice = usesParcelTypeDomesticShipping
    ? roundMoney(baseListingPrice + domesticShippingFee)
    : roundMoney(baseListingPrice)

  const preview = {
    title: form.title || (isLootLot ? t('New card lot listing', 'New card lot listing') : isAuctionFormat ? t('New auction listing', 'New auction listing') : isTradeFormat ? t('New trade listing', 'New trade listing') : t('New listing', 'New listing')),
    subtitle: form.subtitle || (isLootLot ? t('Grouped cards with a clean summary and clear highlights.', 'Grouped cards with a clean summary and clear highlights.') : isAuctionFormat ? t('Timed sale with bidding and optional reserve or buyout.', 'Timed sale with bidding and optional reserve or buyout.') : isTradeFormat ? t('Card available for trade with declared value and protected dual release.', 'Card available for trade with declared value and protected dual release.') : form.franchise),
    franchise: form.franchise,
    condition: form.condition,
    price: effectivePreviewPrice,
    quantity: isAuctionFormat || isTradeFormat ? 1 : Number(form.quantity || 1),
    availability: isAuctionFormat ? auctionAvailability : form.availability,
    typeLabel: isLootLot ? lotLabel : form.typeLabel || form.subcategory || activeTemplate.title,
    rarity: form.rarity,
    acceptOffers: !isAuctionFormat && !isTradeFormat && form.acceptOffers,
    category: { name: currentCategory?.name ?? activeTemplate.title },
    categoryName: currentCategory?.name ?? activeTemplate.title,
    media: previewImageUrl ? [{ url: previewImageUrl, alt: form.title || form.typeLabel || activeTemplate.title }] : [],
    saleFormat: isAuctionFormat ? 'auction' : isTradeFormat ? 'trade' : 'fixed_price',
    auction: isAuctionFormat ? { startingBid: parseAmountInput(form.startingBid), currentBid: parseAmountInput(form.startingBid), reservePrice: parseAmountInput(form.reservePrice), bidIncrement: parseAmountInput(form.bidIncrement), bidCount: 0, watchers: 0, buyoutPrice: form.buyoutPrice ? parseAmountInput(form.buyoutPrice) : null, endsAt: form.auctionEndsAt } : null,
    lot: isLootLot ? { totalCards: Number(form.lotCardCount || 0), guaranteedHits: Number(form.lotGuaranteedHits || 0), previewCards: lotPreviewCards.slice(0, 5), themes: lotThemes, note: form.lotSummary, overflowCount: Math.max(Number(form.lotCardCount || 0) - lotPreviewCards.slice(0, 5).length, 0), allowsIndividualPurchase: Boolean(form.allowIndividualLotPurchase), individualCards: form.lotIndividualCards.slice(0, 8) } : null,
    visual: { gradient: categoryVisuals[form.categoryId] ?? categoryVisuals.cards, label: isAuctionFormat ? t('Auction', 'Auction') : isTradeFormat ? t('Trade', 'Trade') : isLootLot ? lotLabel : t('New listing', 'New listing') },
  }

  const scrollListingFlowToTop = (behavior = 'smooth') => {
    if (listingFlowTopRef.current?.scrollIntoView) {
      listingFlowTopRef.current.scrollIntoView({ behavior, block: 'start' })
      return
    }

    if (typeof window !== 'undefined') {
      window.scrollTo({ top: 0, behavior })
    }
  }

  const goToStep = (nextStep) => {
    setStep(Math.max(1, Math.min(nextStep, listingSteps.length)))
    setStepError('')
  }

  const validateCurrentStep = () => {
    if (step === 1 && (!form.categoryId || !form.subcategory)) return setStepError(t('Choose a category and a subcategory before continuing.', 'Choose a category and a subcategory before continuing.')), false
    if (step === 2 && (!form.title.trim() || !form.description.trim() || !form.franchise || !form.brand || !form.condition)) return setStepError(t('Add a title, description, franchise, brand and condition.', 'Add a title, description, franchise, brand and condition.')), false
    if (step === 3 && !totalMediaCount) return setStepError(t('Upload at least one photo before moving on.', 'Upload at least one photo before moving on.')), false
    if (step === 4) {
      const missingField = activeTemplate.attributeFields.find((field) => field.required && !String(form[field.name] ?? '').trim())
      if (missingField) {
        const missingFieldMessage = locale === 'en'
          ? `Please complete "${missingField.label}".`
          : `Please complete "${missingField.label}".`
        return setStepError(normalizePotentialMojibake(missingFieldMessage)), false
      }
      if (isLootLot && (!Number(form.lotCardCount) || Number(form.lotCardCount) < 2)) return setStepError(t('A card lot should include at least 2 cards.', 'A card lot should include at least 2 cards.')), false
      if (isLootLot && !form.lotNamedCards.length) return setStepError(t('Add at least one card so buyers can immediately understand what is inside the lot.', 'Add at least one card so buyers can immediately understand what is inside the lot.')), false
      if (isLootLot && Number(form.lotCardCount || 0) < form.lotNamedCards.length) return setStepError(t('Total cards must be equal to or higher than the named cards you added.', 'Total cards must be equal to or higher than the named cards you added.')), false
      if (isLootLot && form.allowIndividualLotPurchase && !form.lotIndividualCards.length) return setStepError(t('Add at least one individually purchasable card when separate checkout is enabled.', 'Add at least one individually purchasable card when separate checkout is enabled.')), false
      if (isLootLot && form.allowIndividualLotPurchase && Number(form.lotCardCount || 0) < form.lotIndividualCards.length) return setStepError(t('Total cards must be equal to or higher than the individual cards you listed for separate purchase.', 'Total cards must be equal to or higher than the individual cards you listed for separate purchase.')), false
      if (isLootLot && !form.lotSummary.trim()) return setStepError(t('Add a short summary that explains the lot clearly.', 'Add a short summary that explains the lot clearly.')), false
    }
    if (step === 5) {
      const startingBidValue = parseAmountInput(form.startingBid)
      const bidIncrementValue = parseAmountInput(form.bidIncrement)
      const reservePriceValue = parseAmountInput(form.reservePrice)
      const buyoutPriceValue = parseAmountInput(form.buyoutPrice)
      const itemPriceValue = parseAmountInput(form.price)
      const minimumOfferValue = parseAmountInput(form.minimumOffer)

      if (isAuctionFormat) {
        if (!startingBidValue || startingBidValue <= 0) return setStepError(t('Set a valid opening bid.', 'Set a valid opening bid.')), false
        if (!bidIncrementValue || bidIncrementValue <= 0) return setStepError(t('Set a valid bid increment.', 'Set a valid bid increment.')), false
        if (!form.auctionEndsAt) return setStepError(t('Choose when the auction ends.', 'Choose when the auction ends.')), false
        if (form.reservePrice && reservePriceValue < startingBidValue) return setStepError(t('The reserve price must be equal to or higher than the opening bid.', 'The reserve price must be equal to or higher than the opening bid.')), false
        if (form.buyoutPrice && buyoutPriceValue <= startingBidValue) return setStepError(t('The buyout price must be higher than the opening bid.', 'The buyout price must be higher than the opening bid.')), false
      } else if (isTradeFormat) {
        if (form.categoryId !== 'cards') {
          return setStepError(t('Trades are available only for card listings.', 'Trades are available only for card listings.')), false
        }
        if (!itemPriceValue || itemPriceValue <= 0) {
          return setStepError(t('Set a valid declared trade value.', 'Set a valid declared trade value.')), false
        }
      } else {
        if (!itemPriceValue || itemPriceValue <= 0 || !Number(form.quantity) || Number(form.quantity) < 1) return setStepError(t('Set a valid price and available quantity.', 'Set a valid price and available quantity.')), false
        if (form.acceptOffers && form.minimumOffer && minimumOfferValue >= itemPriceValue) return setStepError(t('The minimum offer should stay lower than the sale price.', 'The minimum offer should stay lower than the sale price.')), false
      }
    }
    if (step === 6) {
      if (usesParcelTypeDomesticShipping) {
        const normalizedParcelType = normalizeParcelType(
          form.domesticParcelType,
          'small',
        )

        if (!BOXNOW_PARCEL_TYPES.includes(normalizedParcelType)) {
          return setStepError(
            t(
              'Επίλεξε έγκυρο τύπο δέματος ώστε να υπολογιστούν σωστά τα μεταφορικά.',
              'Select a valid parcel type so shipping can be calculated correctly.',
            ),
          ), false
        }
      } else {
        if (!domesticShippingFee || domesticShippingFee <= 0) {
          return setStepError(
            t(
              'Όρισε έγκυρη DHL χρέωση για την εγχώρια αποστολή.',
              'Set a valid DHL fee for domestic shipping.',
            ),
          ), false
        }

        if (
          packageDetails.weightKg <= 0 ||
          packageDetails.lengthCm <= 0 ||
          packageDetails.widthCm <= 0 ||
          packageDetails.heightCm <= 0
        ) {
          return setStepError(
            t(
              'Συμπλήρωσε σωστά βάρος και διαστάσεις δέματος για το DHL label.',
              'Enter valid parcel weight and dimensions for the DHL label.',
            ),
          ), false
        }
      }

      if (!form.dispatchTime || !form.packaging) {
        return setStepError(
          t(
            'Complete dispatch time and packaging details.',
            'Complete dispatch time and packaging details.',
          ),
        ), false
      }

      if (form.shipInternational) {
        const missingZone = (internationalShipping.zones ?? []).find(
          (zone) => !Number(form.internationalRates?.[zone.key] || 0),
        )
        if (missingZone) {
          return setStepError(
            locale === 'en'
              ? `Set a DHL shipping amount for ${missingZone.label}.`
              : `Set a DHL shipping amount for ${missingZone.label}.`,
          ), false
        }
      }
    }
    if (step === 7 && form.complianceAcknowledgements.length !== listingCommonOptions.complianceChecks.length) return setStepError(t('Accept all listing confirmations before submitting.', 'Accept all listing confirmations before submitting.')), false
    setStepError('')
    return true
  }

  const submitListing = async (publishAction) => {
    if (!validateCurrentStep()) return
    setIsSubmitting(true)
    try {
      const localizedCategoryName = currentCategory?.name ?? activeTemplate.title
      const submissionPayload = {
        ...form,
        publishAction,
        saleFormat: isAuctionFormat ? 'auction' : isTradeFormat ? 'trade' : 'fixed_price',
        cardBundleMode: form.cardBundleMode,
        tags: splitCommaList(form.tags),
        lotThemeTags: form.lotThemeTags,
        lotThemes: form.lotThemeTags,
        lotNamedCards: form.lotNamedCards,
        lotHighlights: form.lotNamedCards.join(', '),
        categoryName: localizedCategoryName,
      }
      const listing = isEditing
        ? await updateListing(editListingId, submissionPayload)
        : await createListing(submissionPayload)

      setCreatedListing({
        ...listing,
        categoryName: localizedCategoryName,
        mode: isEditing ? 'updated' : 'created',
      })
    } catch (error) {
      setStepError(error.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  const backToListingsLabel = t('Πίσω στις αγγελίες μου', 'Back to my listings')
  const sourceListingModeLabel =
    locale === 'en'
      ? isEditing
        ? 'Edit listing'
        : 'Create similar listing'
      : isEditing
        ? 'Επεξεργασία αγγελίας'
        : 'Δημιουργία παρόμοιας'
  const sourceListingLoadingTitle =
    locale === 'en'
      ? isEditing
        ? 'Loading your listing'
        : 'Preparing your similar listing'
      : isEditing
        ? 'Φορτώνουμε την αγγελία σου'
        : 'Ετοιμάζουμε παρόμοια αγγελία'
  const sourceListingLoadingDescription =
    locale === 'en'
      ? isEditing
        ? 'In a moment all listing details will be ready for editing.'
        : 'We are bringing in the details of the selected listing so you can create a new version faster.'
      : isEditing
        ? 'Σε λίγο θα εμφανιστούν όλα τα στοιχεία της αγγελίας για επεξεργασία.'
        : 'Φέρνουμε τα στοιχεία της αγγελίας που διάλεξες ώστε να στήσεις πιο γρήγορα τη νέα καταχώριση.'
  const sourceListingErrorTitle =
    locale === 'en'
      ? isEditing
        ? 'We could not load the listing'
        : 'We could not load the source listing'
      : isEditing
        ? 'Δεν μπορέσαμε να φορτώσουμε την αγγελία'
        : 'Δεν μπορέσαμε να φορτώσουμε την αγγελία-πηγή'
  const createPageEyebrow =
    locale === 'en'
      ? isEditing
        ? 'Edit listing'
        : isDuplicating
          ? 'Create similar'
          : 'New listing'
      : isEditing
        ? 'Επεξεργασία αγγελίας'
        : isDuplicating
          ? 'Δημιουργία παρόμοιας'
          : 'Νέα αγγελία'
  const createPageTitle =
    locale === 'en'
      ? isEditing
        ? 'Update your listing'
        : isDuplicating
          ? 'Create a new listing from an existing one'
          : 'Create a listing buyers can trust'
      : isEditing
        ? 'Ενημέρωσε την αγγελία σου'
        : isDuplicating
          ? 'Φτιάξε νέα αγγελία πάνω σε υπάρχουσα'
          : 'Φτιάξε μια αγγελία που εμπνέει εμπιστοσύνη'
  const createPageDescription =
    locale === 'en'
      ? isEditing
        ? 'Adjust any details you need and save the updated version of your listing.'
        : isDuplicating
          ? 'We prefill the form from the selected listing so you can change what you want and publish a fresh listing faster.'
          : 'Fill in the details that matter, upload clear photos and send the listing for review before it goes live.'
      : isEditing
        ? 'Άλλαξε ό,τι χρειάζεσαι και αποθήκευσε την ενημερωμένη εκδοχή της αγγελίας σου.'
        : isDuplicating
          ? 'Προσυμπληρώνουμε τη φόρμα από την αγγελία που διάλεξες ώστε να αλλάξεις ό,τι θέλεις και να ανεβάσεις γρήγορα μια νέα.'
          : 'Συμπλήρωσε όσα χρειάζεται ο επόμενος αγοραστής, ανέβασε καθαρές φωτογραφίες και στείλε την αγγελία για έλεγχο πριν βγει δημόσια.'

  if (!isAuthenticated) {
    return (
      <div className="container pb-16">
        <CardSurface className="mx-auto max-w-3xl text-center">
          <Badge tone="gold">{t('Seller access', 'Seller access')}</Badge>
          <h1 className="mt-4 font-display text-4xl text-white">{t('Sign in to create a listing', 'Sign in to create a listing')}</h1>
          <p className="mt-4 text-sm leading-7 text-mist">{t('Listing tools are available only to signed-in collectors so every listing stays tied to a real account.', 'Listing tools are available only to signed-in collectors so every listing stays tied to a real account.')}</p>
          <div className="mt-6 flex justify-center gap-3">
            <Button as={Link} to="/eisodos">{t('Login', 'Login')}</Button>
            <Button as={Link} to="/eggrafi" variant="secondary">{t('Register', 'Register')}</Button>
          </div>
        </CardSurface>
      </div>
    )
  }

  if (sourceListingId && isLoadingExistingListing) {
    return (
      <div className="container pb-16">
        <CardSurface className="mx-auto max-w-3xl text-center">
          <Badge tone="gold">{sourceListingModeLabel}</Badge>
          <h1 className="mt-4 font-display text-4xl text-white">{sourceListingLoadingTitle}</h1>
          <p className="mt-4 text-sm leading-7 text-mist">{sourceListingLoadingDescription}</p>
        </CardSurface>
      </div>
    )
  }

  if (sourceListingId && editLoadError) {
    return (
      <div className="container pb-16">
        <CardSurface className="mx-auto max-w-3xl text-center">
          <Badge tone="warning">{sourceListingModeLabel}</Badge>
          <h1 className="mt-4 font-display text-4xl text-white">{sourceListingErrorTitle}</h1>
          <p className="mt-4 text-sm leading-7 text-mist">{editLoadError}</p>
          <div className="mt-6 flex justify-center gap-3">
            <Button as={Link} to="/oi-aggelies-mou">
              {t('Δες τις αγγελίες μου', 'View my listings')}
            </Button>
            <Button as={Link} to="/dashboard-politi" variant="secondary">
              {t('Άνοιγμα seller dashboard', 'Open seller dashboard')}
            </Button>
          </div>
        </CardSurface>
      </div>
    )
  }

  if (createdListing) {
    return (
      <div className="container pb-16">
        <CardSurface className="mx-auto max-w-4xl">
          <Badge tone="success">{createdListing.mode === 'updated' ? t('Changes saved', 'Changes saved') : t('Listing saved', 'Listing saved')}</Badge>
          <h1 className="mt-4 font-display text-5xl text-white">{t('Your item has been submitted', 'Your item has been submitted')}</h1>
          <div className="mt-8 grid gap-4 md:grid-cols-4">
            <div className="rounded-[20px] border border-white/8 bg-white/5 p-4"><p className="text-[11px] uppercase tracking-[0.28em] text-white/45">{t('Listing ID', 'Listing ID')}</p><p className="mt-2 text-lg font-semibold text-white">{createdListing.id}</p></div>
            <div className="rounded-[20px] border border-white/8 bg-white/5 p-4"><p className="text-[11px] uppercase tracking-[0.28em] text-white/45">{t('Sale format', 'Sale format')}</p><p className="mt-2 text-lg font-semibold text-white">{createdListing.saleFormat === 'auction' ? t('Auction', 'Auction') : createdListing.saleFormat === 'trade' ? t('Trade', 'Trade') : t('Fixed price', 'Fixed price')}</p></div>
            <div className="rounded-[20px] border border-white/8 bg-white/5 p-4"><p className="text-[11px] uppercase tracking-[0.28em] text-white/45">{createdListing.saleFormat === 'auction' ? t('Starting bid', 'Starting bid') : createdListing.saleFormat === 'trade' ? t('Declared trade value', 'Declared trade value') : t('Price', 'Price')}</p><p className="mt-2 text-lg font-semibold text-white">{formatCurrency(createdListing.price)}</p></div>
            <div className="rounded-[20px] border border-white/8 bg-white/5 p-4"><p className="text-[11px] uppercase tracking-[0.28em] text-white/45">{t('Category', 'Category')}</p><p className="mt-2 text-lg font-semibold text-white">{createdListing.categoryName}</p></div>
          </div>
          <div className="mt-8 flex flex-wrap gap-3">
            <Button as={Link} to="/oi-aggelies-mou">{t('View my listings', 'View my listings')}</Button>
            <Button as={Link} to="/dashboard-politi" variant="secondary">{t('Open seller dashboard', 'Open seller dashboard')}</Button>
            <Button variant="ghost" onClick={() => { setCreatedListing(null); setForm(createDefaultForm(listingCategoryTemplates, listingCommonOptions)); setStep(1) }}>{t('Create another listing', 'Create another listing')}</Button>
          </div>
        </CardSurface>
      </div>
    )
  }

  if (marketplaceAccess && !marketplaceAccess.can_create_listing) {
    return (
      <div className="container pb-16">
        <CardSurface className="mx-auto max-w-4xl">
          <Badge tone="warning">{t('marketplace access', 'Marketplace access')}</Badge>
          <h1 className="mt-4 font-display text-4xl text-white">
            {t(
              'Πριν δημιουργήσεις αγγελία, ολοκλήρωσε το verification και το Stripe setup',
              'Before you create a listing, complete verification and Stripe setup',
            )}
          </h1>
          <p className="mt-4 text-sm leading-7 text-mist">
            {marketplaceAccess.blocking_message}
          </p>
          <div className="mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            {(marketplaceAccess.requirements ?? []).map((requirement) => (
              <div key={requirement.key} className="rounded-[20px] border border-white/8 bg-white/5 p-4">
                <p className="text-[11px] uppercase tracking-[0.28em] text-white/45">{requirement.label}</p>
                <p className="mt-2 text-sm font-semibold text-white">{requirement.status_label}</p>
              </div>
            ))}
          </div>
          <div className="mt-6 flex flex-wrap gap-3">
            <Button as={Link} to="/epalithefsi-logariasmou">
              {t('Open verification center', 'Open verification center')}
            </Button>
            <Button as={Link} to="/dashboard-politi" variant="secondary">
              {t('Open seller dashboard / Stripe setup', 'Open seller dashboard / Stripe setup')}
            </Button>
            <Button as={Link} to="/profil" variant="ghost">
              {t('Back to profile', 'Back to profile')}
            </Button>
          </div>
        </CardSurface>
      </div>
    )
  }

  const reviewRows = [
    [t('Category', 'Category'), currentCategory?.name ?? activeTemplate.title],
    [t('Subcategory', 'Subcategory'), form.subcategory],
    [t('Sale format', 'Sale format'), isAuctionFormat ? t('Auction', 'Auction') : isTradeFormat ? t('Trade', 'Trade') : t('Fixed price', 'Fixed price')],
    [
      isAuctionFormat
        ? t('Starting bid', 'Starting bid')
        : isTradeFormat
          ? t('Δηλωμένη αξία trade (με μεταφορικά τύπου δέματος)', 'Declared trade value (with parcel shipping)')
        : usesParcelTypeDomesticShipping
          ? t('Τελική τιμή (με μεταφορικά τύπου δέματος)', 'Final price (with parcel shipping)')
          : t('Τιμή προϊόντος', 'Item price'),
      formatCurrency(effectivePreviewPrice),
    ],
    ...(activeFranchiseGroups.length
      ? [[activeTemplate.franchiseGroupLabel ?? t('Franchise group', 'Franchise group'), activeFranchiseGroupLabel]]
      : []),
    [activeTemplate.franchiseLabel ?? t('Franchise / series', 'Franchise / series'), form.franchise],
    [activeTemplate.brandLabel ?? 'Brand', form.brand],
    [t('Condition', 'Condition'), form.condition],
    ...(isComicListing
      ? [
          [t('Issue / volume', 'Issue / volume'), form.issueNumber],
          [t('Printing / edition', 'Printing / edition'), form.printing],
        ]
      : []),
    [
      t('Shipping', 'Shipping'),
      usesParcelTypeDomesticShipping
        ? form.shipInternational
          ? t(
              `Ελλάδα/Κύπρος με BoxNow ανά τύπο δέματος + DHL διεθνώς (${(internationalShipping.zones ?? []).length} ζώνες)`,
              `Greece/Cyprus with BoxNow parcel type + DHL international (${(internationalShipping.zones ?? []).length} zones)`,
            )
          : t('Ελλάδα/Κύπρος με BoxNow ανά τύπο δέματος', 'Greece/Cyprus with BoxNow parcel type')
        : form.shipInternational
          ? t(
              `BoxNow domestic + DHL international (${(internationalShipping.zones ?? []).length} zones)`,
              `BoxNow domestic + DHL international (${(internationalShipping.zones ?? []).length} zones)`,
            )
          : form.shipInternational
            ? t(
                `DHL Express domestic + DHL international (${(internationalShipping.zones ?? []).length} zones)`,
                `DHL Express domestic + DHL international (${(internationalShipping.zones ?? []).length} zones)`,
              )
            : t('Greece only with DHL Express', 'Greece only with DHL Express'),
    ],
    [t('Photos', 'Photos'), `${form.mediaFiles.length} ${t('files ready to upload', 'files ready to upload')}`],
  ]
  if (usesParcelTypeDomesticShipping) {
    reviewRows.push([
      t('Τύπος δέματος Ελλάδας/Κύπρου', 'Greece/Cyprus parcel type'),
      `${getParcelTypeLabel(locale, domesticParcelType)} (${selectedParcelDimensions.length}x${selectedParcelDimensions.width}x${selectedParcelDimensions.height}cm)`,
    ])
    reviewRows.push([
      t('Χρέωση ανά χώρα', 'Country fees'),
      `GR ${formatCurrency(domesticShippingFee)} • CY ${
        cyprusShippingFee > 0
          ? formatCurrency(cyprusShippingFee)
          : t('DHL fallback', 'DHL fallback')
      }`,
    ])
  } else {
    reviewRows.push([
      t('DHL domestic fee', 'DHL domestic fee'),
      formatCurrency(domesticShippingFee),
    ])
    reviewRows.push([
      t('Package specs', 'Package specs'),
      `${packageDetails.weightKg}kg • ${packageDetails.lengthCm}x${packageDetails.widthCm}x${packageDetails.heightCm}cm`,
    ])
  }
  if (isLootLot) reviewRows.push([t('Lot summary', 'Lot summary'), `${Number(form.lotCardCount || 0)} / ${Number(form.lotGuaranteedHits || 0)}`])

  return (
    <div className="container pb-16">
      <div ref={listingFlowTopRef} />
      {sourceListingId ? (
        <div className="mb-5 flex">
          <Button as={Link} to="/oi-aggelies-mou" variant="secondary" size="sm">
            {backToListingsLabel}
          </Button>
        </div>
      ) : null}
      <SectionHeader eyebrow={createPageEyebrow} title={createPageTitle} description={createPageDescription} />

      <div className="grid gap-6 xl:grid-cols-[1.12fr,0.88fr]">
        <div className="space-y-6 self-start">
          <CardSurface className="overflow-hidden">
            <div className="grid gap-3 md:grid-cols-7">
              {listingSteps.map((label, index) => {
                const itemStep = index + 1
                return (
                  <button key={label} type="button" onClick={() => goToStep(itemStep)} className={cn('rounded-[18px] border px-3 py-3 text-left transition', itemStep === step ? 'border-gold-300/30 bg-gold-300/12 text-gold-100' : itemStep < step ? 'border-emerald-400/20 bg-emerald-400/10 text-emerald-100' : 'border-white/8 bg-white/5 text-white/70 hover:border-white/12 hover:text-white')}>
                    <p className="text-[10px] uppercase tracking-[0.28em]">{String(itemStep).padStart(2, '0')}</p>
                    <p className="mt-2 text-xs font-semibold leading-5">{label}</p>
                  </button>
                )
              })}
            </div>
          </CardSurface>

          <CardSurface>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div><Badge tone="gold">{listingSteps[step - 1]}</Badge><h2 className="mt-3 font-display text-4xl text-white">{activeTemplate.title}</h2></div>
              <p className="max-w-xl text-sm leading-7 text-mist">{activeTemplate.heroNote}</p>
            </div>

            <div className="mt-6 space-y-6">
              {step === 1 ? (
                <div className="grid gap-5 md:grid-cols-2">
                  <div><label className="mb-2 block text-sm text-mist">{t('Category', 'Category')}</label><Select value={form.categoryId} onChange={(event) => handleCategoryChange(event.target.value)}>{categoryOptions.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</Select></div>
                  <div><label className="mb-2 block text-sm text-mist">{t('Subcategory', 'Subcategory')}</label><Select value={form.subcategory} onChange={(event) => handleSubcategoryChange(event.target.value)}>{activeTemplate.subcategories.map((item) => <option key={item} value={item}>{item}</option>)}</Select></div>
                  {form.categoryId === 'cards' ? (
                    <div className="rounded-[22px] border border-gold-300/15 bg-gold-300/10 p-4 md:col-span-2">
                      <div className="grid gap-3 md:grid-cols-2">
                        {[{ value: CARD_BUNDLE_MODES.single, label: singleCardLabel, text: t('Best for one card where grading, set, serial and condition should stand out.', 'Best for one card where grading, set, serial and condition should stand out.') }, { value: CARD_BUNDLE_MODES.lot, label: lotLabel, text: t('Best for grouped listings with many cards and a cleaner, easier-to-scan presentation.', 'Best for grouped listings with many cards and a cleaner, easier-to-scan presentation.') }].map((item) => (
                          <button key={item.value} type="button" onClick={() => setBundleMode(item.value)} className={cn('rounded-[18px] border px-4 py-4 text-left transition', form.cardBundleMode === item.value ? 'border-gold-300/35 bg-gold-300/16 text-gold-50 shadow-[0_12px_32px_rgba(242,203,112,0.08)]' : 'border-white/8 bg-white/5 text-white/78 hover:border-white/12')}>
                            <div className="flex items-center justify-between gap-3"><p className="text-sm font-semibold text-white">{item.label}</p>{form.cardBundleMode === item.value ? <Badge tone="gold">{t('Active', 'Active')}</Badge> : null}</div>
                            <p className="mt-2 text-xs leading-6 text-white/65">{item.text}</p>
                          </button>
                        ))}
                      </div>
                      {isLootLot ? <div className="mt-4 rounded-[18px] border border-emerald-400/18 bg-emerald-400/10 px-4 py-3 text-sm leading-7 text-emerald-50">{t('Lot mode is active. In the next step you will add the total card count, the key cards you want to show, and the short summary buyers will actually read.', 'Lot mode is active. In the next step you will add the total card count, the key cards you want to show, and the short summary buyers will actually read.')}</div> : null}
                    </div>
                  ) : null}
                </div>
              ) : null}

              {step === 2 ? (
                <div className="grid gap-5 md:grid-cols-2">
                  <div className="md:col-span-2">
                    <label className="mb-2 block text-sm text-mist">{t('Listing title', 'Listing title')}</label>
                    <Input
                      value={form.title}
                      onChange={(event) => updateForm('title', event.target.value)}
                        placeholder={
                          isLootLot
                            ? t('For example: Pokemon lot with 54 cards, holo mix and guaranteed hits', 'For example: Pokemon lot with 54 cards, holo mix and guaranteed hits')
                            : isFigureListing
                              ? t('For example: Hot Toys Darth Vader 1/6 Deluxe with full box', 'For example: Hot Toys Darth Vader 1/6 Deluxe with full box')
                              : form.categoryId === 'misc'
                                ? t('For example: Pink Floyd limited vinyl / collectible 2â‚¬ proof coin / signed tour poster', 'For example: Pink Floyd limited vinyl / collectible 2â‚¬ proof coin / signed tour poster')
                              : t('For example: Charizard ex Special Illustration Rare PSA 10', 'For example: Charizard ex Special Illustration Rare PSA 10')
                        }
                      />
                  </div>

                  <div className="md:col-span-2">
                    <label className="mb-2 block text-sm text-mist">{t('Short subtitle', 'Short subtitle')}</label>
                    <Input
                      value={form.subtitle}
                      onChange={(event) => updateForm('subtitle', event.target.value)}
                      placeholder={t('Short context for the buyer', 'Short context for the buyer')}
                    />
                  </div>

                  {activeFranchiseGroups.length ? (
                    <>
                      <div>
                        <label className="mb-2 block text-sm text-mist">{activeTemplate.franchiseGroupLabel ?? t('Franchise group', 'Franchise group')}</label>
                        <Select value={form.franchiseGroup} onChange={(event) => handleFranchiseGroupChange(event.target.value)}>
                          {activeFranchiseGroups.map((group) => (
                            <option key={group.value} value={group.value}>
                              {group.label}
                            </option>
                          ))}
                        </Select>
                      </div>

                      {hasFranchiseSuboptions ? (
                        <div>
                          <label className="mb-2 block text-sm text-mist">{t('Franchise / series', 'Franchise / series')}</label>
                          <Select value={form.franchise} onChange={(event) => updateForm('franchise', event.target.value)}>
                            {activeFranchiseOptions.map((item) => (
                              <option key={item} value={item}>
                                {item}
                              </option>
                            ))}
                          </Select>
                        </div>
                      ) : (
                        <div className="rounded-[20px] border border-white/8 bg-white/5 px-4 py-3 text-sm leading-7 text-white/72">
                          {t(
                            'No franchise subcategory is required for the Other group.',
                            'No franchise subcategory is required for the Other group.',
                          )}
                        </div>
                      )}

                      <div className="rounded-[20px] border border-white/8 bg-white/5 px-4 py-3 text-sm leading-7 text-white/72 md:col-span-2">
                        {t(
                          'Choose the main franchise group first and then you will only see the relevant options.',
                          'Choose the main franchise group first and then you will only see the relevant options.',
                        )}
                      </div>
                    </>
                  ) : (
                    <div>
                      <label className="mb-2 block text-sm text-mist">{activeTemplate.franchiseLabel ?? t('Franchise / series', 'Franchise / series')}</label>
                      <Select value={form.franchise} onChange={(event) => updateForm('franchise', event.target.value)}>
                        {activeTemplate.franchises.map((item) => (
                          <option key={item} value={item}>
                            {item}
                          </option>
                        ))}
                      </Select>
                    </div>
                  )}

                  <div>
                    <label className="mb-2 block text-sm text-mist">{activeTemplate.brandLabel ?? t('Brand / company', 'Brand / company')}</label>
                    <Select value={form.brand} onChange={(event) => updateForm('brand', event.target.value)}>
                      {activeTemplate.brands.map((item) => (
                        <option key={item} value={item}>
                          {item}
                        </option>
                      ))}
                    </Select>
                  </div>

                  <div>
                    <label className="mb-2 block text-sm text-mist">{t('Condition', 'Condition')}</label>
                    <Select value={form.condition} onChange={(event) => updateForm('condition', event.target.value)}>
                      {activeTemplate.conditions.map((item) => (
                        <option key={item} value={item}>
                          {item}
                        </option>
                      ))}
                    </Select>
                  </div>

                  <div>
                    <label className="mb-2 block text-sm text-mist">{activeTemplate.yearLabel ?? t('Year / release', 'Year / release')}</label>
                    <Input value={form.year} onChange={(event) => updateForm('year', event.target.value)} placeholder={t('e.g. 2024', 'e.g. 2024')} />
                  </div>

                  <div className="md:col-span-2">
                    <label className="mb-2 block text-sm text-mist">Tags</label>
                    <Input
                      value={form.tags}
                      onChange={(event) => updateForm('tags', event.target.value)}
                      placeholder={activeTemplate.tagsPlaceholder ?? t('e.g. alt art, PSA 10, sealed, modern', 'e.g. alt art, PSA 10, sealed, modern')}
                    />
                  </div>

                  <div className="md:col-span-2">
                    <label className="mb-2 block text-sm text-mist">{t('Description', 'Description')}</label>
                    <Textarea
                      value={form.description}
                      onChange={(event) => updateForm('description', event.target.value)}
                      placeholder={
                        isLootLot
                          ? t(
                              'Explain the mix, the overall condition, the origin of the lot and what the buyer should realistically expect.',
                              'Explain the mix, the overall condition, the origin of the lot and what the buyer should realistically expect.',
                            )
                          : activeTemplate.descriptionPlaceholder ?? t(
                              'Describe the condition, any flaws, the origin of the item and what the buyer should know before purchasing.',
                              'Describe the condition, any flaws, the origin of the item and what the buyer should know before purchasing.',
                            )
                      }
                    />
                  </div>
                </div>
              ) : null}

              {step === 3 ? (
                <div className="grid gap-5 md:grid-cols-[0.95fr,1.05fr]">
                  <div className="rounded-[20px] border border-white/8 bg-white/5 p-4">
                    <label className="mb-2 block text-sm text-mist">{t('Upload photos', 'Upload photos')}</label>
                    <input
                      type="file"
                      multiple
                      accept="image/*"
                      onChange={(event) => {
                        appendMediaFiles(Array.from(event.target.files ?? []))
                        event.target.value = ''
                      }}
                      className="block w-full text-[13px] text-mist file:mr-3 file:rounded-lg file:border-0 file:bg-gold-300/15 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-gold-100 hover:file:bg-gold-300/20"
                    />
                    <p className="mt-3 text-xs leading-6 text-mist">
                      {t('Photos will be attached when you save or submit the listing.', 'Photos will be attached when you save or submit the listing.')}
                    </p>
                    {totalMediaCount ? (
                      <div className="mt-4 flex flex-wrap gap-2">
                        <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] text-white/75">
                          {t('Selected photos', 'Selected photos')}: {totalMediaCount}
                        </span>
                      </div>
                    ) : null}

                    {existingMediaCount ? (
                      <div className="mt-4">
                        <p className="mb-2 text-xs uppercase tracking-[0.24em] text-white/45">
                          {t('Current photos', 'Current photos')}
                        </p>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                          {form.uploadedMedia.map((item, index) => (
                            <div
                              key={`${item.path ?? item.url ?? 'uploaded'}-${index}`}
                              className="relative overflow-hidden rounded-[18px] border border-white/10 bg-[#08111d]"
                            >
                              {item.url ? (
                                <img
                                  src={item.url}
                                  alt={item.label ?? `Listing media ${index + 1}`}
                                  className="h-28 w-full object-cover"
                                />
                              ) : (
                                <div className="flex h-28 items-center justify-center px-3 text-center text-xs text-mist">
                                  {item.label ?? item.original_name ?? `Image ${index + 1}`}
                                </div>
                              )}
                              <button
                                type="button"
                                onClick={() => removeUploadedMedia(index)}
                                className="absolute right-2 top-2 inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/15 bg-black/60 text-white transition hover:border-rose-300/30 hover:bg-rose-500/20 hover:text-rose-100"
                                aria-label={t('Remove photo', 'Remove photo')}
                              >
                                <X className="h-4 w-4" />
                              </button>
                            </div>
                          ))}
                        </div>
                      </div>
                    ) : null}

                    {form.mediaFiles.length ? (
                      <div className="mt-4">
                        <p className="mb-2 text-xs uppercase tracking-[0.24em] text-white/45">
                          {t('New photos ready to upload', 'New photos ready to upload')}
                        </p>
                        <div className="space-y-2">
                          {form.mediaFiles.map((file, index) => (
                            <div
                              key={`${file.name}-${file.lastModified}-${index}`}
                              className="flex items-center justify-between gap-3 rounded-[16px] border border-white/10 bg-white/5 px-3 py-2"
                            >
                              <div className="min-w-0">
                                <p className="truncate text-sm text-white">{file.name}</p>
                                <p className="text-xs text-mist">{(file.size / 1024 / 1024).toFixed(2)} MB</p>
                              </div>
                              <button
                                type="button"
                                onClick={() => removePendingMedia(index)}
                                className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-white/15 bg-black/50 text-white transition hover:border-rose-300/30 hover:bg-rose-500/20 hover:text-rose-100"
                                aria-label={t('Remove photo', 'Remove photo')}
                              >
                                <X className="h-4 w-4" />
                              </button>
                            </div>
                          ))}
                        </div>
                      </div>
                    ) : null}
                  </div>

                  <div>
                    <label className="mb-2 block text-sm text-mist">{t('Photo notes', 'Photo notes')}</label>
                    <Textarea
                      value={form.mediaNotes}
                      onChange={(event) => updateForm('mediaNotes', event.target.value)}
                      placeholder={t('e.g. close-ups on corners, print lines, serial, seals or box wear.', 'e.g. close-ups on corners, print lines, serial, seals or box wear.')}
                    />
                  </div>

                  <div className="grid gap-3 md:col-span-2 md:grid-cols-2">
                    {photoChecklist.map((item) => (
                      <div key={item} className="rounded-[20px] border border-white/8 bg-white/5 px-4 py-3 text-sm leading-7 text-white/78">
                        {item}
                      </div>
                    ))}
                  </div>
                </div>
              ) : null}

              {step === 4 ? (
                <div className="space-y-6">
                  <div className="grid gap-5 md:grid-cols-2">
                    {activeTemplate.attributeFields
                      .filter((field) => !(field.name === 'miniatureSubtype' && form.typeLabel !== 'Miniatures'))
                      .map((field) => (
                        <div key={field.name} className={field.type === 'textarea' ? 'md:col-span-2' : ''}>
                          <label className="mb-2 block text-sm text-mist">
                            {field.label}
                            {field.required ? <span className="ml-1 text-gold-100">*</span> : null}
                          </label>
                          {field.type === 'select' ? (
                            <Select value={form[field.name]} onChange={(event) => updateForm(field.name, event.target.value)}>
                              {getAttributeFieldOptions(field).map((option) => (
                                <option key={option} value={option}>
                                  {option}
                                </option>
                              ))}
                            </Select>
                          ) : field.type === 'textarea' ? (
                            <Textarea value={form[field.name]} onChange={(event) => updateForm(field.name, event.target.value)} placeholder={field.placeholder} />
                          ) : (
                            <Input value={form[field.name]} onChange={(event) => updateForm(field.name, event.target.value)} placeholder={field.placeholder} />
                          )}
                        </div>
                      ))}
                  </div>

                  {isLootLot ? (
                    <div className="rounded-[24px] border border-gold-300/18 bg-gold-300/10 p-5">
                      <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                          <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{t('Χτίσιμο lot', 'Lot Builder')}</p>
                          <h3 className="mt-3 font-display text-3xl text-white">{t('Details buyers will read first', 'Details buyers will read first')}</h3>
                          <p className="mt-2 max-w-3xl text-sm leading-7 text-gold-50">{t('You do not need to write all 100 cards one by one. Add the overall count, then list the cards you want buyers to notice first.', 'You do not need to write all 100 cards one by one. Add the overall count, then list the cards you want buyers to notice first.')}</p>
                        </div>
                        <Badge tone="gold">{t('Lot mode active', 'Lot mode active')}</Badge>
                      </div>

                      <div className="mt-5 grid gap-5 md:grid-cols-3">
                        <div>
                          <label className="mb-2 block text-sm text-gold-50">{t('Total cards', 'Total cards')}</label>
                          <Input type="number" min="2" value={form.lotCardCount} onChange={(event) => updateForm('lotCardCount', event.target.value)} />
                        </div>
                        <div>
                          <label className="mb-2 block text-sm text-gold-50">{t('Εγγυημένα hits', 'Guaranteed hits')}</label>
                          <Input type="number" min="0" value={form.lotGuaranteedHits} onChange={(event) => updateForm('lotGuaranteedHits', event.target.value)} />
                        </div>
                        <div>
                          <label className="mb-2 block text-sm text-gold-50">{t('Μίξη κατάστασης', 'Condition mix')}</label>
                          <Input value={form.lotCardConditionMix} onChange={(event) => updateForm('lotCardConditionMix', event.target.value)} placeholder={t('e.g. 70% Near Mint / 30% Excellent', 'e.g. 70% Near Mint / 30% Excellent')} />
                        </div>
                      </div>

                      <div className="mt-5 grid gap-5 lg:grid-cols-[1.08fr,0.92fr]">
                        <div className="rounded-[20px] border border-white/10 bg-black/10 p-4">
                          <p className="text-[11px] uppercase tracking-[0.28em] text-white/50">{t('Cards shown first', 'Cards shown first')}</p>
                          <div className="mt-4 flex gap-3">
                            <Input
                              value={form.lotCardDraft}
                              onChange={(event) => updateForm('lotCardDraft', event.target.value)}
                              onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                  event.preventDefault()
                                  addLotCard()
                                }
                              }}
                              placeholder={t('Add a card you want shown in the preview', 'Add a card you want shown in the preview')}
                            />
                            <Button type="button" onClick={addLotCard}>{t('Add', 'Add')}</Button>
                          </div>
                          <div className="mt-4 flex flex-wrap gap-2">
                            {lotPreviewCards.length ? lotPreviewCards.map((item) => (
                              <button
                                key={item}
                                type="button"
                                onClick={() => setForm((previous) => ({ ...previous, lotNamedCards: previous.lotNamedCards.filter((card) => card !== item) }))}
                                className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-[11px] text-white/78 transition hover:border-rose-300/30 hover:text-rose-100"
                              >
                                {item} <span className="ml-1 text-white/40">×</span>
                              </button>
                            )) : <p className="text-sm text-mist">{t('No cards added yet.', 'No cards added yet.')}</p>}
                          </div>
                        </div>

                        <div className="rounded-[20px] border border-white/10 bg-black/10 p-4">
                          <p className="text-[11px] uppercase tracking-[0.28em] text-white/50">{t('Themes', 'Themes')}</p>
                          <div className="mt-4 flex gap-3">
                            <Input
                              value={form.lotThemeDraft}
                              onChange={(event) => updateForm('lotThemeDraft', event.target.value)}
                              onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                  event.preventDefault()
                                  addLotTheme()
                                }
                              }}
                              placeholder={t('Add a theme', 'Add a theme')}
                            />
                            <Button type="button" variant="secondary" onClick={() => addLotTheme()}>{t('Add', 'Add')}</Button>
                          </div>
                          <div className="mt-3 flex flex-wrap gap-2">
                            {lotThemeSuggestions.map((item) => (
                              <button key={item} type="button" onClick={() => addLotTheme(item)} className="rounded-full border border-gold-300/18 bg-gold-300/10 px-3 py-1 text-[11px] text-gold-50 transition hover:border-gold-300/28">
                                + {item}
                              </button>
                            ))}
                          </div>
                          <div className="mt-4 flex flex-wrap gap-2">
                            {lotThemes.length ? lotThemes.map((item) => (
                              <button
                                key={item}
                                type="button"
                                onClick={() => setForm((previous) => ({ ...previous, lotThemeTags: previous.lotThemeTags.filter((theme) => theme !== item) }))}
                                className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-[11px] text-white/78 transition hover:border-rose-300/30 hover:text-rose-100"
                              >
                                {item} <span className="ml-1 text-white/40">×</span>
                              </button>
                            )) : <p className="text-sm text-mist">{t('No themes added yet.', 'No themes added yet.')}</p>}
                          </div>
                        </div>
                      </div>

                      <div className="mt-5">
                        <label className="mb-2 block text-sm text-gold-50">{t('Short lot summary', 'Short lot summary')}</label>
                        <Textarea value={form.lotSummary} onChange={(event) => updateForm('lotSummary', event.target.value)} placeholder={t('Explain what the lot is, what kind of mix it has, and what the buyer should realistically expect.', 'Explain what the lot is, what kind of mix it has, and what the buyer should realistically expect.')} />
                      </div>

                      <div className="mt-5 rounded-[20px] border border-white/10 bg-black/10 p-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                          <div>
                            <p className="text-[11px] uppercase tracking-[0.28em] text-white/50">{t('Individual cards for checkout', 'Individual cards for checkout')}</p>
                            <h4 className="mt-3 text-xl font-semibold text-white">{t('Allow buyers to purchase single cards from the lot', 'Allow buyers to purchase single cards from the lot')}</h4>
                            <p className="mt-2 max-w-3xl text-sm leading-7 text-mist">{t('If enabled, buyers can choose specific cards from the lot and buy them separately.', 'If enabled, buyers can choose specific cards from the lot and buy them separately.')}</p>
                          </div>
                          <label className="inline-flex items-center gap-3 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-white">
                            <input
                              type="checkbox"
                              checked={form.allowIndividualLotPurchase}
                              onChange={(event) => updateForm('allowIndividualLotPurchase', event.target.checked)}
                              className="h-4 w-4 rounded border-white/20 bg-transparent text-gold-300 focus:ring-gold-300/30"
                            />
                            <span>{t('Enabled', 'Enabled')}</span>
                          </label>
                        </div>

                        {form.allowIndividualLotPurchase ? (
                          <>
                            <div className="mt-4 grid gap-3 md:grid-cols-[1.2fr,0.55fr,auto]">
                              <Input
                                value={form.lotIndividualCardTitleDraft}
                                onChange={(event) => updateForm('lotIndividualCardTitleDraft', event.target.value)}
                                onKeyDown={(event) => {
                                  if (event.key === 'Enter') {
                                    event.preventDefault()
                                    addLotIndividualCard()
                                  }
                                }}
                                placeholder={t('Card title for individual purchase', 'Card title for individual purchase')}
                              />
                              <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.lotIndividualCardPriceDraft}
                                onChange={(event) => updateForm('lotIndividualCardPriceDraft', event.target.value)}
                                placeholder={t('Τιμή', 'Price')}
                              />
                              <Button type="button" onClick={addLotIndividualCard}>{t('Add card', 'Add card')}</Button>
                            </div>

                            <div className="mt-4 rounded-xl border border-gold-300/15 bg-gold-300/10 px-4 py-3 text-sm leading-7 text-gold-50">
                              {t('Shipping and insurance for individual card purchases start at â‚¬2.50 for up to 10 cards. From the 11th card onward, â‚¬0.25 is added for each extra card.', 'Shipping and insurance for individual card purchases start at â‚¬2.50 for up to 10 cards. From the 11th card onward, â‚¬0.25 is added for each extra card.')}
                            </div>

                            <div className="mt-4 space-y-2">
                              {form.lotIndividualCards.length ? form.lotIndividualCards.map((card) => (
                                <div key={card.id} className="flex flex-wrap items-center justify-between gap-3 rounded-[16px] border border-white/10 bg-white/5 px-4 py-3">
                                  <div>
                                    <p className="text-sm font-semibold text-white">{card.title}</p>
                                    <p className="text-xs text-mist">{formatCurrency(card.price)}</p>
                                  </div>
                                  <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    onClick={() => setForm((previous) => ({ ...previous, lotIndividualCards: previous.lotIndividualCards.filter((item) => item.id !== card.id) }))}
                                  >
                                    {t('Remove', 'Remove')}
                                  </Button>
                                </div>
                              )) : (
                                <p className="text-sm text-mist">{t('No individual cards added yet.', 'No individual cards added yet.')}</p>
                              )}
                            </div>
                          </>
                        ) : null}
                      </div>
                    </div>
                  ) : null}
                </div>
              ) : null}

              {step === 5 ? (
                <div className="space-y-6">
                  <div className="grid gap-5 md:grid-cols-[0.95fr,1.05fr]">
                    <div>
                      <label className="mb-2 block text-sm text-mist">{t('Sale format', 'Sale format')}</label>
                      <Select value={form.saleFormat} onChange={(event) => handleSaleFormatChange(event.target.value)}>
                        {saleFormatOptions.map((item) => (
                          <option key={item} value={item}>
                            {item}
                          </option>
                        ))}
                      </Select>
                    </div>
                    <div className="rounded-[20px] border border-white/8 bg-white/5 px-4 py-3.5 text-sm leading-7 text-white/78">
                      {isAuctionFormat
                        ? t(
                            'Auction listings show the opening bid, bid step, end time and any optional reserve or buyout.',
                            'Auction listings show the opening bid, bid step, end time and any optional reserve or buyout.',
                          )
                        : isTradeFormat
                          ? t(
                              'Trade listings are card-only. Buyers send trade requests, both sides fund the same Stripe deposit and settlement runs only after dual release.',
                              'Trade listings are card-only. Buyers send trade requests, both sides fund the same Stripe deposit and settlement runs only after dual release.',
                            )
                        : t(
                            'Fixed-price listings can include quantity, an older reference price and whether offers are welcome.',
                            'Fixed-price listings can include quantity, an older reference price and whether offers are welcome.',
                          )}
                    </div>
                  </div>
                  {isAuctionFormat ? (
                    <div className="grid gap-5 md:grid-cols-2">
                      <div>
                        <label className="mb-2 block text-sm text-mist">{t('Starting bid', 'Starting bid')}</label>
                        <Input
                          type="number"
                          min="0"
                          value={form.startingBid}
                          onChange={(event) => updateForm('startingBid', event.target.value)}
                        />
                      </div>
                      <div>
                        <label className="mb-2 block text-sm text-mist">{t('Τιμή reserve', 'Reserve')}</label>
                        <Input
                          type="number"
                          min="0"
                          value={form.reservePrice}
                          onChange={(event) => updateForm('reservePrice', event.target.value)}
                        />
                      </div>
                      <div>
                        <label className="mb-2 block text-sm text-mist">{t('Bid increment', 'Bid increment')}</label>
                        <Input
                          type="number"
                          min="1"
                          value={form.bidIncrement}
                          onChange={(event) => updateForm('bidIncrement', event.target.value)}
                        />
                      </div>
                      <div>
                        <label className="mb-2 block text-sm text-mist">{t('Άμεση αγορά', 'Buyout')}</label>
                        <Input
                          type="number"
                          min="0"
                          value={form.buyoutPrice}
                          onChange={(event) => updateForm('buyoutPrice', event.target.value)}
                        />
                      </div>
                      <div>
                        <label className="mb-2 block text-sm text-mist">{t('Auction end', 'Auction end')}</label>
                        <Input
                          type="datetime-local"
                          value={form.auctionEndsAt}
                          onChange={(event) => updateForm('auctionEndsAt', event.target.value)}
                        />
                      </div>
                      <div>
                        <label className="mb-2 block text-sm text-mist">{t('Availability', 'Availability')}</label>
                        <Input value={auctionAvailability} disabled />
                      </div>
                    </div>
                  ) : isTradeFormat ? (
                    <div className="grid gap-5 md:grid-cols-2">
                      <div>
                        <label className="mb-2 block text-sm text-mist">
                          {t('Declared trade value (before domestic shipping)', 'Declared trade value (before domestic shipping)')}
                        </label>
                        <Input
                          type="number"
                          min="0"
                          value={form.price}
                          onChange={(event) => updateForm('price', event.target.value)}
                        />
                      </div>
                      <div>
                        <label className="mb-2 block text-sm text-mist">{t('Availability', 'Availability')}</label>
                        <Select
                          value={form.availability}
                          onChange={(event) => updateForm('availability', event.target.value)}
                        >
                          {listingCommonOptions.availability.map((item) => (
                            <option key={item} value={item}>
                              {item}
                            </option>
                          ))}
                        </Select>
                      </div>
                      <div className="rounded-[20px] border border-gold-300/15 bg-gold-300/10 px-4 py-3 text-sm leading-7 text-gold-50 md:col-span-2">
                        {t(
                          'Trade terms snapshot: both participants deposit the same amount (based on the higher declared value), dual release is mandatory, no auto-release is allowed and Cardora resolves disputes manually.',
                          'Trade terms snapshot: both participants deposit the same amount (based on the higher declared value), dual release is mandatory, no auto-release is allowed and Cardora resolves disputes manually.',
                        )}
                      </div>
                    </div>
                  ) : (
                    <div className="grid gap-5 md:grid-cols-2">
                      <div>
                        <label className="mb-2 block text-sm text-mist">
                          {t('Item price (before domestic shipping)', 'Item price (before domestic shipping)')}
                        </label>
                        <Input
                          type="number"
                          min="0"
                          value={form.price}
                          onChange={(event) => updateForm('price', event.target.value)}
                        />
                      </div>
                      <div>
                        <label className="mb-2 block text-sm text-mist">
                          {t('Previous price / reference', 'Previous price / reference')}
                        </label>
                        <Input
                          type="number"
                          min="0"
                          value={form.oldPrice}
                          onChange={(event) => updateForm('oldPrice', event.target.value)}
                        />
                      </div>
                      <div>
                        <label className="mb-2 block text-sm text-mist">
                          {t('Available quantity', 'Available quantity')}
                        </label>
                        <Input
                          type="number"
                          min="1"
                          value={form.quantity}
                          onChange={(event) => updateForm('quantity', event.target.value)}
                        />
                      </div>
                      <div>
                        <label className="mb-2 block text-sm text-mist">{t('Availability', 'Availability')}</label>
                        <Select
                          value={form.availability}
                          onChange={(event) => updateForm('availability', event.target.value)}
                        >
                          {listingCommonOptions.availability.map((item) => (
                            <option key={item} value={item}>
                              {item}
                            </option>
                          ))}
                        </Select>
                      </div>
                      <div className="rounded-[20px] border border-white/8 bg-white/5 p-4 md:col-span-2">
                        <label className="flex items-start gap-3 text-sm text-white/80">
                          <input
                            type="checkbox"
                            checked={form.acceptOffers}
                            onChange={(event) => updateForm('acceptOffers', event.target.checked)}
                            className="mt-1 h-4 w-4 rounded border-white/20 bg-transparent text-gold-300 focus:ring-gold-300/30"
                          />
                          <span>{t('I am open to offers from buyers', 'I am open to offers from buyers')}</span>
                        </label>
                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                          <div>
                            <label className="mb-2 block text-sm text-mist">{t('Minimum offer', 'Minimum offer')}</label>
                            <Input
                              type="number"
                              min="0"
                              value={form.minimumOffer}
                              onChange={(event) => updateForm('minimumOffer', event.target.value)}
                              disabled={!form.acceptOffers}
                            />
                          </div>
                          <div className="rounded-xl border border-gold-300/15 bg-gold-300/10 px-4 py-3 text-sm leading-7 text-gold-50">
                            {t(
                              'If you accept offers, set a realistic floor so messages stay useful and serious.',
                              'If you accept offers, set a realistic floor so messages stay useful and serious.',
                            )}
                          </div>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              ) : null}

              {step === 6 ? (
                <div className="space-y-5">
                  <div className="rounded-[20px] border border-emerald-400/20 bg-emerald-400/10 p-4">
                    <p className="text-[11px] uppercase tracking-[0.28em] text-emerald-100">{t('Domestic', 'Domestic')}</p>
                    <p className="mt-2 text-sm leading-7 text-emerald-50">{domesticShipping.note}</p>
                    {usesParcelTypeDomesticShipping ? (
                      <>
                        <div className="mt-4 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                          <div>
                            <label className="mb-2 block text-sm text-emerald-50">{t('Carrier', 'Carrier')}</label>
                            <Input value={form.domesticShippingCarrier} disabled />
                          </div>
                          <div>
                            <label className="mb-2 block text-sm text-emerald-50">{t('Τύπος δέματος', 'Parcel type')}</label>
                            <Select
                              value={domesticParcelType}
                              onChange={(event) =>
                                updateForm(
                                  'domesticParcelType',
                                  normalizeParcelType(
                                    event.target.value,
                                    'small',
                                  ),
                                )
                              }
                            >
                              {parcelTypeOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                  {option.label}
                                </option>
                              ))}
                            </Select>
                          </div>
                          <div>
                            <label className="mb-2 block text-sm text-emerald-50">{t('Διαστάσεις BoxNow (cm)', 'BoxNow dimensions (cm)')}</label>
                            <Input value={`${selectedParcelDimensions.length} x ${selectedParcelDimensions.width} x ${selectedParcelDimensions.height}`} disabled />
                          </div>
                          <div>
                            <label className="mb-2 block text-sm text-emerald-50">{t('Τελική τιμή (προϊόν + Ελλάδα)', 'Final price (item + Greece)')}</label>
                            <Input value={formatCurrency(effectivePreviewPrice)} disabled />
                          </div>
                        </div>

                        <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                          <div className="rounded-[18px] border border-white/10 bg-black/10 px-4 py-3">
                            <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{t('Item price', 'Item price')}</p>
                            <p className="mt-2 text-base font-semibold text-white">{formatCurrency(baseListingPrice)}</p>
                          </div>
                          <div className="rounded-[18px] border border-white/10 bg-black/10 px-4 py-3">
                            <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{t('Χρέωση Ελλάδας', 'Greece fee')}</p>
                            <p className="mt-2 text-base font-semibold text-white">{formatCurrency(domesticShippingFee)}</p>
                          </div>
                          <div className="rounded-[18px] border border-white/10 bg-black/10 px-4 py-3">
                            <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{t('Χρέωση Κύπρου', 'Cyprus fee')}</p>
                            <p className="mt-2 text-base font-semibold text-white">
                              {cyprusShippingFee > 0
                                ? formatCurrency(cyprusShippingFee)
                                : t('DHL fallback', 'DHL fallback')}
                            </p>
                          </div>
                          <div className="rounded-[18px] border border-gold-300/20 bg-gold-300/10 px-4 py-3">
                            <p className="text-[11px] uppercase tracking-[0.24em] text-gold-50">{t('Final price', 'Final price')}</p>
                            <p className="mt-2 text-base font-semibold text-white">{formatCurrency(effectivePreviewPrice)}</p>
                          </div>
                        </div>

                        <p className="mt-3 text-xs leading-6 text-emerald-100/80">
                          {t(
                            'Ενδεικτικές διαστάσεις BoxNow: Mini 17x45x8, Μικρό 17x45x20, Μεσαίο 36x45x20, Μεγάλο 60x45x36 (cm).',
                            'Indicative BoxNow parcel dimensions: Mini 17x45x8, Small 17x45x20, Medium 36x45x20, Large 60x45x36 (cm).',
                          )}
                        </p>
                        {cyprusShippingFee <= 0 ? (
                          <p className="mt-2 text-xs leading-6 text-emerald-100/70">
                            {t(
                              'Για τον επιλεγμένο τύπο δέματος δεν υπάρχει σταθερή τιμή Κύπρου και εφαρμόζεται fallback μέσω DHL.',
                              'For this parcel type there is no fixed Cyprus rate, so DHL fallback applies.',
                            )}
                          </p>
                        ) : null}
                      </>
                    ) : (
                      <>
                        <div className="mt-4 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                          <div>
                            <label className="mb-2 block text-sm text-emerald-50">{t('Carrier', 'Carrier')}</label>
                            <Input value={form.domesticShippingCarrier} disabled />
                          </div>
                          <div>
                            <label className="mb-2 block text-sm text-emerald-50">{t('DHL domestic fee', 'DHL domestic fee')}</label>
                            <Input
                              type="number"
                              min="0"
                              step="0.01"
                              value={form.domesticShippingFee}
                              onChange={(event) => updateForm('domesticShippingFee', event.target.value)}
                              placeholder={t('e.g. 4.50', 'e.g. 4.50')}
                            />
                          </div>
                          <div>
                            <label className="mb-2 block text-sm text-emerald-50">{t('Item price', 'Item price')}</label>
                            <Input value={formatCurrency(baseListingPrice)} disabled />
                          </div>
                          <div>
                            <label className="mb-2 block text-sm text-emerald-50">{t('Buyer pays at checkout', 'Buyer pays at checkout')}</label>
                            <Input value={formatCurrency(roundMoney(baseListingPrice + domesticShippingFee))} disabled />
                          </div>
                        </div>

                        <div className="mt-4 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                          <div>
                            <label className="mb-2 block text-sm text-emerald-50">{t('Weight (kg)', 'Weight (kg)')}</label>
                            <Input
                              type="number"
                              min="0"
                              step="0.01"
                              value={form.packageWeightKg}
                              onChange={(event) => updateForm('packageWeightKg', event.target.value)}
                            />
                          </div>
                          <div>
                            <label className="mb-2 block text-sm text-emerald-50">{t('Length (cm)', 'Length (cm)')}</label>
                            <Input
                              type="number"
                              min="0"
                              step="0.1"
                              value={form.packageLengthCm}
                              onChange={(event) => updateForm('packageLengthCm', event.target.value)}
                            />
                          </div>
                          <div>
                            <label className="mb-2 block text-sm text-emerald-50">{t('Width (cm)', 'Width (cm)')}</label>
                            <Input
                              type="number"
                              min="0"
                              step="0.1"
                              value={form.packageWidthCm}
                              onChange={(event) => updateForm('packageWidthCm', event.target.value)}
                            />
                          </div>
                          <div>
                            <label className="mb-2 block text-sm text-emerald-50">{t('Height (cm)', 'Height (cm)')}</label>
                            <Input
                              type="number"
                              min="0"
                              step="0.1"
                              value={form.packageHeightCm}
                              onChange={(event) => updateForm('packageHeightCm', event.target.value)}
                            />
                          </div>
                        </div>

                        <div className="mt-4 grid gap-4 md:grid-cols-3">
                          <div className="rounded-[18px] border border-white/10 bg-black/10 px-4 py-3">
                            <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{t('DHL fee', 'DHL fee')}</p>
                            <p className="mt-2 text-base font-semibold text-white">{formatCurrency(domesticShippingFee)}</p>
                          </div>
                          <div className="rounded-[18px] border border-white/10 bg-black/10 px-4 py-3">
                            <p className="text-[11px] uppercase tracking-[0.24em] text-white/45">{t('Package specs', 'Package specs')}</p>
                            <p className="mt-2 text-base font-semibold text-white">
                              {packageDetails.weightKg}kg • {packageDetails.lengthCm}x{packageDetails.widthCm}x{packageDetails.heightCm}cm
                            </p>
                          </div>
                          <div className="rounded-[18px] border border-gold-300/20 bg-gold-300/10 px-4 py-3">
                            <p className="text-[11px] uppercase tracking-[0.24em] text-gold-50">{t('Checkout total preview', 'Checkout total preview')}</p>
                            <p className="mt-2 text-base font-semibold text-white">
                              {formatCurrency(roundMoney(baseListingPrice + domesticShippingFee))}
                            </p>
                          </div>
                        </div>
                      </>
                    )}
                  </div>

                  <div className="rounded-[20px] border border-white/8 bg-white/5 p-4">
                    <label className="flex items-start gap-3 text-sm text-white/80">
                      <input
                        type="checkbox"
                        checked={form.shipInternational}
                        onChange={(event) => setInternationalShippingEnabled(event.target.checked)}
                        className="mt-1 h-4 w-4 rounded border-white/20 bg-transparent text-gold-300 focus:ring-gold-300/30"
                      />
                      <span>{t('I also ship internationally', 'I also ship internationally')}</span>
                    </label>

                    {form.shipInternational ? (
                      <>
                        <p className="mt-3 text-sm leading-7 text-white/75">{internationalShipping.note}</p>
                        <div className="mt-4 grid gap-5 md:grid-cols-2">
                          <div>
                            <label className="mb-2 block text-sm text-mist">{t('International carrier', 'International carrier')}</label>
                            <Input value={form.internationalCarrier} disabled />
                          </div>
                          {(internationalShipping.zones ?? []).map((zone) => (
                            <div key={zone.key}>
                              <label className="mb-2 block text-sm text-mist">{zone.label}</label>
                              <Input
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.internationalRates?.[zone.key] ?? ''}
                                onChange={(event) => updateInternationalRate(zone.key, event.target.value)}
                                placeholder={t('DHL cost', 'DHL cost')}
                              />
                            </div>
                          ))}
                        </div>
                      </>
                    ) : null}
                  </div>

                  <div className="grid gap-5 md:grid-cols-2">
                    <div>
                      <label className="mb-2 block text-sm text-mist">{t('Dispatch time', 'Dispatch time')}</label>
                      <Select value={form.dispatchTime} onChange={(event) => updateForm('dispatchTime', event.target.value)}>
                        {listingCommonOptions.dispatchTimes.map((item) => (
                          <option key={item} value={item}>
                            {item}
                          </option>
                        ))}
                      </Select>
                    </div>
                    <div>
                      <label className="mb-2 block text-sm text-mist">{t('Packaging', 'Packaging')}</label>
                      <Select value={form.packaging} onChange={(event) => updateForm('packaging', event.target.value)}>
                        {packagingOptions.map((item) => (
                          <option key={item} value={item}>
                            {item}
                          </option>
                        ))}
                      </Select>
                    </div>
                  </div>

                  <div>
                    <label className="mb-2 block text-sm text-mist">{t('Shipping notes', 'Shipping notes')}</label>
                    <Textarea value={form.shippingNotes} onChange={(event) => updateForm('shippingNotes', event.target.value)} />
                  </div>
                </div>
              ) : null}

              {step === 7 ? <div className="space-y-5"><div className="grid gap-4 md:grid-cols-2">{reviewRows.map(([label, value]) => <div key={label} className="rounded-[20px] border border-white/8 bg-white/5 p-4"><p className="text-[11px] uppercase tracking-[0.28em] text-white/45">{label}</p><p className="mt-2 text-sm font-semibold text-white">{value}</p></div>)}</div>{isLootLot ? <div className="rounded-[20px] border border-white/8 bg-white/5 p-4"><p className="text-[11px] uppercase tracking-[0.28em] text-white/45">{t('Lot summary', 'Lot summary')}</p><div className="mt-3 flex flex-wrap gap-2">{lotPreviewCards.slice(0, 5).map((item) => <Badge key={item} tone="muted">{item}</Badge>)}{Number(form.lotCardCount || 0) > lotPreviewCards.slice(0, 5).length ? <Badge tone="info">+{Math.max(Number(form.lotCardCount || 0) - lotPreviewCards.slice(0, 5).length, 0)} {t('cards', 'cards')}</Badge> : null}</div><p className="mt-3 text-sm leading-7 text-mist">{form.lotSummary || t('No lot summary has been added yet.', 'No lot summary has been added yet.')}</p></div> : null}<div className="grid gap-3 md:grid-cols-2">{listingCommonOptions.complianceChecks.map((item) => <label key={item} className="flex items-start gap-3 rounded-2xl border border-white/8 bg-white/5 px-4 py-3 text-sm text-white/80"><input type="checkbox" checked={form.complianceAcknowledgements.includes(item)} onChange={() => toggleArrayValue('complianceAcknowledgements', item)} className="mt-1 h-4 w-4 rounded border-white/20 bg-transparent text-gold-300 focus:ring-gold-300/30" /><span>{item}</span></label>)}</div></div> : null}

              {stepError ? <div className="rounded-xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">{stepError}</div> : null}
              <div className="flex flex-wrap items-center justify-between gap-3 border-t border-white/8 pt-5"><Button type="button" variant="ghost" onClick={() => goToStep(step - 1)} disabled={step === 1}>{t('Previous', 'Previous')}</Button><div className="flex flex-wrap gap-3">{step < listingSteps.length ? <Button type="button" onClick={() => validateCurrentStep() && goToStep(step + 1)}>{t('Continue', 'Continue')}</Button> : <><Button type="button" variant="secondary" onClick={() => submitListing('draft')} disabled={isSubmitting}>{isSubmitting ? t('Saving...', 'Saving...') : t('Save as draft', 'Save as draft')}</Button><Button type="button" onClick={() => submitListing('review')} disabled={isSubmitting}>{isSubmitting ? t('Submitting...', 'Submitting...') : t('Submit for review', 'Submit for review')}</Button></>}</div></div>
            </div>
          </CardSurface>
        </div>

        <div className="space-y-6">
          <ListingPreviewCard preview={preview} seller={currentUser} />
          <CardSurface>
            <h3 className="font-display text-3xl text-white">{t('Before you publish', 'Before you publish')}</h3>
            <div className="mt-4 space-y-3">{[t('Use the title for the main hook and the subtitle for quick value signals.', 'Use the title for the main hook and the subtitle for quick value signals.'), t('For lots, show a few recognisable cards and explain the overall mix clearly.', 'For lots, show a few recognisable cards and explain the overall mix clearly.'), t('For graded or sealed items, make sure the label, corners and packaging are visible in good light.', 'For graded or sealed items, make sure the label, corners and packaging are visible in good light.')].map((tip) => <div key={tip} className="rounded-2xl border border-white/8 bg-white/5 px-4 py-3 text-sm leading-7 text-white/78">{tip}</div>)}</div>
          </CardSurface>
        </div>
      </div>
    </div>
  )
}

export default CreateListingPage




