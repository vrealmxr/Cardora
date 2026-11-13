import assert from 'node:assert/strict'
import fs from 'node:fs/promises'

const output=process.argv[2]
if(!output)throw new Error('Evidence output path required')
const rows=[]
const cases=[
 ['/el',200],['/en',200],['/el/kartes',200],['/en/figoures',200],['/el/komik-vivlia',200],['/en/diafora',200],
 ['/el/syllektika',200],['/en/odigos-polisis',200],['/el/katastasi-kai-grading',200],['/en/faq',200],
 ['/en/epikoinonia',200],['/en/proion/doublade',200],['/en/proion/2025-26-topps-8-bit-ballers-8b-18',200],
 ['/en/blog/pos-na-apotimiseis-sosta-mia-karta-prin-ti-valeis-pros-polisi-stin-cardora',200],
 ['/el/blog/pos-na-steileis-kartes-me-asfaleia',200],['/en/sylloges/ninjaniarf',200],
 ['/el/not-a-page-release-qa',404],['/en/proion/not-a-card-release-qa',404],['/en/profil',200],
 ['/en/kartes?franchise=Pokemon',200],['/en/kartes?page=2',200],['/en/terms',301],
 ['/robots.txt',200],['/sitemap.xml',200],['/static/missing-release-check.js',404],
]
let assetHtml='',anonymousData
for(const [path,expected] of cases){
 const began=Date.now()
 const response=await fetch('https://cardora.gr'+path,{redirect:'manual',signal:AbortSignal.timeout(20000),headers:{'User-Agent':'Cardora-Release-QA/1.0'}})
 const body=await response.text(), type=response.headers.get('content-type')||''
 const row={path,status:response.status,expected,ms:Date.now()-began,type,robots:response.headers.get('x-robots-tag'),setCookie:response.headers.has('set-cookie'),h1:(body.match(/<h1[\s>]/g)||[]).length,checks:[]}
 const check=(label,value)=>row.checks.push({label,pass:!!value})
 check('status',response.status===expected)
 check('no response cookies',!row.setCookie)
 if(type.includes('text/html')){
  check('one H1 or private CSR',path.endsWith('/profil')?row.h1===0:row.h1===1)
  check('production canonical',body.includes('href="https://cardora.gr/'+path.split('/')[1]))
  if(path.endsWith('/profil'))check('private data absent',!body.includes('id="cardora-public-data"'))
  else if(expected===200)check('initial public data present',body.includes('id="cardora-public-data"'))
  if(expected===200&&!path.includes('?franchise=')&&!path.endsWith('/profil'))check('public page indexable',!body.match(/<meta name="robots" content="[^"]*noindex/) && !row.robots?.includes('noindex'))
  else check('private/filtered/missing noindex',body.match(/<meta name="robots" content="[^"]*noindex/)||row.robots?.includes('noindex'))
 }
 if(path==='/en/epikoinonia'){
  const protectedLinks=[...body.matchAll(/href="\/cdn-cgi\/l\/email-protection#([a-f0-9]+)"/g)]
  const validProtected=protectedLinks.some(([,hex])=>{const bytes=Buffer.from(hex,'hex');return [...bytes.subarray(1)].map(n=>String.fromCharCode(n^bytes[0])).join('')==='support@cardora.gr'})
  check('working contact link',body.includes('mailto:support@cardora.gr') || (validProtected && body.includes('cloudflare-static/email-decode.min.js')))
 }
 if(path.includes('8-bit-ballers'))check('sold notice',body.includes('This item has been sold'))
 if(path==='/robots.txt')check('valid robots text',type.includes('text/plain')&&body.includes('Sitemap: https://cardora.gr/sitemap.xml')&&!body.includes('<!doctype'))
 if(path==='/sitemap.xml')check('public XML sitemap',type.includes('application/xml')&&body.includes('<urlset')&&!body.includes('/cardora-scanner')&&!body.includes('/profil</loc>'))
 if(path==='/el'){assetHtml=body;anonymousData=body.match(/id="cardora-public-data">([\s\S]*?)<\/script>/)?.[1]}
 rows.push(row)
 await fs.writeFile(output,JSON.stringify({at:new Date().toISOString(),rows},null,2))
 if(response.status>=500)throw new Error(`Release unavailable: ${path} (${response.status})`)
}
const cookieResponse=await fetch('https://cardora.gr/el',{headers:{Cookie:'cardora_qa=synthetic','Authorization':'Bearer synthetic-invalid','User-Agent':'Cardora-Release-QA/1.0'},signal:AbortSignal.timeout(20000)})
const cookieBody=await cookieResponse.text()
rows.push({path:'/el (synthetic credentials)',status:cookieResponse.status,checks:[{label:'anonymous SSR independent of credentials',pass:cookieResponse.status===200 && cookieBody.match(/id="cardora-public-data">([\s\S]*?)<\/script>/)?.[1]===anonymousData && !cookieResponse.headers.has('set-cookie')}]})
for(const path of [...new Set([...assetHtml.matchAll(/(?:src|href)="(\/static\/[^"<>]+\.(?:js|css|woff2))"/g)].map(m=>m[1]))].slice(0,6)){
 const response=await fetch('https://cardora.gr'+path,{signal:AbortSignal.timeout(15000)})
 await response.arrayBuffer()
 rows.push({path,status:response.status,type:response.headers.get('content-type'),checks:[{label:'real asset',pass:response.status===200&&!response.headers.get('content-type')?.includes('text/html')}]})
}
const failures=rows.filter(row=>row.checks.some(c=>!c.pass))
await fs.writeFile(output,JSON.stringify({at:new Date().toISOString(),rows,failures},null,2))
console.log(JSON.stringify({requests:rows.length,failures},null,2))
assert.equal(failures.length,0)
