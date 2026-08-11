import { ChevronRight } from 'lucide-react'
import { Link } from 'react-router-dom'
import CompletionBar from '@/components/binder/CompletionBar'
import PlaceholderSetArt from '@/components/binder/PlaceholderSetArt'

function SetCard({ set, to, completion, actionLabel }) {
  return (
    <Link
      to={to}
      className="group relative flex flex-col overflow-hidden rounded-[22px] border border-white/10 bg-white/[0.04] p-3 transition duration-200 hover:-translate-y-1 hover:border-[#f3d385]/30 hover:bg-white/[0.06] hover:shadow-[0_18px_36px_rgba(0,0,0,0.35)]"
    >
      <PlaceholderSetArt category={set.theme} name={set.name} className="aspect-[4/3] w-full" />

      <div className="mt-3 flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="truncate text-[10px] font-bold uppercase tracking-wider text-[#f3d385]/80">
            {set.brandLabel} · {set.year}
          </p>
          <p className="mt-0.5 text-xs text-white/45">{set.totalCards} κάρτες</p>
        </div>
        <ChevronRight className="mt-1 h-4 w-4 shrink-0 text-white/30 transition group-hover:translate-x-0.5 group-hover:text-[#f3d385]" />
      </div>

      {completion ? (
        <div className="mt-3">
          <CompletionBar percent={completion.percent} size="sm" />
          <p className="mt-1.5 text-[11px] text-white/50">
            {completion.owned}/{completion.total} κάρτες · €{completion.value.toFixed(2)}
          </p>
        </div>
      ) : actionLabel ? (
        <span className="mt-3 inline-flex w-fit items-center rounded-full border border-[#f3d385]/30 bg-[#f3d385]/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-[#f3d385]">
          {actionLabel}
        </span>
      ) : null}
    </Link>
  )
}

export default SetCard
