import StructuredData from '@/components/StructuredData'
import { collectionStructuredData } from '@/utils/structuredData'
import { getSeoUrls } from '@/utils/seoUrls'
import { useLocation } from 'react-router-dom'
import { Link } from 'react-router-dom'
import { catalogCopy } from '@/data/catalogCopy'
import { localizePath } from '@/utils/helpers'
import { Filter } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useCatalogQuery } from '@/hooks/useCatalogQuery'
import Breadcrumbs from '@/components/catalog/Breadcrumbs'
import FilterSidebar from '@/components/catalog/FilterSidebar'
import PageSeo from '@/components/PageSeo'
import NotFoundPage from '@/pages/NotFoundPage'
import ProductCard from '@/components/catalog/ProductCard'
import SearchBar from '@/components/catalog/SearchBar'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import Drawer from '@/components/ui/Drawer'
import EmptyState from '@/components/ui/EmptyState'
import Pagination from '@/components/ui/Pagination'
import SectionHeader from '@/components/ui/SectionHeader'
import { useSeo } from '@/context/SeoContext'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'

const CATEGORY_SLUG_TO_SEO_KEY = {
  kartes: 'category.cards',
  figoures: 'category.figures',
  'komik-vivlia': 'category.comics',
  diafora: 'category.misc',
}

