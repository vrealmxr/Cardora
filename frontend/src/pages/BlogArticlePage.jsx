import { ArrowLeft, ExternalLink } from 'lucide-react'
import { Link, Navigate, useParams } from 'react-router-dom'
import BlogCard from '@/components/blog/BlogCard'
import BlogEditorialCover from '@/components/blog/BlogEditorialCover'
import Badge from '@/components/ui/Badge'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import SectionHeader from '@/components/ui/SectionHeader'
import { useI18n } from '@/hooks/useI18n'
import { useMarketplace } from '@/hooks/useMarketplace'
import { formatDate } from '@/utils/formatters'

function BlogArticlePage() {
  const { locale } = useI18n()
  const { slug } = useParams()
  const { getBlogPostBySlug, getRelatedBlogPosts } = useMarketplace()
  const post = getBlogPostBySlug(slug)

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
          tags: 'Tags άρθρου',
          nextTitle: 'Χρήσιμα επόμενα βήματα',
          nextItems: [
            'Αν το άρθρο αλλάζει το πώς σκέφτεσαι για τιμή ή παρουσίαση, ανανέωσε το επόμενο listing σου με καθαρότερες φωτογραφίες, πιο σαφές condition και σωστούς όρους αποστολής.',
            'Για κομμάτια υψηλότερης αξίας, κράτα έτοιμα στοιχεία αυθεντικότητας, close-up φωτογραφίες και καθαρές σημειώσεις συσκευασίας πριν το κομμάτι βγει δημόσια.',
          ],
          createListing: 'Δημιούργησε αγγελία',
          verify: 'Ολοκλήρωσε επαλήθευση',
          relatedEyebrow: 'Σχετική ανάγνωση',
          relatedTitle: 'Συνέχισε στο ίδιο θέμα',
          relatedDescription: 'Περισσότερα άρθρα γύρω από το ίδιο συλλεκτικό ενδιαφέρον.',
        }

  if (!post) {
    return <Navigate to="/blog" replace />
  }

  const relatedPosts = getRelatedBlogPosts(post.slug, post.category)

  return (
    <div className="container pb-16">
      <Button as={Link} to="/blog" variant="ghost" className="mb-5">
        <ArrowLeft className="h-4 w-4" />
        {copy.back}
      </Button>

      <CardSurface className="overflow-hidden p-0">
        <BlogEditorialCover post={post} variant="hero" />
      </CardSurface>

      <div className="mt-12 grid gap-8 xl:grid-cols-[1.15fr,0.85fr]">
        <article className="space-y-6">
          {post.intro ? (
            <CardSurface>
              <p className="text-[11px] uppercase tracking-[0.28em] text-gold-100">{copy.intro}</p>
              <p className="mt-4 text-sm leading-8 text-white/82">{post.intro}</p>
            </CardSurface>
          ) : null}

          {post.sections.map((section) => (
            <CardSurface key={section.title}>
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
            </CardSurface>
          ))}
        </article>

        <div className="space-y-6">
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
              <Button as={Link} to="/dimiourgia-aggelias">
                {copy.createListing}
              </Button>
              <Button as={Link} to="/epalithefsi-logariasmou" variant="secondary">
                {copy.verify}
              </Button>
            </div>
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
