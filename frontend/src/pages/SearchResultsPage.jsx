import { Filter } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import FilterSidebar from '@/components/catalog/FilterSidebar'
import ProductCard from '@/components/catalog/ProductCard'
import SearchBar from '@/components/catalog/SearchBar'
import Button from '@/components/ui/Button'
import Drawer from '@/components/ui/Drawer'
import EmptyState from '@/components/ui/EmptyState'
import Pagination from '@/components/ui/Pagination'
import SectionHeader from '@/components/ui/SectionHeader'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'

const ALL_CATEGORY_VALUE = 'all'

function SearchResultsPage() {
  const { locale } = useI18n()
  const {
    categories,
    searchProducts,
    getDynamicFacets,
    defaultSearchFilters,
    allFilterValue,
  } = useMarketplace()
  const [searchParams, setSearchParams] = useSearchParams()
  const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false)
  const [page, setPage] = useState(1)
  const [sort, setSort] = useState(searchParams.get('sort') ?? 'newest')
  const [selectedCategory, setSelectedCategory] = useState(
    searchParams.get('category') ?? ALL_CATEGORY_VALUE,
  )
  const [filters, setFilters] = useState(() => ({
    ...defaultSearchFilters,
    search: searchParams.get('q') ?? searchParams.get('search') ?? '',
    price: searchParams.get('price') ?? defaultSearchFilters.price,
    condition: searchParams.get('condition') ?? defaultSearchFilters.condition,
    rarity: searchParams.get('rarity') ?? defaultSearchFilters.rarity,
    franchise: searchParams.get('franchise') ?? defaultSearchFilters.franchise,
    brand: searchParams.get('brand') ?? defaultSearchFilters.brand,
    productType: searchParams.get('productType') ?? defaultSearchFilters.productType,
    graded: searchParams.get('graded') ?? defaultSearchFilters.graded,
    availability: searchParams.get('availability') ?? defaultSearchFilters.availability,
    sellerRating: searchParams.get('sellerRating') ?? defaultSearchFilters.sellerRating,
  }))

  const selectedCategoryId =
    selectedCategory === ALL_CATEGORY_VALUE ? undefined : selectedCategory
  const selectedCategoryMeta = categories.find((item) => item.id === selectedCategoryId) ?? null

  const copy =
    locale === 'en'
      ? {
          eyebrow: 'Smart Search',
          title: 'Search results',
          description: (count) =>
            `${count} listings matched your query with dynamic, relevance-aware filtering.`,
          filters: 'Filters',
          sortNewest: 'Newest first',
          sortPriceAsc: 'Price: low to high',
          sortPriceDesc: 'Price: high to low',
          sortRating: 'Seller rating',
          emptyTitle: 'No listings found',
          emptyDescription: 'Try broader filters, another category or a different search query.',
          clearFilters: 'Clear filters',
        }
      : {
          eyebrow: '\u0388\u03be\u03c5\u03c0\u03bd\u03b7 \u0391\u03bd\u03b1\u03b6\u03ae\u03c4\u03b7\u03c3\u03b7',
          title: '\u0391\u03c0\u03bf\u03c4\u03b5\u03bb\u03ad\u03c3\u03bc\u03b1\u03c4\u03b1 \u03b1\u03bd\u03b1\u03b6\u03ae\u03c4\u03b7\u03c3\u03b7\u03c2',
          description: (count) =>
            `${count} \u03b1\u03b3\u03b3\u03b5\u03bb\u03af\u03b5\u03c2 \u03bc\u03b5 relevance ranking \u03ba\u03b1\u03b9 \u03b4\u03c5\u03bd\u03b1\u03bc\u03b9\u03ba\u03ac \u03c6\u03af\u03bb\u03c4\u03c1\u03b1.`,
          filters: '\u03a6\u03af\u03bb\u03c4\u03c1\u03b1',
          sortNewest: '\u039d\u03b5\u03cc\u03c4\u03b5\u03c1\u03b1 \u03c0\u03c1\u03ce\u03c4\u03b1',
          sortPriceAsc: '\u03a4\u03b9\u03bc\u03ae: \u03c7\u03b1\u03bc\u03b7\u03bb\u03ae \u03c3\u03b5 \u03c5\u03c8\u03b7\u03bb\u03ae',
          sortPriceDesc: '\u03a4\u03b9\u03bc\u03ae: \u03c5\u03c8\u03b7\u03bb\u03ae \u03c3\u03b5 \u03c7\u03b1\u03bc\u03b7\u03bb\u03ae',
          sortRating: 'Seller rating',
          emptyTitle:
            '\u0394\u03b5\u03bd \u03b2\u03c1\u03ad\u03b8\u03b7\u03ba\u03b1\u03bd \u03b1\u03b3\u03b3\u03b5\u03bb\u03af\u03b5\u03c2',
          emptyDescription:
            '\u0394\u03bf\u03ba\u03af\u03bc\u03b1\u03c3\u03b5 \u03c0\u03b9\u03bf \u03b3\u03b5\u03bd\u03b9\u03ba\u03ac \u03c6\u03af\u03bb\u03c4\u03c1\u03b1 \u03ae \u03bd\u03ad\u03bf query.',
          clearFilters: '\u039a\u03b1\u03b8\u03b1\u03c1\u03b9\u03c3\u03bc\u03cc\u03c2 \u03c6\u03af\u03bb\u03c4\u03c1\u03c9\u03bd',
        }

  useEffect(() => {
    setPage(1)
  }, [filters, selectedCategory, sort])

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }, [page])

  useEffect(() => {
    setSort(searchParams.get('sort') ?? 'newest')
    setSelectedCategory(searchParams.get('category') ?? ALL_CATEGORY_VALUE)
    setFilters({
      ...defaultSearchFilters,
      search: searchParams.get('q') ?? searchParams.get('search') ?? '',
      price: searchParams.get('price') ?? defaultSearchFilters.price,
      condition: searchParams.get('condition') ?? defaultSearchFilters.condition,
      rarity: searchParams.get('rarity') ?? defaultSearchFilters.rarity,
      franchise: searchParams.get('franchise') ?? defaultSearchFilters.franchise,
      brand: searchParams.get('brand') ?? defaultSearchFilters.brand,
      productType: searchParams.get('productType') ?? defaultSearchFilters.productType,
      graded: searchParams.get('graded') ?? defaultSearchFilters.graded,
      availability: searchParams.get('availability') ?? defaultSearchFilters.availability,
      sellerRating: searchParams.get('sellerRating') ?? defaultSearchFilters.sellerRating,
    })
  }, [defaultSearchFilters, searchParams])

  useEffect(() => {
    const params = new URLSearchParams()

    if (filters.search?.trim()) params.set('q', filters.search.trim())
    if (selectedCategory && selectedCategory !== ALL_CATEGORY_VALUE) {
      params.set('category', selectedCategory)
    }

    Object.entries(filters).forEach(([key, value]) => {
      if (key === 'search' || value == null) return
      if (String(value) === String(allFilterValue)) return
      params.set(key, value)
    })

    if (sort !== 'newest') {
      params.set('sort', sort)
    }

    if (params.toString() !== searchParams.toString()) {
      setSearchParams(params, { replace: true })
    }
  }, [
    allFilterValue,
    filters,
    searchParams,
    selectedCategory,
    setSearchParams,
    sort,
  ])

  const results = searchProducts({
    categoryId: selectedCategoryId,
    query: filters.search,
    price: filters.price,
    condition: filters.condition,
    rarity: filters.rarity,
    franchise: filters.franchise,
    brand: filters.brand,
    productType: filters.productType,
    graded: filters.graded,
    availability: filters.availability,
    sellerRating: filters.sellerRating,
  })

  const sorted = [...results].sort((a, b) => {
    if (Number(Boolean(b.featured)) !== Number(Boolean(a.featured))) {
      return Number(Boolean(b.featured)) - Number(Boolean(a.featured))
    }

    if (sort === 'price-asc') return Number(a.price ?? 0) - Number(b.price ?? 0)
    if (sort === 'price-desc') return Number(b.price ?? 0) - Number(a.price ?? 0)
    if (sort === 'rating') return Number(b.sellerRating ?? 0) - Number(a.sellerRating ?? 0)
    return new Date(b.listedAt ?? 0) - new Date(a.listedAt ?? 0)
  })

  const facetGroups = getDynamicFacets({
    categoryId: selectedCategoryId,
    filters,
  })

  const perPage = 20
  const totalPages = Math.max(1, Math.ceil(sorted.length / perPage))
  const paginated = sorted.slice((page - 1) * perPage, page * perPage)

  const updateFilter = (key, value) => {
    setFilters((previous) => ({
      ...previous,
      [key]: previous[key] === value ? allFilterValue : value,
    }))
  }

  const resetFilters = () => {
    setFilters({
      ...defaultSearchFilters,
      search: filters.search,
    })
  }

  return (
    <div className="container pb-14">
      <SearchBar
        initialValues={{
          query: filters.search,
          categoryId: selectedCategory,
          price: filters.price,
          condition: filters.condition,
          sort,
        }}
      />

      <div className="mt-8 grid gap-6 xl:grid-cols-[290px,1fr]">
        <div className="hidden xl:block">
          <FilterSidebar
            facetGroups={facetGroups}
            spotlightFilters={selectedCategoryMeta?.spotlightFilters ?? []}
            filters={filters}
            onChange={updateFilter}
            onReset={resetFilters}
          />
        </div>

        <div>
          <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
            <SectionHeader
              className="mb-0"
              eyebrow={copy.eyebrow}
              title={copy.title}
              description={copy.description(sorted.length)}
            />

            <div className="flex flex-wrap items-center gap-3">
              <Button
                variant="secondary"
                size="sm"
                className="xl:hidden"
                onClick={() => setMobileFiltersOpen(true)}
              >
                <Filter className="h-4 w-4" />
                {copy.filters}
              </Button>

              <select
                value={sort}
                onChange={(event) => setSort(event.target.value)}
                className="rounded-xl border border-white/10 bg-white/5 px-3.5 py-2.5 text-[13px] text-white"
              >
                <option value="newest">{copy.sortNewest}</option>
                <option value="price-asc">{copy.sortPriceAsc}</option>
                <option value="price-desc">{copy.sortPriceDesc}</option>
                <option value="rating">{copy.sortRating}</option>
              </select>
            </div>
          </div>

          {paginated.length ? (
            <>
              <div className="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
                {paginated.map((product) => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </div>
              <Pagination page={page} totalPages={totalPages} onPageChange={setPage} />
            </>
          ) : (
            <EmptyState
              title={copy.emptyTitle}
              description={copy.emptyDescription}
              actionLabel={copy.clearFilters}
              onAction={resetFilters}
            />
          )}
        </div>
      </div>

      <Drawer
        open={mobileFiltersOpen}
        title={copy.filters}
        onClose={() => setMobileFiltersOpen(false)}
      >
        <FilterSidebar
          facetGroups={facetGroups}
          spotlightFilters={selectedCategoryMeta?.spotlightFilters ?? []}
          filters={filters}
          onChange={updateFilter}
          onReset={resetFilters}
        />
      </Drawer>
    </div>
  )
}

export default SearchResultsPage
