import { Filter } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useCatalogQuery } from '@/hooks/useCatalogQuery'
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
import PageSeo from '@/components/PageSeo'
import NotFoundPage from '@/pages/NotFoundPage'

const ALL_CATEGORY_VALUE = 'all'

function SearchResultsPage() {
  const { locale } = useI18n()
  const {
    categories,
    searchProducts,
    getDynamicFacets,
    defaultSearchFilters,
    allFilterValue,
    isReady,
  } = useMarketplace()
  const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false)
  const { page, sort, filters, category: selectedCategory, setSort, setFilters, pageHref } =
    useCatalogQuery(defaultSearchFilters, allFilterValue)

  const selectedCategoryId =
    selectedCategory === ALL_CATEGORY_VALUE ? undefined : selectedCategory
  const selectedCategoryMeta = categories.find((item) => item.id === selectedCategoryId) ?? null

  const copy =
    locale === 'en'
      ? {
          eyebrow: 'Smart Search',
          title: 'Search results',
          description: (count) =>
            `${count} listings match your search. Use the filters to narrow your results.`,
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
            `${count} αγγελίες ταιριάζουν στην αναζήτησή σου. Χρησιμοποίησε τα φίλτρα για πιο συγκεκριμένα αποτελέσματα.`,
          filters: '\u03a6\u03af\u03bb\u03c4\u03c1\u03b1',
          sortNewest: '\u039d\u03b5\u03cc\u03c4\u03b5\u03c1\u03b1 \u03c0\u03c1\u03ce\u03c4\u03b1',
          sortPriceAsc: '\u03a4\u03b9\u03bc\u03ae: \u03c7\u03b1\u03bc\u03b7\u03bb\u03ae \u03c3\u03b5 \u03c5\u03c8\u03b7\u03bb\u03ae',
          sortPriceDesc: '\u03a4\u03b9\u03bc\u03ae: \u03c5\u03c8\u03b7\u03bb\u03ae \u03c3\u03b5 \u03c7\u03b1\u03bc\u03b7\u03bb\u03ae',
          sortRating: 'Βαθμολογία πωλητή',
          emptyTitle:
            '\u0394\u03b5\u03bd \u03b2\u03c1\u03ad\u03b8\u03b7\u03ba\u03b1\u03bd \u03b1\u03b3\u03b3\u03b5\u03bb\u03af\u03b5\u03c2',
          emptyDescription:
            'Δοκίμασε διαφορετική λέξη, άλλη κατηγορία ή λιγότερα φίλτρα.',
          clearFilters: '\u039a\u03b1\u03b8\u03b1\u03c1\u03b9\u03c3\u03bc\u03cc\u03c2 \u03c6\u03af\u03bb\u03c4\u03c1\u03c9\u03bd',
        }

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }, [page])

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
  if (isReady && page > totalPages) return <NotFoundPage />
  const paginated = sorted.slice((page - 1) * perPage, page * perPage)

  const updateFilter = (key, value) => {
    setFilters({ [key]: filters[key] === value ? allFilterValue : value })
  }

  const resetFilters = () => {
    setFilters({
      ...defaultSearchFilters,
      search: filters.search,
    })
  }

  return (
    <div className="container pb-14">
      <PageSeo pageKey="search" fallbackTitle={`${copy.title} | Cardora`} fallbackDescription={copy.description(sorted.length)} noIndex />
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
              as="h1"
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
                aria-label={locale === 'en' ? 'Sort results' : 'Ταξινόμηση αποτελεσμάτων'}
                value={sort}
                onChange={(event) => setSort(event.target.value)}
                className="rounded-xl border border-[#eadab7] bg-white px-3.5 py-2.5 text-[13px] text-slate-700"
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
              <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                {paginated.map((product) => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </div>
              <Pagination page={page} totalPages={totalPages} getPageHref={pageHref} />
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
