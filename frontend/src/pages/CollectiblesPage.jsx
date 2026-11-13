import StructuredData from '@/components/StructuredData'
import { collectionStructuredData } from '@/utils/structuredData'
import { getSeoUrls } from '@/utils/seoUrls'
import { useLocation } from 'react-router-dom'
import { Link, useSearchParams } from 'react-router-dom'
import { Search } from 'lucide-react'
import PageSeo from '@/components/PageSeo'
import ProductCard from '@/components/catalog/ProductCard'
import Pagination from '@/components/ui/Pagination'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { Input } from '@/components/ui/Input'
import { useMarketplace } from '@/hooks/useMarketplace'
import { useI18n } from '@/hooks/useI18n'
import { useFormatters } from '@/hooks/useFormatters'
import { localizePath } from '@/utils/helpers'
import { readPage } from '@/utils/catalogQuery'
import { collectorHub, gradingSources } from '@/data/collectorHub'
import NotFoundPage from '@/pages/NotFoundPage'
import { editorialGuideSlugs } from '@/data/editorialGuideIndex'

const EDITORIAL_GUIDES = new Set([
  ...editorialGuideSlugs,
  'pos-na-apotimiseis-sosta-mia-karta-prin-ti-valeis-pros-polisi-stin-cardora',
  'pos-na-stiseis-premium-listing-kai-asfali-apostoli-choris-na-chaseis-trust-i-axia',
])

