import { Search } from 'lucide-react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input, Select } from '@/components/ui/Input'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { formatCurrency } from '@/utils/formatters'
import { cn } from '@/utils/helpers'

const ALL_CATEGORY_VALUE = 'all'

function SearchBar({
  compact = false,
  className,
  defaultCategory = ALL_CATEGORY_VALUE,
  placeholder,
  initialValues,
  showSort = true,
}) {
  const { t, locale } = useI18n()
  const navigate = useNavigate()
  const {
    categories,
    getSearchSuggestions,
    getDynamicFacets,
    defaultSearchFilters,
    allFilterValue,
  } = useMarketplace()

  const [query, setQuery] = useState(initialValues?.query ?? '')
  const [categoryId, setCategoryId] = useState(
    initialValues?.categoryId ?? defaultCategory ?? ALL_CATEGORY_VALUE,
  )
  const [price, setPrice] = useState(initialValues?.price ?? defaultSearchFilters.price)
  const [condition, setCondition] = useState(
    initialValues?.condition ?? defaultSearchFilters.condition,
  )
  const [sort, setSort] = useState(initialValues?.sort ?? 'newest')
  const [isFocused, setIsFocused] = useState(false)
  const [activeSuggestionIndex, setActiveSuggestionIndex] = useState(-1)
  const containerRef = useRef(null)
  const resolvedPlaceholder = placeholder || t('search.placeholder')
  const initialSyncKey = [
    initialValues?.query ?? '',
    initialValues?.categoryId ?? defaultCategory ?? ALL_CATEGORY_VALUE,
    initialValues?.price ?? defaultSearchFilters.price,
    initialValues?.condition ?? defaultSearchFilters.condition,
    initialValues?.sort ?? 'newest',
  ].join('::')

  useEffect(() => {
    if (!initialValues) return

    setQuery(initialValues.query ?? '')
    setCategoryId(initialValues.categoryId ?? defaultCategory ?? ALL_CATEGORY_VALUE)
    setPrice(initialValues.price ?? defaultSearchFilters.price)
    setCondition(initialValues.condition ?? defaultSearchFilters.condition)
    setSort(initialValues.sort ?? 'newest')
  }, [defaultCategory, defaultSearchFilters.condition, defaultSearchFilters.price, initialSyncKey])

  useEffect(() => {
    const handlePointerDown = (event) => {
      if (!containerRef.current?.contains(event.target)) {
        setIsFocused(false)
        setActiveSuggestionIndex(-1)
      }
    }

    document.addEventListener('mousedown', handlePointerDown)

    return () => {
      document.removeEventListener('mousedown', handlePointerDown)
    }
  }, [])

  const selectedCategoryId =
    categoryId === ALL_CATEGORY_VALUE ? undefined : categoryId || defaultCategory

  const facetGroups = getDynamicFacets({
    categoryId: selectedCategoryId,
    filters: {
      ...defaultSearchFilters,
      search: query,
      price,
      condition,
    },
  })

  const priceOptions = useMemo(() => {
    const group = facetGroups.find((item) => item.key === 'price')
    return (
      group?.options ?? [
        { value: allFilterValue, label: locale === 'en' ? 'All' : '\u038c\u03bb\u03b5\u03c2' },
      ]
    )
  }, [allFilterValue, facetGroups, locale])

  const conditionOptions = useMemo(() => {
    const group = facetGroups.find((item) => item.key === 'condition')
    return (
      group?.options ?? [
        { value: allFilterValue, label: locale === 'en' ? 'All' : '\u038c\u03bb\u03b5\u03c2' },
      ]
    )
  }, [allFilterValue, facetGroups, locale])

  const suggestions = useMemo(
    () =>
      getSearchSuggestions(query, {
        categoryId: selectedCategoryId,
        limit: 5,
      }),
    [getSearchSuggestions, query, selectedCategoryId],
  )

  const showSuggestions = isFocused && (suggestions.length > 0 || Boolean(query.trim()))

  const copy =
    locale === 'en'
      ? {
          allCategories: 'All categories',
          noMatches: 'No direct matches yet',
          viewAll: 'View all results',
          sortNewest: 'Newest first',
          sortPriceAsc: 'Price: low to high',
          sortPriceDesc: 'Price: high to low',
          sortRating: 'Seller rating',
        }
      : {
          allCategories: '\u038c\u03bb\u03b5\u03c2 \u03bf\u03b9 \u03ba\u03b1\u03c4\u03b7\u03b3\u03bf\u03c1\u03af\u03b5\u03c2',
          noMatches: '\u0394\u03b5\u03bd \u03c5\u03c0\u03ac\u03c1\u03c7\u03bf\u03c5\u03bd \u03b1\u03ba\u03cc\u03bc\u03b1 \u03ac\u03bc\u03b5\u03c3\u03b1 matches',
          viewAll:
            '\u03a0\u03c1\u03bf\u03b2\u03bf\u03bb\u03ae \u03cc\u03bb\u03c9\u03bd \u03c4\u03c9\u03bd \u03b1\u03c0\u03bf\u03c4\u03b5\u03bb\u03b5\u03c3\u03bc\u03ac\u03c4\u03c9\u03bd',
          sortNewest: '\u039d\u03b5\u03cc\u03c4\u03b5\u03c1\u03b1 \u03c0\u03c1\u03ce\u03c4\u03b1',
          sortPriceAsc: '\u03a4\u03b9\u03bc\u03ae: \u03c7\u03b1\u03bc\u03b7\u03bb\u03ae \u03c3\u03b5 \u03c5\u03c8\u03b7\u03bb\u03ae',
          sortPriceDesc: '\u03a4\u03b9\u03bc\u03ae: \u03c5\u03c8\u03b7\u03bb\u03ae \u03c3\u03b5 \u03c7\u03b1\u03bc\u03b7\u03bb\u03ae',
          sortRating: 'Seller rating',
        }

  const navigateToSearch = ({ targetQuery = query, targetCategory = categoryId } = {}) => {
    const params = new URLSearchParams()
    const cleanedQuery = targetQuery?.trim()

    if (cleanedQuery) params.set('q', cleanedQuery)
    if (targetCategory && targetCategory !== ALL_CATEGORY_VALUE) params.set('category', targetCategory)
    if (price && String(price) !== String(allFilterValue)) params.set('price', price)
    if (condition && String(condition) !== String(allFilterValue)) params.set('condition', condition)
    if (sort !== 'newest') params.set('sort', sort)

    navigate(`/anazitisi${params.toString() ? `?${params.toString()}` : ''}`)
    setIsFocused(false)
    setActiveSuggestionIndex(-1)
  }

  const handleSubmit = (event) => {
    event.preventDefault()
    navigateToSearch()
  }

  const handleInputKeyDown = (event) => {
    if (!showSuggestions) {
      if (event.key === 'Enter') {
        event.preventDefault()
        navigateToSearch()
      }
      return
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setActiveSuggestionIndex((previous) =>
        Math.min(previous + 1, suggestions.length),
      )
      return
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault()
      setActiveSuggestionIndex((previous) => Math.max(previous - 1, -1))
      return
    }

    if (event.key === 'Escape') {
      setIsFocused(false)
      setActiveSuggestionIndex(-1)
      return
    }

    if (event.key === 'Enter') {
      event.preventDefault()

      if (activeSuggestionIndex >= 0 && activeSuggestionIndex < suggestions.length) {
        navigate(`/proion/${suggestions[activeSuggestionIndex].slug}`)
      } else {
        navigateToSearch()
      }

      setIsFocused(false)
      setActiveSuggestionIndex(-1)
    }
  }

  const renderSearchInput = () => (
    <div ref={containerRef} className="relative z-20 flex-1">
      <Search className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gold-200" />
      <Input
        value={query}
        onChange={(event) => {
          setQuery(event.target.value)
          setActiveSuggestionIndex(-1)
        }}
        onFocus={() => setIsFocused(true)}
        onKeyDown={handleInputKeyDown}
        className={cn(
          'pl-9',
          compact &&
            'h-[44px] rounded-[14px] border-[#e3d0a8] bg-white pr-3.5 text-[14px] shadow-[0_10px_24px_rgba(199,168,103,0.12)]',
        )}
        placeholder={resolvedPlaceholder}
      />

      {showSuggestions ? (
        <div
          className={cn(
            'absolute top-[calc(100%+8px)] z-30 overflow-hidden rounded-2xl border bg-white/95 p-2 shadow-[0_24px_40px_rgba(160,130,73,0.18)] backdrop-blur-xl',
            compact
              ? 'left-0 w-[min(430px,calc(100vw-20px))] border-gold-300/25'
              : 'left-0 right-0 border-[#eadab7]',
          )}
        >
          {suggestions.length ? (
            <div className="space-y-1">
              {suggestions.map((item, index) => (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => {
                    navigate(`/proion/${item.slug}`)
                    setIsFocused(false)
                    setActiveSuggestionIndex(-1)
                  }}
                  className={cn(
                    'flex w-full items-center gap-2.5 rounded-xl border border-transparent px-2.5 py-2 text-left transition',
                    activeSuggestionIndex === index
                      ? 'border-gold-300/28 bg-gold-300/10'
                      : 'hover:border-[#eadab7] hover:bg-[#fffaf0]',
                  )}
                >
                  <div className="h-10 w-10 shrink-0 overflow-hidden rounded-lg border border-[#eadab7] bg-[#fffaf0]">
                    {item.mediaUrl ? (
                      <img
                        src={item.mediaUrl}
                        alt={item.title}
                        className="h-full w-full object-cover"
                        loading="lazy"
                      />
                    ) : null}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-ink">{item.title}</p>
                    <p className="truncate text-xs text-mist">
                      {[item.subtitle, item.franchise, item.categoryName].filter(Boolean).join(' \u2022 ')}
                    </p>
                  </div>
                  <p className="text-xs font-semibold text-gold-100">{formatCurrency(item.price)}</p>
                </button>
              ))}
            </div>
          ) : (
            <div className="rounded-xl border border-dashed border-[#eadab7] bg-white px-3 py-2.5 text-sm text-mist">
              {copy.noMatches}
            </div>
          )}

          <button
            type="button"
            onClick={() => navigateToSearch()}
            className={cn(
              'mt-2 flex w-full items-center justify-between rounded-xl border px-3 py-2 text-left text-sm transition',
              activeSuggestionIndex === suggestions.length
                ? 'border-gold-300/32 bg-gold-300/12 text-gold-100'
                : 'border-[#eadab7] bg-white text-ink hover:border-gold-300/28 hover:bg-gold-300/10 hover:text-gold-700',
            )}
          >
            <span className="truncate font-medium">{copy.viewAll}</span>
            <Search className="h-4 w-4" />
          </button>
        </div>
      ) : null}
    </div>
  )

  if (compact) {
    return (
      <form onSubmit={handleSubmit} className={cn('flex w-full items-center', className)}>
        {renderSearchInput()}
      </form>
    )
  }

  return (
    <CardSurface className={cn('relative z-10 overflow-visible p-3.5 sm:p-4', className)}>
      <form
        onSubmit={handleSubmit}
        className={cn(
          'relative z-10 grid gap-2.5',
          showSort ? 'lg:grid-cols-[1.7fr,1fr,1fr,1fr,1fr,auto]' : 'lg:grid-cols-[1.7fr,1fr,1fr,auto]',
        )}
      >
        {renderSearchInput()}

        <Select value={categoryId} onChange={(event) => setCategoryId(event.target.value)}>
          <option value={ALL_CATEGORY_VALUE}>{copy.allCategories}</option>
          {categories.map((category) => (
            <option key={category.id} value={category.id}>
              {category.name}
            </option>
          ))}
        </Select>

        <Select value={price} onChange={(event) => setPrice(event.target.value)}>
          {priceOptions.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </Select>

        <Select value={condition} onChange={(event) => setCondition(event.target.value)}>
          {conditionOptions.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </Select>

        {showSort ? (
          <Select value={sort} onChange={(event) => setSort(event.target.value)}>
            <option value="newest">{copy.sortNewest}</option>
            <option value="price-asc">{copy.sortPriceAsc}</option>
            <option value="price-desc">{copy.sortPriceDesc}</option>
            <option value="rating">{copy.sortRating}</option>
          </Select>
        ) : null}

        <Button type="submit">{t('common.search')}</Button>
      </form>
    </CardSurface>
  )
}

export default SearchBar
