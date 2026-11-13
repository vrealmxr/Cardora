import { ImageOff, List, ChevronDown } from 'lucide-react'
import { useId, useState } from 'react'
import Button from '@/components/ui/Button'
import { useI18n } from '@/hooks/useI18n'
import { formatCurrency } from '@/utils/formatters'

function ItemPhoto({ item, emptyLabel }) {
  const [selected, setSelected] = useState(0)
  const [failed, setFailed] = useState(false)
  const media = item.media ?? []
  const image = media[selected]
  return <div className="w-full shrink-0 sm:w-36">
    {image && !failed ? <img src={image.url} alt={item.title || ''} loading="lazy" onError={() => setFailed(true)}
      className="h-48 w-full rounded-xl border border-white/8 bg-white object-contain" />
      : <div className="flex h-40 flex-col items-center justify-center gap-2 rounded-xl border border-white/8 text-mist"><ImageOff className="h-7 w-7" /><span className="text-xs">{emptyLabel}</span></div>}
    {media.length > 1 ? <div className="mt-2 flex flex-wrap gap-2">{media.map((photo, index) =>
      <button key={photo.id ?? index} type="button" onClick={() => { setSelected(index); setFailed(false) }}
        aria-label={`${item.title || ''} — ${index + 1}`} aria-pressed={selected === index}
        className={`rounded-lg border p-1 ${selected === index ? 'border-gold-300' : 'border-white/8'}`}>
        <img src={photo.thumbUrl || photo.url} alt="" loading="lazy" className="h-10 w-9 object-contain" />
      </button>)}</div> : null}
  </div>
}

export default function OrderItems({ order }) {
  const { locale } = useI18n()
  const en = locale === 'en'
  const [open, setOpen] = useState(false)
  const regionId = useId()
  const items = Array.isArray(order.items) ? order.items : []
  const subtotal = items.reduce((sum, item) => sum + Number(item.lineTotal ?? Number(item.unitPrice) * Number(item.quantity)), 0)
  const shipping = Number(order.shipping ?? 0)
  const total = Number(order.totalAmount ?? order.total ?? 0)
  const fees = Math.max(0, Math.round((total - subtotal - shipping) * 100) / 100)
  const title = en ? 'Order items' : 'Λίστα παραγγελίας'
  return <section className="rounded-2xl border border-white/8 bg-white/5 p-4">
    <Button variant="secondary" onClick={() => setOpen(value => !value)} aria-expanded={open} aria-controls={regionId}>
      <List className="h-4 w-4" />{title}<ChevronDown className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`} />
    </Button>
    {open ? <div id={regionId} role="region" aria-label={title} className="mt-4 space-y-4">
      {items.length ? items.map(item => <article key={item.id} className="flex flex-col gap-5 rounded-2xl border border-white/8 bg-white p-4 sm:flex-row">
        <ItemPhoto item={item} emptyLabel={en ? 'Photo unavailable' : 'Δεν υπάρχει φωτογραφία'} />
        <div className="min-w-0 flex-1">
          <h4 className="text-lg font-semibold text-white">{item.title || (en ? 'Order item' : 'Προϊόν παραγγελίας')}</h4>
          {item.subtitle ? <p className="mt-1 text-sm text-mist">{item.subtitle}</p> : null}
          <dl className="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            {[
              [en ? 'Quantity' : 'Ποσότητα', item.quantity],
              [en ? 'Unit price' : 'Τιμή τεμαχίου', formatCurrency(item.unitPrice)],
              [en ? 'Item total' : 'Σύνολο προϊόντος', formatCurrency(item.lineTotal)],
              [en ? 'Condition' : 'Κατάσταση', item.condition],
              [en ? 'Collection' : 'Συλλογή', item.franchise],
              [en ? 'Brand' : 'Κατασκευαστής', item.brand],
              [en ? 'Year' : 'Έτος', item.year],
              [en ? 'Language' : 'Γλώσσα', item.language],
              [en ? 'Set / edition' : 'Σετ / έκδοση', item.setName],
              [en ? 'Number' : 'Αριθμός', item.itemNumber],
            ].filter(([, value]) => value != null && value !== '').map(([label, value]) => <div key={label}><dt className="text-mist">{label}</dt><dd className="font-semibold text-white">{value}</dd></div>)}
          </dl>
          {item.description ? <p className="mt-4 whitespace-pre-line break-words text-sm leading-6 text-mist">{item.description}</p> : null}
        </div>
      </article>) : <p className="text-sm text-mist">{en ? 'Item details are currently unavailable.' : 'Οι λεπτομέρειες των προϊόντων δεν είναι διαθέσιμες αυτή τη στιγμή.'}</p>}
      {items.length ? <dl className="ml-auto grid max-w-sm grid-cols-2 gap-x-5 gap-y-2 text-sm">
        <dt className="text-mist">{en ? 'Items' : 'Προϊόντα'}</dt><dd className="text-right">{formatCurrency(subtotal)}</dd>
        <dt className="text-mist">{en ? 'Shipping' : 'Μεταφορικά'}</dt><dd className="text-right">{formatCurrency(shipping)}</dd>
        {fees > 0 ? <><dt className="text-mist">{en ? 'Fees' : 'Χρεώσεις'}</dt><dd className="text-right">{formatCurrency(fees)}</dd></> : null}
        <dt className="font-semibold">{en ? 'Order total' : 'Σύνολο παραγγελίας'}</dt><dd className="text-right font-semibold">{formatCurrency(total)}</dd>
      </dl> : null}
    </div> : null}
  </section>
}
