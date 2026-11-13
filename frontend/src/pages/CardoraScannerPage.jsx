import { Camera, ExternalLink, ImagePlus, Loader2, RefreshCw, Search, Trash2 } from 'lucide-react'
import { useCallback, useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import BinderShell from '@/components/binder/BinderShell'
import Button from '@/components/ui/Button'
import CardSurface from '@/components/ui/CardSurface'
import { Select } from '@/components/ui/Input'
import { useAuth } from '@/hooks/useAuth'
import { useI18n } from '@/hooks/useI18n'
import { apiClient } from '@/services/apiClient'
import { prepareCardPhoto } from '@/components/scanner/autoCrop'
import { localizePath } from '@/utils/helpers'

const isPending = (scan) => ['queued', 'processing'].includes(scan?.state)

function PhotoField({ role, label, file, required, disabled, onChange, english }) {
  const input = useRef(null)
  const [preview, setPreview] = useState(null)
  useEffect(() => {
    if (!file) { setPreview(null); return }
    const url = URL.createObjectURL(file)
    setPreview(url)
    return () => URL.revokeObjectURL(url)
  }, [file])
  return (
    <div>
      <p id={'label-' + role} className="mb-2 text-xs font-semibold text-ink">{label}{required ? ' *' : english ? ' (optional)' : ' (προαιρετική)'}</p>
      <input ref={input} type="file" className="sr-only" accept="image/jpeg,image/png,image/webp"
        aria-labelledby={'label-' + role} disabled={disabled}
        onChange={event => onChange(event.target.files?.[0] || null)} />
      <button type="button" disabled={disabled} onClick={() => input.current?.click()}
        onDragOver={event => event.preventDefault()}
        onDrop={event => { event.preventDefault(); if (!disabled) onChange(event.dataTransfer.files?.[0] || null) }}
        className="flex min-h-48 w-full flex-col items-center justify-center gap-3 rounded-[18px] border-2 border-dashed border-[#eadab7] p-4 text-slate-500 transition hover:border-[#d8b06a] hover:text-[#6b4718] disabled:cursor-not-allowed disabled:opacity-60">
        {preview ? <img src={preview} alt={label} className="h-36 max-w-full rounded-[14px] object-contain" /> : <ImagePlus className="h-8 w-8" />}
        <span className="max-w-full truncate text-xs">{file ? (english ? 'Photo ready' : 'Η φωτογραφία είναι έτοιμη') : (english ? 'Drop or select a photo' : 'Σύρε ή επίλεξε μια φωτογραφία')}</span>
      </button>
      {file && <button type="button" disabled={disabled} className="mt-2 text-xs text-slate-500 underline"
        onClick={() => { onChange(null); if (input.current) input.current.value = '' }}>
        {english ? 'Remove photo' : 'Αφαίρεση φωτογραφίας'}
      </button>}
    </div>
  )
}

function formatMoney(value, currency, english) {
  if (value === null || value === undefined) return '—'
  try {
    return new Intl.NumberFormat(english ? 'en-IE' : 'el-GR', { style: 'currency', currency: currency || 'EUR' }).format(value)
  } catch {
    return `${Number(value).toFixed(2)} ${currency || ''}`.trim()
  }
}

// eBay estimate the backend attaches to a completed scan (result.market_price).
function MarketPrice({ price, english }) {
  const t = (el, en) => english ? en : el
  if (!price?.estimated_sale) {
    return <p className="mt-2 rounded-xl border border-[#eadab7] bg-[#fffaf0] px-4 py-3 text-sm text-slate-600">
      {t('Δεν βρέθηκαν αρκετές αγγελίες στο eBay για να εκτιμηθεί τιμή για αυτή την κάρτα.', 'Not enough eBay listings were found to estimate a price for this card.')}
    </p>
  }
  const estimate = price.estimated_sale
  const money = value => formatMoney(value, price.currency, english)
  const confidence = {
    high: [t('Υψηλή αξιοπιστία', 'High confidence'), 'text-emerald-700'],
    medium: [t('Μέτρια αξιοπιστία', 'Medium confidence'), 'text-[#9d6a17]'],
    low: [t('Χαμηλή αξιοπιστία', 'Low confidence'), 'text-rose-700'],
  }[estimate.confidence] || [estimate.confidence, 'text-slate-500']
  return (
    <div className="mt-2 rounded-[18px] border border-[#eadab7] bg-[#fff8ec] p-4">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-[10px] font-semibold uppercase tracking-wide text-[#9d6a17]/80">{t('Εκτιμώμενη τιμή πώλησης', 'Estimated sale price')}</p>
          <p className="font-display text-3xl font-bold text-[#6b4718]">{money(estimate.price)}</p>
          <p className={`mt-0.5 text-xs font-medium ${confidence[1]}`}>{confidence[0]}</p>
        </div>
        {price.median_asking !== null && price.median_asking !== undefined && <div className="text-right text-xs text-slate-500">
          <p>{t('Διάμεσος ζητούμενων τιμών', 'Median asking price')}</p>
          <p className="font-display text-lg font-semibold text-[#9d6a17]">{money(price.median_asking)}</p>
        </div>}
      </div>
      <p className="mt-3 text-[11px] leading-5 text-slate-500">
        {t(`Με βάση ${price.used_for_stats} ενεργές αγγελίες στο eBay παγκοσμίως`, `Based on ${price.used_for_stats} active eBay listings worldwide`)}
        {price.condition_filter === 'graded' ? t(' (βαθμολογημένες κάρτες)', ' (graded cards)') : t(' (χωρίς βαθμολογημένες κάρτες)', ' (graded slabs excluded)')}
        {' · '}{t('αναζήτηση', 'search')}: “{price.query}”
      </p>
      {price.match === 'name_only' && <p className="mt-1 text-[11px] text-[#9d6a17]">{t(
        'Η αναζήτηση έγινε μόνο με το όνομα της κάρτας, οπότε η τιμή μπορεί να περιλαμβάνει και άλλες εκδόσεις της.',
        'Searched by card name only, so the price may include other printings of this card.',
      )}</p>}
      {price.items?.length > 0 && <ul className="mt-3 grid gap-2 sm:grid-cols-3">
        {price.items.slice(0, 3).map((item, index) => <li key={item.url || index}>
          <a href={item.url} target="_blank" rel="noopener noreferrer nofollow"
            className="flex h-full items-center gap-2 rounded-xl border border-[#ead9b1] bg-white p-2 text-xs text-slate-600 transition hover:border-[#d8b06a]">
            {item.image_url && <img src={item.image_url} alt="" loading="lazy" className="h-12 w-9 shrink-0 rounded object-cover" />}
            <span className="min-w-0 flex-1">
              <span className="line-clamp-2">{item.title}</span>
              <span className="mt-0.5 flex items-center gap-1 font-semibold text-[#9d6a17]">{money(item.price)} <ExternalLink className="h-3 w-3" /></span>
            </span>
          </a>
        </li>)}
      </ul>}
      <p className="mt-3 text-[10px] leading-4 text-slate-400">{t(
        'Εκτίμηση από τη φθηνότερη πλευρά των τρεχουσών τιμών στο eBay, όπου πουλιούνται συνήθως οι κάρτες, σε ευρώ. Λιγότερο αξιόπιστη για πολύ φθηνές και vintage κάρτες. Δεν είναι εγγυημένη τιμή πώλησης.',
        'Estimated from the cheaper end of current eBay asking prices, where cards usually sell, in euro. Less reliable for very cheap and vintage cards. Not a guaranteed sale price.',
      )}</p>
    </div>
  )
}

export default function CardoraScannerPage() {
  const { locale } = useI18n()
  const { isAuthenticated, isAuthReady } = useAuth()
  const english = locale === 'en'
  const t = (el, en) => english ? en : el
  const [config, setConfig] = useState(null)
  const [category, setCategory] = useState('pokemon')
  const [files, setFiles] = useState({})
  const [preparing, setPreparing] = useState(false)
  const [cropped, setCropped] = useState({})
  const preparation = useRef(0)
  const [scan, setScan] = useState(null)
  const [recent, setRecent] = useState([])
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const requestKey = useRef(null)
  const requiresBack = ['starwars', 'nba', 'soccer', 'euroleague'].includes(category)
  const pending = isPending(scan)
  const locked = busy || pending || preparing || !isAuthenticated
  const updateScan = useCallback(row => {
    setScan(row)
    setRecent(old => [row, ...old.filter(item => item.id !== row.id)].slice(0, 50))
  }, [])

  useEffect(() => {
    document.title = 'Cardora Scanner'
    let active = true
    apiClient.get('/card-scans/config').then(value => { if (active) setConfig(value) }).catch(e => { if (active) setError(e.message) })
    return () => { active = false }
  }, [])

  useEffect(() => {
    let active = true
    if (isAuthenticated) {
      apiClient.get('/card-scans/mine').then(rows => {
        if (!active) return
        setRecent(rows)
        setScan(current => current || rows.find(isPending) || null)
      }).catch(e => { if (active) setError(e.message) })
    } else { setRecent([]); setScan(null); setFiles({}); setCropped({}); preparation.current++; setPreparing(false) }
    return () => { active = false }
  }, [isAuthenticated])

  useEffect(() => {
    if (!pending) return
    let active = true
    let timer
    let attempts = 0
    const poll = async () => {
      let finished = false
      try {
        const row = await apiClient.get('/card-scans/' + scan.id)
        if (active) { updateScan(row); setError(''); finished = !isPending(row) }
      } catch (e) { if (active) setError(e.message) }
      if (active && !finished && ++attempts < 180) timer = setTimeout(poll, 5000)
      else if (active && !finished) setError(english ? 'The scan is taking longer. Use Refresh to check again.' : 'Το scan διαρκεί περισσότερο. Πάτησε Ανανέωση για νέο έλεγχο.')
    }
    timer = setTimeout(poll, 4000)
    return () => { active = false; clearTimeout(timer) }
  }, [pending, scan?.id, english, updateScan])

  useEffect(() => () => { preparation.current++ }, [])

  const chooseFile = async (role, file) => {
    if (locked) return
    if (file && (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024)) {
      setError(t('Χρειάζεται JPG, PNG ή WebP έως 10 MB.', 'Choose a JPG, PNG or WebP up to 10 MB.'))
      return
    }
    setError(''); requestKey.current = null
    setFiles(old => ({ ...old, [role]: null })); setCropped(old => ({ ...old, [role]: false }))
    if (!file) return
    const attempt = ++preparation.current
    setPreparing(true)
    try {
      const prepared = await prepareCardPhoto(file)
      if (attempt !== preparation.current) return
      setFiles(old => ({ ...old, [role]: prepared.file }))
      setCropped(old => ({ ...old, [role]: prepared.cropped }))
    } catch (e) {
      if (attempt === preparation.current) setError(e.message === 'image_size'
        ? t('Διάλεξε καθαρή φωτογραφία έως 16 megapixels και 10 MB.', 'Choose a clear photo up to 16 megapixels and 10 MB.')
        : t('Δεν μπορέσαμε να ανοίξουμε τη φωτογραφία. Δοκίμασε άλλη.', 'We could not open this photo. Try another.'))
    } finally { if (attempt === preparation.current) setPreparing(false) }
  }

  const submit = async event => {
    event.preventDefault()
    if (locked || !config?.enabled) return
    if (!files.front || (requiresBack && !files.back)) {
      setError(t('Πρόσθεσε τις υποχρεωτικές όψεις της κάρτας.', 'Add the required card views.'))
      return
    }
    setBusy(true); setError(''); setNotice('')
    requestKey.current ||= crypto.randomUUID()
    const data = new FormData()
    data.append('request_key', requestKey.current)
    data.append('category', category)
    data.append('terms_accepted', '1')
    data.append('image_preparation', Object.entries(files).filter(([, file]) => file).every(([role]) => cropped[role]) ? 'auto-card-crop-v2' : 'scan-only-v2')
    data.append('policy_version', config.policy_version)
    Object.entries(files).forEach(([role, file]) => { if (file) data.append(role, file) })
    try { updateScan(await apiClient.post('/card-scans', data)); requestKey.current = null }
    catch (e) { setError(e.message) }
    finally { setBusy(false) }
  }

  const removeScan = async () => {
    setBusy(true); setError('')
    try {
      await apiClient.delete('/card-scans/' + scan.id)
      setRecent(old => old.filter(item => item.id !== scan.id)); setScan(null)
      setNotice(t('Το scan και οι αποθηκευμένες φωτογραφίες διαγράφηκαν.', 'The scan and its saved photos were deleted.'))
    } catch (e) { setError(e.message) }
    finally { setBusy(false) }
  }
  const identity = scan?.result?.identity
  const confirmed = scan?.result?.printing_status === 'confirmed'

  return (
    <BinderShell>
      <div className="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div className="max-w-2xl">
          <p className="text-[10px] font-bold uppercase tracking-[0.28em] text-[#9d6a17]">Cardora-1 · Beta</p>
          <h2 className="mt-2 font-display text-3xl font-semibold text-ink sm:text-4xl">Cardora Scanner</h2>
          <p className="mt-2 text-sm leading-6 text-mist">{t(
            'Ποια κάρτα έχεις; Ανέβασε μια καθαρή φωτογραφία και ανακάλυψε τη συλλογή, τον αριθμό, την έκδοση και την εκτιμώμενη τιμή της.',
            'Which card do you have? Upload a clear photo to discover its set, number, edition and estimated price.',
          )}</p>
        </div>
        {config && <div className="rounded-xl border border-[#eee2c4] bg-white px-4 py-2.5 text-xs font-medium text-slate-500">
          {config.user_daily_limit} {t('αναγνωρίσεις την ημέρα', 'scans a day')}
        </div>}
      </div>

      <div className="mb-5 inline-flex flex-wrap rounded-2xl border border-[#eadab7] bg-white p-1.5 shadow-glass">
        <button type="button" disabled title={t('Οι τιμές θα προστεθούν αργότερα.', 'Prices will be added later.')}
          className="flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold text-slate-400">
          <Search className="h-3.5 w-3.5" />{t('Αναζήτηση τιμής · προσεχώς', 'Price search · coming soon')}
        </button>
        <span className="flex items-center gap-1.5 rounded-xl border border-[#d8b06a] bg-[linear-gradient(145deg,rgba(255,247,229,0.98)_0%,rgba(243,229,193,0.96)_100%)] px-4 py-2 text-xs font-semibold text-[#6b4718]">
          <Camera className="h-3.5 w-3.5" />{t('Ανέβασμα φωτογραφίας', 'Upload a photo')}
        </span>
      </div>

      {isAuthReady && !isAuthenticated && <div className="mb-5 rounded-xl border border-[#eadab7] bg-[#fff8ec] px-4 py-3 text-sm text-[#6b4718]">
        <Link className="font-semibold underline" to={localizePath('/eisodos', locale)}>{t('Συνδέσου στον λογαριασμό σου', 'Sign in to your account')}</Link>
        {t(' για να κάνεις scan και να βλέπεις τα αποτελέσματά σου.', ' to scan cards and view your results.')}
      </div>}
      {error && <p role="alert" className="mb-5 rounded-xl border border-[#eadab7] bg-[#fff8ec] px-4 py-3 text-sm text-[#6b4718]">{error}</p>}
      {notice && <p role="status" className="mb-5 text-sm text-slate-600">{notice}</p>}

      <CardSurface hover={false}>
        <form onSubmit={submit} className="space-y-5">
          <label className="block max-w-sm text-xs font-semibold text-ink">
            {t('Συλλογή', 'Collection')}
            <Select value={category} disabled={locked} onChange={event => setCategory(event.target.value)} className="mt-2">
              {Object.entries(config?.categories || { pokemon: 'Pokémon' }).map(([key, name]) => <option key={key} value={key}>{name}</option>)}
            </Select>
          </label>
          <div className="grid gap-5 sm:grid-cols-3">
            {[['front', t('Μπροστινή όψη', 'Front'), true], ['back', t('Πίσω όψη', 'Back'), requiresBack], ['angled', t('Λήψη υπό γωνία', 'Angled view'), false]].map(([role, label, required]) =>
              <PhotoField key={role} role={role} label={label} required={required} file={files[role]} disabled={locked}
                english={english} onChange={file => chooseFile(role, file)} />)}
          </div>
          <p className="text-xs leading-5 text-slate-500">{t(
            'Φρόντισε να φαίνεται ολόκληρη η κάρτα, χωρίς αντανακλάσεις. Για αθλητικές κάρτες και Star Wars, πρόσθεσε και την πίσω όψη.',
            'Show the whole card clearly, without glare. For sports and Star Wars cards, add the back too.',
          )}</p>
          <p className="text-xs leading-6 text-slate-500">
            {t('Χρησιμοποιώντας το Cardora Scanner, αποδέχεσαι τους ', 'By using Cardora Scanner, you accept our ')}
            <Link to={localizePath('/oroi-xrisis', locale) + '#cardora-scanner'} target="_blank" rel="noopener noreferrer" className="font-semibold text-[#9d6a17] underline underline-offset-2">{t('Όρους Χρήσης', 'Terms of Use')}</Link>.
            {' '}{t('Περικόπτουμε αυτόματα τις φωτογραφίες σου. Για τη βελτίωση και την εκπαίδευση του μοντέλου μας κρατάμε μόνο εικόνες στις οποίες έχει απομονωθεί η κάρτα.', 'We crop your photos automatically. To improve and train our model, we only keep images where the card has been isolated.')}
          </p>
          <Button type="submit" disabled={locked || !config?.enabled || !files.front || (requiresBack && !files.back)}>
            {busy || pending || preparing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Camera className="h-4 w-4" />}
            {preparing ? t('Ετοιμάζουμε τη φωτογραφία…', 'Preparing your photo…') : pending ? t('Η αναγνώριση εκτελείται…', 'Identifying…') : busy ? t('Αποστολή…', 'Uploading…') : t('Αναγνώριση κάρτας', 'Identify card')}
          </Button>
          {config && !config.enabled && <p className="text-xs text-slate-500">{t('Το scanner είναι προσωρινά μη διαθέσιμο.', 'The scanner is temporarily unavailable.')}</p>}
        </form>
      </CardSurface>

      {recent.length > 0 && <label className="mt-6 block max-w-xl text-xs font-semibold text-ink">
        {t('Οι πρόσφατες κάρτες μου', 'My recent cards')}
        <Select className="mt-2" value={scan?.id || ''} disabled={busy || pending} onChange={event => setScan(recent.find(row => row.id === event.target.value) || null)}>
          <option value="">{t('Νέα αναγνώριση', 'New scan')}</option>
          {recent.map(row => <option key={row.id} value={row.id}>{row.result?.identity?.name || t('Κάρτα', 'Card')} · {({ queued: t('Σε αναμονή', 'Waiting'), processing: t('Αναγνώριση…', 'Identifying…'), completed: t('Έτοιμο', 'Ready'), failed: t('Δεν ολοκληρώθηκε', 'Incomplete') })[row.state]}</option>)}
        </Select>
      </label>}

      {scan && <div className="mt-6" aria-live="polite"><CardSurface hover={false}>
        <h3 className="font-display text-2xl font-semibold text-ink">{pending ? t('Το scan σου είναι σε εξέλιξη', 'Your scan is in progress') : scan.state === 'failed' ? t('Η ανάλυση δεν ολοκληρώθηκε', 'Analysis did not complete') : identity?.name || t('Χρειάζεται επιπλέον έλεγχος', 'Further review needed')}</h3>
        {pending && <p className="mt-3 text-sm leading-6 text-slate-500">{t('Ψάχνουμε την κάρτα σου. Μπορεί να χρειαστούν λίγα λεπτά — το αποτέλεσμα θα εμφανιστεί εδώ.', 'We’re looking for your card. This may take a few minutes — your result will appear here.')}</p>}
        {scan.error && <p className="mt-3 text-sm text-slate-500">{scan.error}</p>}
        {identity && <dl className="my-5 grid grid-cols-2 gap-3 text-sm text-slate-600">
          <dt>Set</dt><dd>{identity.set_name || t('Άγνωστο', 'Unknown')}</dd>
          <dt>{t('Αριθμός', 'Number')}</dt><dd>{identity.collector_number || t('Άγνωστος', 'Unknown')}</dd>
          <dt>{t('Ταυτότητα', 'Identity')}</dt><dd>{scan.result.identity_status === 'confirmed' ? t('Επιβεβαιωμένη από το σύστημα', 'Confirmed by the system') : t('Χρειάζεται έλεγχος', 'Needs review')}</dd>
          <dt>Parallel</dt><dd>{confirmed ? scan.result.parallel || t('Δεν δηλώθηκε', 'Not specified') : t('Δεν επιβεβαιώθηκε', 'Unconfirmed')}</dd>
          <dt>Finish</dt><dd>{confirmed ? scan.result.finish || t('Δεν δηλώθηκε', 'Not specified') : t('Δεν επιβεβαιώθηκε', 'Unconfirmed')}</dd>
        </dl>}
        {scan.state === 'completed' && identity?.name && <div className="mb-5">
          <MarketPrice price={scan.result.market_price} english={english} />
        </div>}
        <div className="flex flex-wrap gap-3">
          <Button variant="secondary" size="sm" disabled={busy} onClick={() => apiClient.get('/card-scans/' + scan.id).then(updateScan).catch(e => setError(e.message))}>
            <RefreshCw className="h-3.5 w-3.5" />{t('Ανανέωση', 'Refresh')}
          </Button>
          <Button variant="ghost" size="sm" disabled={busy || pending} onClick={removeScan}>
            <Trash2 className="h-3.5 w-3.5" />{t('Διαγραφή scan και φωτογραφιών', 'Delete scan and photos')}
          </Button>
        </div>
      </CardSurface></div>}
    </BinderShell>
  )
}
