'use strict';
const $ = id => document.getElementById(id);
const fields = ['category','name','set_name','collector_number','language','parallel','finish','serial_number'];
const statusNames = {pending:'Προς έλεγχο',approved:'Ελεγμένα στοιχεία',rejected:'Απορρίφθηκε'};
let token = '', current = null, dirty = false, labelsDirty = false, busy = false, page = 1, generation = 0, listGeneration = 0, imageURLs = [];
let reviewer = '', categories = [];
const tell = (text, error = false) => { $('notice').textContent = text; $('notice').classList.toggle('error',error); };
function node(tag, text, cls) { const el = document.createElement(tag); el.textContent = text; if(cls) el.className = cls; return el; }
async function api(path, data) {
  const response = await fetch("/admin/dataset-review" + path, {cache:'no-store',headers:{Accept:'application/json','X-CSRF-TOKEN':token,...(data ? {'Content-Type':'application/json'} : {})},
    ...(data ? {method:'POST',body:JSON.stringify(data)} : {})});
  if(!response.ok) { const body = await response.json().catch(()=>({})); throw new Error(body.error || body.message || `Σφάλμα ${response.status}`); }
  return response.json();
}
function mayLeave() { return !dirty || window.confirm('Υπάρχουν μη αποθηκευμένες αλλαγές. Να τις αφήσεις;'); }
function lock(value) {
  busy = value;
  document.querySelectorAll('#review-form input,#review-form select,#review-form textarea,#review-form button,#clear-usage,#basis,#card-only-verified').forEach(el=>el.disabled=value);
  if(!value) $('clear-usage').disabled = !current || current.status !== 'approved';
}
async function loadList() {
  const seq = ++listGeneration;
  const result = await api(`/api/scans?status=${encodeURIComponent($('filter').value)}&q=${encodeURIComponent($('search').value)}&page=${page}`);
  if(seq !== listGeneration) return;
  for(const name of ['all','pending','approved','ready']) $(`count-${name}`).textContent = result.counts[name];
  $('export').disabled = result.counts.ready === 0;
  if(page > 1 && !result.items.length) { page--; return loadList(); }
  $('list-summary').textContent = `${result.total} scans στο φίλτρο`;
  $('scan-list').replaceChildren();
  if(!result.items.length) $('scan-list').append(node('p','Δεν υπάρχουν scans σε αυτό το φίλτρο.','muted'));
  for(const item of result.items) {
    const button = node('button','', 'scan');
    button.setAttribute('aria-current', String(current?.id === item.id));
    button.append(node('span',`${categories.find(c=>c.key===item.category)?.label || item.category} · ${item.ready?'Έτοιμο για export':statusNames[item.status]}`,'tag'),
      node('strong',item.name || 'Χωρίς αναγνωρισμένο όνομα'),
      node('span',`${item.set_name || 'Άγνωστο set'} · ${item.collector_number || '—'}`,'muted'),
      node('span',new Date(item.created*1000).toLocaleDateString('el-GR'),'muted'));
    button.addEventListener('click',()=> { if(!busy && mayLeave()) openScan(item.id).catch(e=>tell(e.message,true)); });
    $('scan-list').append(button);
  }
  $('page').textContent = `${page} / ${Math.max(1,Math.ceil(result.total/30))}`;
  $('prev').disabled = page <= 1; $('next').disabled = page*30 >= result.total;
}
function showRecord(row) {
  current = row; dirty = false; labelsDirty = false;
  $('empty').hidden = true; $('visual').hidden = $('editor').hidden = false;
  $('record-status').textContent = statusNames[row.status];
  $('scan-meta').textContent = `${row.id.slice(0,12)} · ${new Date(row.created*1000).toLocaleString('el-GR')} · Επαναλήψεις: ${row.duplicates}`;
  const prediction = row.metadata.prediction;
  const saved = row.review?.labels;
  const values = saved || {...prediction.identity,category:row.category};
  for(const key of fields) $(key).value = values[key] || (key==='category' ? row.category : '');
  $('reviewer').value = reviewer || row.review?.reviewer || '';
  $('evidence').value = row.review?.evidence || '';
  $('verified').checked = false;
  $('basis').value = row.clearance?.basis || '';
  $('card-only-verified').checked = false;
  $('usage-status').textContent = row.clearance?.card_only_verified
    ? `Έγκριση χρήσης καταγεγραμμένη από ${row.clearance.reviewer}. Το scan είναι διαθέσιμο για export.`
    : 'Δεν υπάρχει έγκριση χρήσης. Το scan δεν εξάγεται ακόμη για training.';
  $('clear-usage').disabled = row.status !== 'approved';
  $('prediction').replaceChildren();
  for(const [label,value] of [['Όνομα',prediction.identity.name],['Set',prediction.identity.set_name],['Αριθμός',prediction.identity.collector_number],
    ['Parallel',prediction.parallel],['Finish',prediction.finish],['Ταυτότητα',prediction.identity_status],['Printing',prediction.printing_status],['Αποτέλεσμα',prediction.outcome]]) {
    $('prediction').append(node('dt',label),node('dd',value || 'Άγνωστο'));
  }
  $('provenance').textContent = JSON.stringify({model:row.metadata.provenance,review:row.review,usage_clearance:row.clearance},null,2);
}
async function openScan(id) {
  const seq = ++generation;
  const row = await api(`/api/scans/${id}`);
  if(seq !== generation) return;
  showRecord(row);
  $('zoom').close(); $('zoom-image').removeAttribute('src');
  imageURLs.forEach(URL.revokeObjectURL); imageURLs = [];
  $('photos').replaceChildren(node('p','Φόρτωση φωτογραφιών…','muted'));
  const loaded = await Promise.allSettled(row.metadata.images.map(async (item,index) => {
    const response = await fetch(`/admin/dataset-review/api/scans/${id}/images/${index}`,{cache:'no-store',headers:{'X-CSRF-TOKEN':token}});
    if(!response.ok) throw new Error('Η φωτογραφία δεν φορτώθηκε ή απέτυχε ο έλεγχος ακεραιότητας.');
    return {item,url:URL.createObjectURL(await response.blob())};
  }));
  if(seq !== generation) { loaded.forEach(r=> {if(r.status==='fulfilled') URL.revokeObjectURL(r.value.url);}); return; }
  $('photos').replaceChildren();
  for(const result of loaded) {
    if(result.status==='rejected') { $('photos').append(node('p',result.reason.message,'hint')); continue; }
    const {item,url} = result.value; imageURLs.push(url);
    const label = {front:'Μπροστινή όψη',back:'Πίσω όψη',additional:'Πρόσθετη λήψη',angled:'Λήψη υπό γωνία'}[item.role];
    const button = node('button','', 'photo'); const image = document.createElement('img');
    image.src = url; image.alt = label;
    button.append(image,node('span',`${label} · ${item.width} × ${item.height}`));
    button.addEventListener('click',()=> { $('zoom-image').src=url; $('zoom-image').alt=label; $('zoom-title').textContent=label; $('zoom').showModal(); });
    $('photos').append(button);
  }
  await loadList();
}
async function save(action) {
  if(!current || busy) return;
  if(action==='approve' && (!$('review-form').reportValidity() || !$('verified').checked)) {
    tell('Συμπλήρωσε τα υποχρεωτικά πεδία και επιβεβαίωσε ότι έλεγξες τα στοιχεία.',true); return;
  }
  if(!$('reviewer').value.trim() || !$('evidence').value.trim()) { tell('Χρειάζονται ψευδώνυμο ελεγκτή και τεκμηρίωση ή λόγος απόρριψης.',true); return; }
  if(action==='reject' && !window.confirm('Να απορριφθεί αυτό το scan από το dataset; Μπορείς να το επανεξετάσεις αργότερα.')) return;
  reviewer = $('reviewer').value.trim();
  const labels = Object.fromEntries(fields.map(key=>[key,$(key).value.trim() || null]));
  lock(true);
  try {
    const row = await api(`/api/scans/${current.id}/review`,{action,labels,reviewer,evidence:$('evidence').value.trim(),revision:current.revision});
    showRecord(row); await loadList();
    tell(action==='approve' ? 'Τα διορθωμένα στοιχεία αποθηκεύτηκαν. Η έγκριση χρήσης παραμένει ξεχωριστό βήμα.' : 'Το scan απορρίφθηκε. Οι εικόνες παραμένουν διαθέσιμες για επανέλεγχο.');
  } catch(e) { tell(e.message,true); } finally { lock(false); }
}
$('review-form').addEventListener('submit',e=> { e.preventDefault(); save('approve'); });
$('reject').addEventListener('click',()=>save('reject'));
$('review-form').addEventListener('input',()=>{dirty=true;labelsDirty=true;});
$('basis').addEventListener('input',()=>dirty=true);
$('clear-usage').addEventListener('click',async()=> {
  if(!current || busy) return;
  if(labelsDirty) { tell('Αποθήκευσε πρώτα τις αλλαγές στα στοιχεία ελέγχου.',true); return; }
  const alias = $('reviewer').value.trim(), basis = $('basis').value.trim();
  if(!alias || !basis || !$('card-only-verified').checked) { tell('Χρειάζονται ελεγκτής, τεκμηρίωση και έλεγχος ότι όλες οι εικόνες περιέχουν μόνο την κάρτα.',true); return; }
  // Never clear usage against labels different from the saved decision.
  const saved = current.review?.labels || {};
  if(fields.some(key=>($(key).value.trim() || null) !== (saved[key] || (key==='category'?current.category:null)))) {
    tell('Αποθήκευσε πρώτα τις αλλαγές στα στοιχεία.',true); return;
  }
  lock(true);
  try { showRecord(await api(`/api/scans/${current.id}/clear-usage`,{reviewer:alias,basis,card_only_verified:true,revision:current.revision})); await loadList(); tell('Η έγκριση χρήσης καταγράφηκε. Το scan μπορεί να εξαχθεί.'); }
  catch(e) { tell(e.message,true); } finally { lock(false); }
});
$('refresh').addEventListener('click',()=> { if(busy || !mayLeave()) return; (current?openScan(current.id):loadList()).catch(e=>tell(e.message,true)); });
for(const id of ['filter','search']) $(id).addEventListener('change',()=> { page=1; loadList().catch(e=>tell(e.message,true)); });
$('prev').addEventListener('click',()=> { page--; loadList().catch(e=>tell(e.message,true)); });
$('next').addEventListener('click',()=> { page++; loadList().catch(e=>tell(e.message,true)); });
$('close-zoom').addEventListener('click',()=>$('zoom').close());
$('export').addEventListener('click',()=> { if(!busy) $('export-dialog').showModal(); });
$('cancel-export').addEventListener('click',()=>$('export-dialog').close());
$('confirm-export').addEventListener('click',async()=> {
  $('confirm-export').disabled=true;
  try {
    const response=await fetch('/admin/dataset-review/api/export',{method:'POST',headers:{'X-CSRF-TOKEN':token,'Accept':'application/json'}});
    if(!response.ok) { const error=await response.json(); throw new Error(error.message || 'Export failed'); }
    const url=URL.createObjectURL(await response.blob()); const link=document.createElement('a'); link.href=url;link.download='cardora-reviewed-dataset.zip';link.click();setTimeout(()=>URL.revokeObjectURL(url),10000);
    $('export-dialog').close();tell('Το ZIP με τις εικόνες και το manifest κατέβηκε. Απαιτείται διαχωρισμός train/validation/test και έλεγχος διπλοτύπων.');
  }
  catch(e) { tell(e.message,true); $('export-dialog').close(); } finally { $('confirm-export').disabled=false; }
});
window.addEventListener('beforeunload',e=> { if(dirty) {e.preventDefault();e.returnValue='';} });
(async()=> {
  const response=await fetch('/admin/dataset-review/session',{cache:'no-store'}); if(!response.ok) throw new Error('Δεν είναι διαθέσιμη η τοπική συλλογή.');
  const session=await response.json(); token=session.token; categories=session.categories;
  for(const category of categories) { const option=node('option',category.label); option.value=category.key; $('category').append(option); }
  $('storage').textContent=`${session.root} · Όριο ${(session.max_bytes/1024**3).toFixed(1)} GiB · Διατήρηση ${session.retention_days} ημέρες. Ο έλεγχος δεν παρατείνει τη διατήρηση. Τα ZIP που κατεβάζεις χρειάζονται χωριστή διαχείριση διαγραφής και εκδόσεων.`;
  await loadList(); tell('Ιδιωτική συλλογή συνδεδεμένη. Δεν εκτελείται inference ή training από αυτή την οθόνη.');
})().catch(e=>tell(e.message,true));
