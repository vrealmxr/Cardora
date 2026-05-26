import {
  BadgeDollarSign,
  BookOpenText,
  Clock3,
  Gem,
  Newspaper,
  ShieldCheck,
  Sparkles,
} from 'lucide-react'
import Badge from '@/components/ui/Badge'
import { formatDate } from '@/utils/formatters'
import { cn } from '@/utils/helpers'

const THEMES = [
  { match: 'card', icon: BadgeDollarSign, kicker: 'Market focus' },
  { match: 'comic', icon: BookOpenText, kicker: 'Reading room' },
  { match: 'book', icon: BookOpenText, kicker: 'Reading room' },
  { match: 'figure', icon: Gem, kicker: 'Collector shelf' },
  { match: 'guide', icon: ShieldCheck, kicker: 'Cardora guide' },
  { match: 'news', icon: Newspaper, kicker: 'Collector news' },
]

function resolveTheme(post) {
  const value = `${post.category} ${post.visual?.label ?? ''} ${post.source?.publisher ?? ''}`.toLowerCase()
  return THEMES.find((theme) => value.includes(theme.match)) ?? { icon: Sparkles, kicker: 'Cardora editorial' }
}

function BlogEditorialCover({ post, variant = 'card', className }) {
  const theme = resolveTheme(post)
  const Icon = theme.icon
  const isHero = variant === 'hero'
  const sourceLabel = post.source?.publisher || post.visual?.label || 'Cardora'
  const tags = post.tags?.slice(0, isHero ? 4 : 3) ?? []

  return (
    <div
      className={cn(
        'relative overflow-hidden rounded-[28px] border border-white/10',
        isHero ? 'min-h-[420px]' : 'min-h-[280px]',
        className,
      )}
    >
      <div className={cn('absolute inset-0 bg-gradient-to-br opacity-95', post.visual.gradient)} />
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.12),transparent_28%),radial-gradient(circle_at_bottom_left,rgba(243,202,87,0.20),transparent_34%),linear-gradient(135deg,rgba(255,255,255,0.02),transparent_52%)]" />
      <div className="absolute inset-0 opacity-40 [background-image:linear-gradient(rgba(255,255,255,0.04)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.04)_1px,transparent_1px)] [background-size:32px_32px]" />
      <div className="absolute inset-x-5 top-5 h-20 rounded-[22px] bg-gradient-to-r from-gold-200/30 via-white/6 to-transparent blur-2xl" />

      <div className="relative flex h-full flex-col justify-between p-5 sm:p-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <Badge tone="gold">{post.category}</Badge>
            <Badge tone="muted">{theme.kicker}</Badge>
          </div>
          <div className="inline-flex items-center gap-2 rounded-full border border-white/12 bg-[#091425]/75 px-3 py-1.5 text-[11px] uppercase tracking-[0.24em] text-white/66">
            <Icon className="h-3.5 w-3.5 text-gold-100" />
            {sourceLabel}
          </div>
        </div>

        <div className={cn('mt-8 grid gap-6', isHero ? 'lg:grid-cols-[1.15fr,0.85fr]' : '')}>
          <div>
            <div className="inline-flex items-center gap-2 rounded-full border border-gold-200/20 bg-gold-200/10 px-3 py-1.5 text-[11px] uppercase tracking-[0.24em] text-gold-50">
              <Icon className="h-3.5 w-3.5" />
              {post.visual?.label || 'Cardora'}
            </div>
            <h2
              className={cn(
                'mt-4 max-w-4xl text-balance font-display text-white',
                isHero ? 'text-5xl sm:text-6xl' : 'text-[1.95rem] leading-tight',
              )}
            >
              {post.title}
            </h2>
            <p className={cn('mt-4 max-w-3xl text-white/80', isHero ? 'text-sm leading-8' : 'text-sm leading-7')}>
              {post.excerpt}
            </p>
          </div>

          <div className="space-y-3">
            <div className="rounded-[24px] border border-white/10 bg-[#091425]/72 p-4">
              <p className="text-[11px] uppercase tracking-[0.24em] text-white/44">Published</p>
              <p className="mt-2 text-xl font-semibold text-white">{formatDate(post.publishedAt)}</p>
            </div>
            <div className="rounded-[24px] border border-white/10 bg-[#091425]/72 p-4">
              <p className="text-[11px] uppercase tracking-[0.24em] text-white/44">Reading time</p>
              <div className="mt-2 inline-flex items-center gap-2 text-xl font-semibold text-white">
                <Clock3 className="h-4 w-4 text-gold-100" />
                {post.readTime}
              </div>
            </div>
            {post.author?.name ? (
              <div className="rounded-[24px] border border-white/10 bg-[#091425]/72 p-4">
                <p className="text-[11px] uppercase tracking-[0.24em] text-white/44">By</p>
                <p className="mt-2 text-lg font-semibold text-white">{post.author.name}</p>
                <p className="mt-1 text-sm text-white/60">{post.author.role}</p>
              </div>
            ) : null}
          </div>
        </div>

        {tags.length ? (
          <div className="mt-8 flex flex-wrap gap-2">
            {tags.map((tag) => (
              <span
                key={tag}
                className="inline-flex items-center rounded-full border border-white/12 bg-white/6 px-3 py-1.5 text-xs text-white/76"
              >
                {tag}
              </span>
            ))}
          </div>
        ) : null}
      </div>
    </div>
  )
}

export default BlogEditorialCover
