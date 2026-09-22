import { Layers } from 'lucide-react'
import { Link } from 'react-router-dom'

function formatDate(isoDate, locale) {
  if (!isoDate) return null
  try {
    return new Date(isoDate).toLocaleDateString(locale === 'en' ? 'en-US' : 'el-GR', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    })
  } catch {
    return null
  }
}

function RealSetCard({ set, to, locale, cardsLabel }) {
  const releaseDate = formatDate(set.releasedAt, locale)

  return (
    <Link
      to={to}
      className="group flex flex-col overflow-hidden rounded-xl border border-[#eee2c4] bg-white p-3 transition hover:border-[#c79d62]"
    >
      <div className="relative flex aspect-[4/3] w-full items-center justify-center overflow-hidden rounded-lg border border-[#f0e6c8] bg-[#fbf6e9] px-3 text-center">
        {releaseDate ? (
          <span className="absolute right-2 top-2 text-[9px] font-medium uppercase tracking-wide text-slate-400">
            {releaseDate}
          </span>
        ) : null}
        <p className="font-display text-base font-semibold leading-tight text-[#6b4718]">{set.name}</p>
      </div>

      <p className="mt-2.5 truncate text-xs font-semibold text-ink group-hover:text-[#6b4718]">{set.name}</p>
      <p className="mt-0.5 flex items-center gap-1 text-[11px] text-slate-400">
        <Layers className="h-3 w-3" />
        {set.cardCount} {cardsLabel}
      </p>
    </Link>
  )
}

export default RealSetCard
