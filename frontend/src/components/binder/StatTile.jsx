function StatTile({ icon: Icon, label, value, hint }) {
  return (
    <div className="rounded-[20px] border border-[#ead9b1] bg-white p-4 shadow-glass sm:p-5">
      <div className="mb-3 inline-flex h-9 w-9 items-center justify-center rounded-xl border border-[#eadab7] bg-[#fff8ec] text-[#9d6a17]">
        <Icon className="h-4.5 w-4.5" />
      </div>
      <p className="font-display text-2xl font-semibold text-ink sm:text-3xl">{value}</p>
      <p className="mt-1 text-xs font-medium text-slate-600">{label}</p>
      {hint ? <p className="mt-2 text-[11px] text-slate-400">{hint}</p> : null}
    </div>
  )
}

export default StatTile
