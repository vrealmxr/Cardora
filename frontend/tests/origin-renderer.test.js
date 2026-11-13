import test from 'node:test'
import process from 'node:process'
import { Buffer } from 'node:buffer'
import assert from 'node:assert/strict'
import { spawnSync } from 'node:child_process'
import { createHash, createHmac } from 'node:crypto'
import { requestOriginPage } from '../src/server/originTransport.js'

test('origin transport binds release, path and preview mode; strips credentials and response cookies', async () => {
  const secret = 'a'.repeat(64), release = '1'.repeat(16)
  const request = new Request('https://qa.cardora-frontend.pages.dev/en/kartes?page=2', {headers:{Cookie:'private','Authorization':'Bearer private','CF-Connecting-IP':'192.0.2.1'}})
  let called = false
  const result = await requestOriginPage(request, {PUBLIC_RENDER_RELEASE:release,PUBLIC_RENDER_SIGNING_KEY:secret}, async (url, options) => {
    called = true
    const raw = Buffer.from(options.headers['X-Cardora-Render-Request'],'base64').toString()
    assert.deepEqual(JSON.parse(raw),{version:1,path:'/en/kartes?page=2',mode:'preview',release})
    const digest = createHash('sha256').update(raw).digest('hex')
    assert.equal(url,`https://public-render.cardora.gr/__cardora_public_html/${digest}?locale=en`)
    const h = options.headers
    assert.equal(h['X-Cardora-Public-Signature'],createHmac('sha256',secret).update(['v1',h['X-Cardora-Public-Time'],'GET',`public-html/${digest}`,'en',h['X-Cardora-Public-Visitor']].join('\n')).digest('hex'))
    assert.equal(h.Cookie,undefined); assert.equal(h.Authorization,undefined)
    return new Response('<h1>Public</h1>',{headers:{'Content-Type':'application/octet-stream','X-Cardora-Document-Type':'text/html; charset=utf-8','Set-Cookie':'private'}})
  })
  assert.ok(called); assert.equal(result.headers.get('set-cookie'),null)
  assert.equal(result.headers.get('x-robots-tag'),'noindex, nofollow')
})

test('origin transport fails closed on overload and does not expose upstream bodies', async () => {
  const request = new Request('https://cardora.gr/el', {headers:{'CF-Connecting-IP':'192.0.2.1'}})
  for (const status of [403,429,500,503]) {
    const response = await requestOriginPage(request,{PUBLIC_RENDER_RELEASE:'1'.repeat(16),PUBLIC_RENDER_SIGNING_KEY:'a'.repeat(64)},async()=>new Response('private exception',{status}))
    assert.equal(response.status,503); assert.equal(response.headers.get('retry-after'),'30')
    assert.ok(!(await response.text()).includes('private exception'))
  }
})

test('production and preview use the same signed Custom Domain without service bindings', async () => {
  const originalFetch = globalThis.fetch
  const addresses = []
  const response = () => new Response('HTML',{headers:{'X-Cardora-Document-Type':'text/html; charset=utf-8'}})
  const env = {PUBLIC_RENDER_SIGNING_KEY:'a'.repeat(64),PUBLIC_RENDER_RELEASE:'1'.repeat(16)}
  globalThis.fetch=async address=>{addresses.push(new URL(address));return response()}
  try {
    for(const host of ['cardora.gr','qa.cardora-frontend.pages.dev']) await requestOriginPage(new Request(`https://${host}/en/kartes`,{headers:{'CF-Connecting-IP':'192.0.2.1'}}),env)
    assert.equal(addresses.length,2)
    assert.ok(addresses.every(url=>url.hostname==='public-render.cardora.gr'))
  } finally { globalThis.fetch=originalFetch }
})

test('origin CLI renders actual React HTML, preserves locale and never starts network clients', () => {
  const data = {categories:[{id:'cards',slug:'kartes',name:'Κάρτες',metrics:{listings:0},spotlightFilters:[]}],products:[]}
  for (const [mode,path] of [['preview','/en/kartes'],['production','/el/kartes'],['production','/en/profil'],['production','/en/not-a-page']]) {
    const run = spawnSync(process.execPath,['dist-origin/origin-render-cli.mjs'],{input:JSON.stringify({version:1,mode,path,data,seo:{}}),encoding:'utf8',timeout:10000,maxBuffer:12*1024*1024})
    assert.equal(run.status,0,run.stderr)
    const result = JSON.parse(run.stdout)
    assert.equal(result.status,path.endsWith('not-a-page')?404:200)
    assert.ok(result.body.includes(`lang="${path.slice(1,3)}"`))
    if (mode==='preview') assert.equal(result.headers['x-robots-tag'],'noindex, nofollow')
    if (path.endsWith('profil')) assert.ok(!result.body.includes('id="cardora-public-data"'))
    else assert.equal((result.body.match(/<h1[\s>]/g)||[]).length,1)
  }
})
