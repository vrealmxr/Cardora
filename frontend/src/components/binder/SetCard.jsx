import { ChevronRight } from 'lucide-react'
import { Link } from 'react-router-dom'
import CompletionBar from '@/components/binder/CompletionBar'
import PlaceholderSetArt from '@/components/binder/PlaceholderSetArt'

function SetCard({ set, to, completion, actionLabel }) {
  return (
    <Link
      to={to}
      className="group flex flex-col overflow-hidden rounded-[20px] border border-[#ead9b1] bg-white p-3 shadow-glass transition duration-200 hover:-translate-y-1 hover:border-[#d8b06a] hover:shadow-gold"
    >
      <PlaceholderSetArt category={set.theme} name={set.name} className="aspect-[4/3] w-full" />

      <div className="mt-3 flex items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="truncate text-[10px] font-bold uppercase tracking-wider text-[#9d6a17]">
            {set.brandLabel} · {set.year}
          </p>
          <p className="mt-0.5 text-xs text-slate-500">{set.totalCards} κάρτες</p>
        </div>
        <ChevronRight className="mt-1 h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-[#9d6a17]" />
      </div>

      {completion ? (
        <div className="mt-3">
          <CompletionBar percent={completion.percent} size="sm" />
          <p className="mt-1.5 text-[11px] text-slate-500">
            {completion.owned}/{completion.total} κάρτες · €{completion.value.toFixed(2)}
          </p>
        </div>
      ) : actionLabel ? (
        <span className="mt-3 inline-flex w-fit items-center rounded-full border border-[#eadab7] bg-[#fff8ec] px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-[#9d6a17]">
          {actionLabel}
        </span>
      ) : null}
    </Link>
  )
}

export default SetCard
