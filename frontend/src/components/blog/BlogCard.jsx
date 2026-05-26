import { ArrowUpRight } from 'lucide-react'
import { Link } from 'react-router-dom'
import BlogEditorialCover from '@/components/blog/BlogEditorialCover'
import Badge from '@/components/ui/Badge'
import CardSurface from '@/components/ui/CardSurface'
import { formatDate } from '@/utils/formatters'
import { cn } from '@/utils/helpers'

function BlogCard({ post, featured = false, className }) {
  return (
    <CardSurface className={cn('group h-full overflow-hidden p-0', className)}>
      <Link to={`/blog/${post.slug}`} className="block h-full">
        <div className="space-y-4">
          <BlogEditorialCover post={post} variant={featured ? 'hero' : 'card'} />
          <div className="px-1">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="flex flex-wrap items-center gap-2">
                <Badge tone="gold">{post.category}</Badge>
                <span className="text-[10px] font-semibold uppercase tracking-[0.32em] text-white/55">
                  {post.visual.label}
                </span>
              </div>
              <div className="flex items-center gap-4 text-sm text-white/72">
                <span>{formatDate(post.publishedAt)}</span>
                <ArrowUpRight className="h-4 w-4 text-gold-100 transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
              </div>
            </div>
          </div>
        </div>
      </Link>
    </CardSurface>
  )
}

export default BlogCard