function CollectiblesPage() {
  const { locale } = useI18n()
  const location = useLocation()
  const { formatNumber } = useFormatters()
  const { productsWithSellers, categories, blogPosts, isReady } = useMarketplace()
  const [params] = useSearchParams()
  const page = readPage(params.get('page'))
  const copy = collectorHub[locale] || collectorHub.el
  const path = (value) => localizePath(value, locale)
  const products = productsWithSellers.filter(item => !item.status || ['active', 'published'].includes(item.status))
    .slice().sort((a, b) => new Date(b.listedAt || 0) - new Date(a.listedAt || 0) || Number(b.id) - Number(a.id))
  const totalPages = Math.max(1, Math.ceil(products.length / 24))
  if (isReady && page > totalPages) return <NotFoundPage />
  const visible = products.slice((page - 1) * 24, page * 24)
  const counts = Object.fromEntries(categories.map(category => [category.id, products.filter(item => item.categoryId === category.id).length]))
  const guides = blogPosts.filter(post => EDITORIAL_GUIDES.has(post.slug))

  return <div className="container space-y-12 pb-16">
    <StructuredData value={collectionStructuredData({ title: copy.title, canonical: getSeoUrls(location.pathname, location.search, locale).canonical, products: visible, locale, start: (page - 1) * 24 })} />
    <section>
      <PageSeo pageKey="collectibles" fallbackTitle={`${copy.title} | Cardora`} fallbackDescription={copy.description} />
      <SectionHeader as="h1" title={copy.title} description={copy.intro} />
      <ul className="mb-6 grid gap-3 text-sm text-ink sm:grid-cols-3">{copy.trust.map(text => <li key={text} className="rounded-2xl border border-gold-200/40 bg-white p-4">{text}</li>)}</ul>
      <form action={path('/anazitisi')} method="get" className="flex max-w-2xl gap-3">
        <label className="sr-only" htmlFor="collectible-search">{locale === 'en' ? 'Search collectibles' : 'Αναζήτηση συλλεκτικών'}</label>
        <Input id="collectible-search" name="q" placeholder={locale === 'en' ? 'Game, player, character or series' : 'Παιχνίδι, παίκτης, χαρακτήρας ή σειρά'} />
        <Button type="submit"><Search className="h-4 w-4" /><span>{locale === 'en' ? 'Search' : 'Αναζήτηση'}</span></Button>
      </form>
    </section>
    <section>
      <SectionHeader title={copy.categoriesTitle} />
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{categories.filter(category => copy.categories[category.slug]).map(category =>
        <Link key={category.id} to={path(`/${category.slug}`)} className="rounded-3xl border border-gold-200/40 bg-white p-6 transition hover:shadow-md">
          <h3 className="font-display text-2xl text-ink">{copy.categories[category.slug].title}</h3>
          <p className="mt-3 text-sm text-mist">{formatNumber(counts[category.id] || 0)} {copy.count}</p>
        </Link>)}</div>
    </section>
    <section>
      <SectionHeader title={copy.listingsTitle} />
      <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">{visible.map(product => <ProductCard key={product.id} product={product} />)}</div>
      {!visible.length && <p className="text-mist">{copy.empty}</p>}
      <Pagination page={page} totalPages={totalPages} getPageHref={value => path('/syllektika') + (value > 1 ? `?page=${value}` : '')} />
    </section>
    <section>
      <SectionHeader title={copy.categorySection} />
      <div className="grid gap-6 md:grid-cols-2">{Object.entries(copy.categories).map(([slug, category]) =>
        <CardSurface key={slug}><h3 className="font-display text-2xl text-ink"><Link to={path(`/${slug}`)}>{category.title}</Link></h3>
          <p className="mt-3 text-sm leading-7 text-mist">{category.text}</p></CardSurface>)}</div>
    </section>
    <section>
      <SectionHeader title={copy.buyingTitle} />
      <ol className="grid gap-6 md:grid-cols-2">{copy.buyingSteps.map(([title, text], index) => <li key={title}>
        <CardSurface className="h-full"><h3 className="font-display text-2xl text-ink">{index + 1}. {title}</h3><p className="mt-3 text-sm leading-7 text-mist">{text}</p></CardSurface>
      </li>)}</ol>
      <Link to={path('/epistrofes-kai-diafores')} className="mt-5 inline-block text-ink underline underline-offset-4">{copy.buyingLink}</Link>
    </section>
    <section>
      <SectionHeader title={copy.conditionTitle} description={copy.conditionIntro} />
      <div className="overflow-x-auto rounded-2xl border border-gold-200/40"><table className="w-full bg-white text-left text-sm">
        <caption className="sr-only">{copy.conditionTitle}</caption>
        <tbody>{copy.conditions.map(([term, description]) => <tr key={term} className="border-b border-gold-200/30 last:border-0">
          <th scope="row" className="w-1/4 p-4 font-semibold text-ink">{term}</th><td className="p-4 leading-7 text-mist">{description}</td>
        </tr>)}</tbody>
      </table></div>
      <p className="mt-4 text-sm leading-7 text-mist">{copy.conditionEnd}</p>
      <Link to={path('/katastasi-kai-grading')} className="mt-4 inline-block text-ink underline underline-offset-4">{locale === 'en' ? 'Condition and grading glossary' : 'Λεξιλόγιο κατάστασης και grading'}</Link>
      <ul className="mt-3 flex flex-wrap gap-4 text-sm">{gradingSources.map(([title, url]) => <li key={url}><a href={url} className="text-mist underline underline-offset-4">{title}</a></li>)}</ul>
    </section>
    <section>
      <SectionHeader title={copy.sellingTitle} description={copy.sellingText} />
      <div className="flex flex-wrap gap-3"><Button as={Link} to={path('/odigos-polisis')}>{copy.sellingAction}</Button><Button as={Link} to={path('/dimiourgia-aggelias')} variant="secondary">{copy.listingAction}</Button></div>
    </section>
    <section>
      <SectionHeader title={copy.guidesTitle} description={copy.guidesText} />
      <ul className="grid gap-4 md:grid-cols-2">{guides.map(post => <li key={post.slug} className="rounded-2xl border border-gold-200/40 bg-white p-5">
        <Link to={path(`/blog/${post.slug}`)} className="font-display text-2xl text-ink">{post.title}</Link><p className="mt-2 text-sm leading-7 text-mist">{post.excerpt}</p>
      </li>)}</ul>
    </section>
    <section>
      <SectionHeader title={copy.faqTitle} />
      <div className="space-y-3">{copy.faqs.map(([question, answer]) => <details key={question} className="rounded-2xl border border-gold-200/40 bg-white p-5">
        <summary className="cursor-pointer text-ink"><h3 className="inline font-semibold">{question}</h3></summary><p className="mt-3 text-sm leading-7 text-mist">{answer}</p>
      </details>)}</div>
    </section>
  </div>
}
export default CollectiblesPage
