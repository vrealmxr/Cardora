// A single column in the portfolio stat strip — plain number over a quiet
// label, no icon badge. Meant to sit inside a shared bordered row, not to be
// its own card.
function StatTile({ label, value, hint }) {
  return (
    <div className="px-4 py-4 first:pl-0 sm:px-5">
      <p className="font-display text-2xl font-semibold text-ink sm:text-[1.75rem]">{value}</p>
      <p className="mt-1 text-xs font-medium text-slate-500">{label}</p>
      {hint ? <p className="mt-0.5 text-[11px] text-slate-400">{hint}</p> : null}
    </div>
  )
}

export default StatTile
