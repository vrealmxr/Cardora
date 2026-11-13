import test from 'node:test'
import assert from 'node:assert/strict'
import { requestOriginPage } from '../src/server/originTransport.js'
import { handleOriginBridge } from '../src/server/originBridge.js'

const env = {PUBLIC_RENDER_SIGNING_KEY:'a'.repeat(64),PUBLIC_RENDER_RELEASE:'1'.repeat(16)}
async function signedRequest() {
  let captured
  await requestOriginPage(new Request('https://preview.cardora-frontend.pages.dev/en/kartes',{headers:{'CF-Connecting-IP':'192.0.2.1','X-Forwarded-For':'198.51.100.2','Cookie':'private'}}),env,async(url,options)=>{
    captured = new Request(url,options)
    return new Response('HTML',{headers:{'X-Cardora-Document-Type':'text/html; charset=utf-8'}})
  })
  return captured
}
test('bridge verifies visitor attribution and body before forwarding to fixed origin',async()=>{
  const request=await signedRequest()
  let called=0
  const result=await handleOriginBridge(request,env,async(url,options)=>{
    called++
    assert.match(url,/^https:\/\/public-origin\.cardora\.gr\/index\.php\/api\/public-html\/[a-f0-9]{64}\?locale=en$/)
    assert.equal(options.headers.get('x-real-ip'),'192.0.2.1')
    assert.equal(options.headers.get('cf-connecting-ip'),'192.0.2.1')
    for(const name of ['authorization','cookie','x-forwarded-for','x-cardora-visitor-ip','host']) assert.equal(options.headers.get(name),null)
    assert.equal(options.cf,undefined)
    return new Response('public document',{headers:{'Content-Type':'application/octet-stream'}})
  })
  assert.equal(called,1);assert.equal(await result.text(),'public document')
})
test('forged IP, stale signature, tampered payload, credentials and unrelated endpoints never reach origin',async()=>{
  const original=await signedRequest()
  for(const [header,value] of [['X-Cardora-Visitor-IP','198.51.100.2'],['X-Cardora-Public-Time','1000000000'],['X-Cardora-Public-Signature','f'.repeat(64)],['X-Cardora-Render-Request','e30='],['Cookie','private'],['Authorization','Bearer private']]) {
    const headers=new Headers(original.headers);headers.set(header,value)
    const result=await handleOriginBridge(new Request(original.url,{headers}),env,()=>{throw new Error('Must not fetch')})
    assert.equal(result.status,403,header)
  }
  for(const url of ['https://cardora.gr/index.php/api/orders','https://cardora.gr/scanner/scan',original.url+'&extra=true']) {
    const result=await handleOriginBridge(new Request(url,{headers:original.headers}),env,()=>{throw new Error('Must not fetch')})
    assert.equal(result.status,403)
  }
})