function CategoryPage({ categorySlug }) {
  const { locale } = useI18n()
  const location = useLocation()
  const { isReady, categories, searchProducts, getDynamicFacets, defaultSearchFilters, allFilterValue } =
    useMarketplace()
  const [mobileFiltersOpen, setMobileFiltersOpen] = useState(false)
  const category = categories.find((item) => item.slug === categorySlug)
  const seo = useSeo(CATEGORY_SLUG_TO_SEO_KEY[categorySlug])

  const { page, sort, filters, setSort, setFilters, pageHref } =
    useCatalogQuery(defaultSearchFilters, allFilterValue)

  const copy =
    locale === 'en'
      ? {
          home: 'Home',
          heroCategory: 'Hero category',
          collectorCategory: 'Collector category',
          listings: locale === 'en' ? 'Listings' : 'Αγγελίες',
          sales: 'Orders',
          verified: 'Verified sellers here',
          resultsEyebrow: 'Search Results',
          resultsDescription: (count) =>
            `${count} listings match your selection.`,
          filters: 'Filters',
          sortNewest: 'Newest first',
          sortPriceAsc: 'Price: low to high',
          sortPriceDesc: 'Price: high to low',
          sortRating: 'Seller rating',
          emptyTitle: 'No items found',
          emptyDescription: 'Try broader filters or a different query.',
          clearFilters: 'Clear filters',
        }
      : {
          home: '\u0391\u03c1\u03c7\u03b9\u03ba\u03ae',
          heroCategory: '\u039a\u03b5\u03bd\u03c4\u03c1\u03b9\u03ba\u03ae \u03ba\u03b1\u03c4\u03b7\u03b3\u03bf\u03c1\u03af\u03b1',
          collectorCategory: '\u039a\u03b1\u03c4\u03b7\u03b3\u03bf\u03c1\u03af\u03b1 \u03c3\u03c5\u03bb\u03bb\u03b5\u03ba\u03c4\u03ce\u03bd',
          listings: locale === 'en' ? 'Listings' : 'Αγγελίες',
          sales: 'Παραγγελίες',
          verified: '\u0395\u03c0\u03b1\u03bb\u03b7\u03b8\u03b5\u03c5\u03bc\u03ad\u03bd\u03bf\u03b9 \u03c0\u03c9\u03bb\u03b7\u03c4\u03ad\u03c2 \u03b5\u03b4\u03ce',
          resultsEyebrow: '\u0391\u03c0\u03bf\u03c4\u03b5\u03bb\u03ad\u03c3\u03bc\u03b1\u03c4\u03b1 \u03b1\u03bd\u03b1\u03b6\u03ae\u03c4\u03b7\u03c3\u03b7\u03c2',
          resultsDescription: (count) =>
            `${count} αγγελίες ταιριάζουν στις επιλογές σου.`,
          filters: '\u03a6\u03af\u03bb\u03c4\u03c1\u03b1',
          sortNewest: '\u039d\u03b5\u03cc\u03c4\u03b5\u03c1\u03b1 \u03c0\u03c1\u03ce\u03c4\u03b1',
          sortPriceAsc: '\u03a4\u03b9\u03bc\u03ae: \u03c7\u03b1\u03bc\u03b7\u03bb\u03ae \u03c3\u03b5 \u03c5\u03c8\u03b7\u03bb\u03ae',
          sortPriceDesc: '\u03a4\u03b9\u03bc\u03ae: \u03c5\u03c8\u03b7\u03bb\u03ae \u03c3\u03b5 \u03c7\u03b1\u03bc\u03b7\u03bb\u03ae',
          sortRating: 'Βαθμολογία πωλητή',
          emptyTitle: '\u0394\u03b5\u03bd \u03b2\u03c1\u03ad\u03b8\u03b7\u03ba\u03b1\u03bd \u03b1\u03bd\u03c4\u03b9\u03ba\u03b5\u03af\u03bc\u03b5\u03bd\u03b1',
          emptyDescription:
            '\u0394\u03bf\u03ba\u03af\u03bc\u03b1\u03c3\u03b5 \u03c0\u03b9\u03bf \u03b5\u03bb\u03b1\u03c3\u03c4\u03b9\u03ba\u03ac \u03c6\u03af\u03bb\u03c4\u03c1\u03b1 \u03ae \u03bd\u03ad\u03b1 \u03b1\u03bd\u03b1\u03b6\u03ae\u03c4\u03b7\u03c3\u03b7.',
          clearFilters: '\u039a\u03b1\u03b8\u03b1\u03c1\u03b9\u03c3\u03bc\u03cc\u03c2 \u03c6\u03af\u03bb\u03c4\u03c1\u03c9\u03bd',
        }

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }, [page])

  if (!category) return isReady ? <NotFoundPage /> : null

  const results = searchProducts({
    categoryId: category.id,
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
    categoryId: category.id,
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
      search: '',
    })
  }

  return (
    <div className="container pb-14">
      <StructuredData value={collectionStructuredData({ title: seo?.h1 || category.name, canonical: getSeoUrls(location.pathname, location.search, locale).canonical, products: paginated, locale, start: (page - 1) * perPage })} />
      <PageSeo
        pageKey={CATEGORY_SLUG_TO_SEO_KEY[categorySlug]}
        fallbackTitle={category.name}
        fallbackDescription={catalogCopy[locale]?.[categorySlug]?.short || category.description}
      />
      <CardSurface className="overflow-hidden p-6 sm:p-7">
        <Breadcrumbs items={[{ label: copy.home, href: '/' }, { label: category.name }]} />
        <div className="mt-5 grid gap-5 xl:grid-cols-[1.2fr,0.8fr]">
          <div>
            <p className="text-xs uppercase tracking-[0.35em] text-gold-100">
              {category.id === 'cards' ? copy.heroCategory : copy.collectorCategory}
            </p>
            <h1 className="mt-3.5 font-display text-4xl text-white md:text-5xl">
              {seo?.h1 || category.name}
            </h1>
            {seo?.h2 ? (
              <h2 className="mt-2 font-display text-xl text-gold-100 md:text-2xl">{seo.h2}</h2>
            ) : null}
            <p className="mt-3.5 max-w-3xl text-base leading-7 text-mist">{catalogCopy[locale]?.[categorySlug]?.intro || category.description}</p>
            <Link className="mt-3 inline-block text-sm text-gold-700 underline" to={localizePath('/katastasi-kai-grading', locale)}>{locale === 'en' ? 'Understand condition and grading' : 'Δείτε τον οδηγό κατάστασης και grading'}</Link>
            <div className="mt-5 flex flex-wrap gap-2">
              {(category.spotlightFilters ?? []).map((item) => (
                <span
                  key={item}
                  className="rounded-full border border-white/10 bg-white/5 px-3.5 py-1.5 text-[13px] text-white/75"
                >
                  {item}
                </span>
              ))}
            </div>
          </div>

          <div className="grid gap-2.5 sm:grid-cols-3 xl:grid-cols-1">
            <div className="rounded-[20px] border border-white/8 bg-white/5 p-3.5">
              <p className="text-xs uppercase tracking-[0.3em] text-white/50">{copy.listings}</p>
              <p className="mt-1.5 text-xl font-semibold text-white">{category.metrics?.listings ?? 0}</p>
            </div>
            <div className="rounded-[20px] border border-white/8 bg-white/5 p-3.5">
              <p className="text-xs uppercase tracking-[0.3em] text-white/50">{copy.sales}</p>
              <p className="mt-1.5 text-xl font-semibold text-white">{category.metrics?.sold ?? 0}</p>
            </div>
            <div className="rounded-[20px] border border-white/8 bg-white/5 p-3.5">
              <p className="text-xs uppercase tracking-[0.3em] text-white/50">{copy.verified}</p>
              <p className="mt-1.5 text-xl font-semibold text-white">{category.metrics?.verified ?? 0}</p>
            </div>
          </div>
        </div>
      </CardSurface>

      <div className="mt-8">
        <SearchBar
          defaultCategory={category.id}
          showSort={false}
          initialValues={{
            query: filters.search,
            categoryId: category.id,
            price: filters.price,
            condition: filters.condition,
            sort,
          }}
        />
      </div>

      <div className="mt-8 grid gap-6 xl:grid-cols-[290px,1fr]">
        <div className="hidden xl:block">
          <FilterSidebar
            facetGroups={facetGroups}
            spotlightFilters={category.spotlightFilters}
            filters={filters}
            onChange={updateFilter}
            onReset={resetFilters}
          />
        </div>

        <div>
          <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
            <SectionHeader
              className="mb-0"
              eyebrow={copy.resultsEyebrow}
              title={`${category.name} marketplace`}
              description={copy.resultsDescription(sorted.length)}
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
          spotlightFilters={category.spotlightFilters}
          filters={filters}
          onChange={updateFilter}
          onReset={resetFilters}
        />
      </Drawer>
    </div>
  )
}

export default CategoryPage
