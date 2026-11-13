import { ArrowLeft, ExternalLink } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'
import BlogCard from '@/components/blog/BlogCard'
import PageSeo from '@/components/PageSeo'
import NotFoundPage from '@/pages/NotFoundPage'
import BlogEditorialCover from '@/components/blog/BlogEditorialCover'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { useFormatters } from '@/hooks/useFormatters'
import { localizePath } from '@/utils/helpers'
import { getEditorialGuide } from '@/data/editorialGuides'

function BlogArticlePage() {
  const { formatDate } = useFormatters()
  const { locale } = useI18n()
  const { slug } = useParams()
  const { getBlogPostBySlug, getRelatedBlogPosts, isReady } = useMarketplace()
  const post = getEditorialGuide(slug, locale) || getBlogPostBySlug(slug)

  const copy =
    locale === 'en'
      ? {
          back: 'Back to blog',
          intro: 'Article introduction',
          source: 'Official source',
          sourceButton: 'Open source',
          tags: 'Article tags',
          nextTitle: 'Useful next steps',
          nextItems: [
            'If the article affects how you price or present an item, update your next listing with cleaner photos, stronger condition notes and clearer shipping terms.',
            'For higher-value pieces, keep proof of authenticity, close-up details and packaging notes ready before the listing goes public.',
          ],
          createListing: 'Create listing',
          verify: 'Complete verification',
          relatedEyebrow: 'Related Reading',
          relatedTitle: 'Continue on the same subject',
          relatedDescription: 'More reading around the same collector topic.',
        }
      : {
          back: 'Πίσω στο blog',
          intro: 'Εισαγωγή άρθρου',
          source: 'Επίσημη πηγή',
          sourceButton: 'Άνοιγμα πηγής',
          tags: 'Θέματα άρθρου',
          nextTitle: 'Χρήσιμα επόμενα βήματα',
          nextItems: [
            'Πριν δημοσιεύσεις μια αγγελία, πρόσθεσε καθαρές φωτογραφίες, συγκεκριμένη περιγραφή κατάστασης και τις διαθέσιμες επιλογές αποστολής.',
            'Κράτησε τα στοιχεία έκδοσης, τυχόν πιστοποίηση και κοντινές φωτογραφίες διαθέσιμα, ώστε ο αγοραστής να μπορεί να ελέγξει το συγκεκριμένο αντικείμενο.',
          ],
          createListing: 'Δημιούργησε αγγελία',
          verify: 'Ολοκλήρωσε επαλήθευση',
          relatedEyebrow: 'Σχετική ανάγνωση',
          relatedTitle: 'Συνέχισε στο ίδιο θέμα',
          relatedDescription: 'Περισσότερα άρθρα γύρω από το ίδιο συλλεκτικό ενδιαφέρον.',
        }

  if (!post) {
    return isReady ? <NotFoundPage /> : null
  }

  const relatedPosts = getRelatedBlogPosts(post.slug, post.category)

  return (
    <div className="container pb-16">
      <PageSeo fallbackTitle={`${post.title} | Cardora`} fallbackDescription={post.excerpt || post.intro?.slice(0, 180)} type="article" image={post.coverImage} />
      <Button as={Link} to={localizePath('/blog', locale)} variant="ghost" className="mb-5">
        <ArrowLeft className="h-4 w-4" />
        {copy.back}
      </Button>

      <CardSurface className="overflow-hidden p-0">
        <BlogEditorialCover post={post} variant="hero" as="h1" />
      </CardSurface>

      <div className="mt-12 grid gap-8 xl:grid-cols-[1.15fr,0.85fr]">
        <article className="space-y-6">
          {post.intro ? (
            <CardSurface>
              <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.intro}</p>
              <p className="mt-4 text-sm leading-8 text-white/82">{post.intro}</p>
            </CardSurface>
          ) : null}

          {post.sections.map((section, index) => (
            <CardSurface key={section.title} id={post.editorial ? `guide-section-${index + 1}` : undefined} className="scroll-mt-28">
              <h2 className="font-display text-4xl text-white">{section.title}</h2>
              <div className="mt-4 space-y-4">
                {section.paragraphs?.map((paragraph) => (
                  <p key={paragraph} className="text-sm leading-8 text-white/82">
                    {paragraph}
                  </p>
                ))}
                {section.bullets?.length ? (
                  <div className="space-y-3">
                    {section.bullets.map((bullet) => (
                      <div
                        key={bullet}
                        className="rounded-2xl border border-white/8 bg-white/5 px-4 py-3 text-sm leading-7 text-white/78"
                      >
                        {bullet}
                      </div>
                    ))}
                  </div>
                ) : null}
              </div>
              {section.sources?.length ? <p className="mt-4 text-xs leading-6 text-mist">
                {locale === 'en' ? 'Official references: ' : 'Επίσημες πηγές: '}
                {section.sources.map((sourceIndex, position) => <span key={sourceIndex}>{position ? ' · ' : ''}<a href={post.sources[sourceIndex].url} target="_blank" rel="noreferrer" className="underline">{post.sources[sourceIndex].title}</a></span>)}
              </p> : null}
            </CardSurface>
          ))}
        </article>

        <div className="space-y-6">
          {post.editorial ? <CardSurface>
            <h2 className="font-display text-3xl text-ink">{locale === 'en' ? 'In this guide' : 'Σε αυτόν τον οδηγό'}</h2>
            <ol className="mt-4 space-y-3 text-sm text-mist">{post.sections.map((section, index) => <li key={section.title}><a className="underline" href={`#guide-section-${index + 1}`}>{section.title}</a></li>)}</ol>
            <p className="mt-5 text-sm leading-7 text-mist">{locale === 'en' ? 'Practical guidance from Cardora. Official sources are linked beside the relevant sections.' : 'Πρακτικός οδηγός της Cardora. Οι επίσημες πηγές συνδέονται δίπλα στις σχετικές ενότητες.'}</p>
          </CardSurface> : null}
          {post.source ? (
            <CardSurface>
              <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.source}</p>
              <h2 className="mt-4 font-display text-3xl text-white">{post.source.title || post.source.publisher}</h2>
              <div className="mt-3 space-y-2 text-sm leading-7 text-white/78">
                {post.source.publisher ? <p>{post.source.publisher}</p> : null}
                {post.source.publishedAt ? <p>{formatDate(post.source.publishedAt)}</p> : null}
                {post.source.note ? <p>{post.source.note}</p> : null}
              </div>
              {post.source.url ? (
                <Button as="a" href={post.source.url} target="_blank" rel="noreferrer" className="mt-5">
                  {copy.sourceButton}
                  <ExternalLink className="h-4 w-4" />
                </Button>
              ) : null}
            </CardSurface>
          ) : null}

          {post.tags?.length ? (
            <CardSurface>
              <h2 className="font-display text-3xl text-white">{copy.tags}</h2>
              <div className="mt-4 flex flex-wrap gap-2">
                {post.tags.map((tag) => (
                  <Badge key={tag} tone="muted">
                    {tag}
                  </Badge>
                ))}
              </div>
            </CardSurface>
          ) : null}

          <CardSurface>
            <h2 className="font-display text-3xl text-white">{copy.nextTitle}</h2>
            <div className="mt-4 space-y-3">
              {copy.nextItems.map((item) => (
                <div key={item} className="rounded-2xl border border-white/8 bg-white/5 px-4 py-3 text-sm leading-7 text-white/78">
                  {item}
                </div>
              ))}
            </div>
            <div className="mt-5 flex flex-wrap gap-3">
              <Button as={Link} to={localizePath('/dimiourgia-aggelias', locale)}>
                {copy.createListing}
              </Button>
              <Button as={Link} to={localizePath('/epalithefsi-logariasmou', locale)} variant="secondary">
                {copy.verify}
              </Button>
            </div>
            {post.relatedPaths?.length ? <ul className="mt-5 space-y-3 text-sm text-mist">{post.relatedPaths.map(path => <li key={path}><Link className="underline" to={localizePath(path, locale)}>{({
              '/kartes': locale === 'en' ? 'Browse cards' : 'Δες τις κάρτες',
              '/katastasi-kai-grading': locale === 'en' ? 'Condition and grading glossary' : 'Οδηγός κατάστασης και grading',
              '/odigos-polisis': locale === 'en' ? 'Selling guide and fees' : 'Οδηγός πώλησης και χρεώσεις',
              '/epistrofes-kai-diafores': locale === 'en' ? 'Refunds and disputes' : 'Επιστροφές και διαφορές',
            })[path]}</Link></li>)}</ul> : null}
          </CardSurface>
        </div>
      </div>

      {relatedPosts.length ? (
        <section className="mt-16">
          <SectionHeader
            eyebrow={copy.relatedEyebrow}
            title={copy.relatedTitle}
            description={copy.relatedDescription}
          />
          <div className="grid gap-5 lg:grid-cols-3">
            {relatedPosts.map((item) => (
              <BlogCard key={item.id} post={item} />
            ))}
          </div>
        </section>
      ) : null}
    </div>
  )
}

export default BlogArticlePage
