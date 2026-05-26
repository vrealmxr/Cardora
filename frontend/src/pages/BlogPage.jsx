import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import BlogCard from '@/components/blog/BlogCard'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Input } from '@/components/ui/Input'
import SectionHeader from '@/components/ui/SectionHeader'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'

const ALL_CATEGORY = '__all__'

function BlogPage() {
  const { locale } = useI18n()
  const { blogCategories, featuredBlogPosts, latestBlogPosts } = useMarketplace()
  const [activeCategory, setActiveCategory] = useState(ALL_CATEGORY)
  const [query, setQuery] = useState('')

  const copy =
    locale === 'en'
      ? {
          all: 'All',
          badge: 'Cardora Journal',
          badgeSecondary: 'Collector guides, market notes and trusted news',
          title: 'Long-form articles for collectors who want clearer decisions.',
          description:
            'Read practical Cardora guides alongside researched updates from the wider hobby, from cards and comics to figures and premium collectibles.',
          listItem: 'Create listing',
          learnMore: 'About Cardora',
          focusTitle: 'What you will find here',
          focusText:
            'Pricing guides, category watchlists, official release coverage and practical advice for presenting, protecting and selling collectible items.',
          whyTitle: 'Why this section exists',
          whyText:
            'Because a serious marketplace should not only process transactions. It should also help its members think more clearly before they buy or sell.',
          discoverEyebrow: 'Find Articles',
          discoverTitle: 'Browse by topic or category',
          discoverDescription:
            'Search for the subject you care about most, whether that is card value, new comic issues, figures, packaging or safer marketplace habits.',
          searchPlaceholder: 'Search article, tag or topic',
          emptyTitle: 'No articles found',
          emptyDescription: 'Try a different keyword or choose another category.',
        }
      : {
          all: 'Όλα',
          badge: 'Blog Cardora',
          badgeSecondary: 'Οδηγοί συλλογής, νέα αγοράς και επίσημες πηγές',
          title: 'Άρθρα, οδηγοί και νέα για συλλέκτες που θέλουν να κάνουν πιο σωστές επιλογές.',
          description:
            'Το blog της Cardora συγκεντρώνει ουσιαστικούς οδηγούς της πλατφόρμας μαζί με ερευνημένα νέα από επίσημες πηγές για κάρτες, κόμικς, βιβλία, φιγούρες και premium συλλεκτικά.',
          listItem: 'Δημιούργησε αγγελία',
          learnMore: 'Μάθε περισσότερα για την Cardora',
          focusTitle: 'Τι θα βρεις εδώ',
          focusText:
            'Οδηγούς αποτίμησης, release watchlists, πρακτικά tips για συσκευασία και άρθρα που σε βοηθούν να παρουσιάζεις σωστά ένα συλλεκτικό κομμάτι πριν βγει δημόσια.',
          whyTitle: 'Γιατί υπάρχει αυτή η ενότητα',
          whyText:
            'Γιατί ένα σοβαρό marketplace δεν αρκεί να ολοκληρώνει συναλλαγές. Πρέπει και να βοηθά τα μέλη του να αγοράζουν και να πουλούν με περισσότερη σιγουριά.',
          discoverEyebrow: 'Ανακάλυψε άρθρα',
          discoverTitle: 'Βρες περιεχόμενο ανά θέμα ή κατηγορία',
          discoverDescription:
            'Αναζήτησε αυτό που σε ενδιαφέρει περισσότερο, από αξία κάρτας και grading μέχρι νέα κόμικς, φιγούρες, packing και ασφαλέστερες συναλλαγές.',
          searchPlaceholder: 'Αναζήτησε άρθρο, tag ή θέμα',
          emptyTitle: 'Δεν βρέθηκαν άρθρα',
          emptyDescription: 'Δοκίμασε άλλη λέξη-κλειδί ή επίλεξε διαφορετική κατηγορία.',
        }

  const categoryOptions = useMemo(
    () => [{ value: ALL_CATEGORY, label: copy.all }, ...blogCategories.filter(Boolean).map((category) => ({ value: category, label: category }))],
    [blogCategories, copy.all],
  )

  const filteredPosts = useMemo(
    () =>
      latestBlogPosts.filter((post) => {
        const matchesCategory = activeCategory === ALL_CATEGORY || post.category === activeCategory
        const matchesQuery =
          !query ||
          [post.title, post.excerpt, post.tags.join(' ')]
            .join(' ')
            .toLowerCase()
            .includes(query.toLowerCase())

        return matchesCategory && matchesQuery
      }),
    [activeCategory, latestBlogPosts, query],
  )

  const [heroPost, secondaryPost] = featuredBlogPosts

  return (
    <div className="container pb-16">
      <CardSurface className="overflow-hidden p-6 sm:p-7">
        <div className="grid gap-6 xl:grid-cols-[1.1fr,0.9fr] xl:items-center">
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone="gold">{copy.badge}</Badge>
              <Badge tone="info">{copy.badgeSecondary}</Badge>
            </div>
            <h1 className="mt-5 font-display text-5xl text-white sm:text-6xl">{copy.title}</h1>
            <p className="mt-4 max-w-3xl text-sm leading-7 text-mist">{copy.description}</p>
            <div className="mt-6 flex flex-wrap gap-3">
              <Button as={Link} to="/dimiourgia-aggelias">
                {copy.listItem}
              </Button>
              <Button as={Link} to="/cardora" variant="secondary">
                {copy.learnMore}
              </Button>
            </div>
          </div>

          <div className="space-y-3">
            <div className="rounded-[22px] border border-white/8 bg-white/5 p-4">
              <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.focusTitle}</p>
              <p className="mt-2 text-sm leading-7 text-white/80">{copy.focusText}</p>
            </div>
            <div className="rounded-[22px] border border-white/8 bg-white/5 p-4">
              <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.whyTitle}</p>
              <p className="mt-2 text-sm leading-7 text-white/80">{copy.whyText}</p>
            </div>
          </div>
        </div>
      </CardSurface>

      {heroPost ? (
        <section className="mt-12 grid gap-6 xl:grid-cols-[1.2fr,0.8fr]">
          <BlogCard post={heroPost} featured />
          {secondaryPost ? <BlogCard post={secondaryPost} /> : <CardSurface />}
        </section>
      ) : null}

      <section className="mt-16">
        <SectionHeader
          eyebrow={copy.discoverEyebrow}
          title={copy.discoverTitle}
          description={copy.discoverDescription}
        />

        <div className="flex flex-col gap-4">
          <div className="max-w-xl">
            <Input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder={copy.searchPlaceholder}
            />
          </div>

          <div className="flex flex-wrap gap-2">
            {categoryOptions.map((category) => (
              <button
                key={category.value}
                type="button"
                onClick={() => setActiveCategory(category.value)}
                className={
                  activeCategory === category.value
                    ? 'rounded-full border border-gold-300/35 bg-gold-300/15 px-3.5 py-2 text-sm font-semibold text-gold-100'
                    : 'rounded-full border border-white/10 bg-white/5 px-3.5 py-2 text-sm text-white/70 transition hover:border-gold-300/25 hover:text-white'
                }
              >
                {category.label}
              </button>
            ))}
          </div>
        </div>

        {filteredPosts.length ? (
          <div className="mt-8 grid gap-5 lg:grid-cols-2">
            {filteredPosts.map((post) => (
              <BlogCard key={post.id} post={post} />
            ))}
          </div>
        ) : (
          <CardSurface className="mt-8 text-center">
            <h3 className="font-display text-3xl text-white">{copy.emptyTitle}</h3>
            <p className="mt-3 text-sm leading-7 text-mist">{copy.emptyDescription}</p>
          </CardSurface>
        )}
      </section>
    </div>
  )
}

export default BlogPage
