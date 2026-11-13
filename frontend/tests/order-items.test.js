import test from 'node:test'
import assert from 'node:assert/strict'
import { JSDOM } from './runtime/node_modules/jsdom/lib/api.js'

test('buyer and seller can expand purchase details and see sold items in messages without public catalogue', async () => {
 const dom = new JSDOM('<div id="root"></div>', { url: 'http://cardora.test/el' })
 globalThis.window=dom.window; globalThis.document=dom.window.document; globalThis.CustomEvent=dom.window.CustomEvent; globalThis.IS_REACT_ACT_ENVIRONMENT=true
 const Native=globalThis.MessageChannel; const channels=[]
 globalThis.MessageChannel=class extends Native { constructor(){super();channels.push(this)} }
 const originalFetch=globalThis.fetch; let actor=10
 const product={id:318,title:'Purchased book',description:'A book about comics.',condition:'Good',media:[{url:'https://cardora.gr/book.jpg'}],price:4,acceptOffers:false,status:'sold'}
 const order={id:'CDR-FIXTURE',databaseId:41,productId:318,buyerId:36,sellerId:10,title:'Purchased book',itemType:'listing',statusKey:'paid_pending_release',totalAmount:7.75,shipping:3,sellerAmount:4,items:[{...product,id:42,quantity:1,unitPrice:4,lineTotal:4}]}
 order.orderedAt='2026-10-29T13:21:06Z'
 const data={products:[],categories:[],users:[{id:10,name:'Seller'},{id:36,name:'Buyer'}],blogPosts:[],cartItems:[],orders:[order],conversations:[{id:14,productId:318,buyerId:36,sellerId:10,product,messages:[],offers:[],unreadCount:0,updatedAt:'2026-10-29T13:21:06Z'}]}
 globalThis.fetch=async (url,options)=>{
  const path=new URL(url).pathname
  if(path.endsWith('/bootstrap')||path.endsWith('/public-page-data'))return Response.json({data})
  if(path.endsWith('/auth/me'))return Response.json({data:{id:actor,name:actor===10?'Seller':'Buyer'}})
  if(path.endsWith('/messages/conversations/14/read'))return Response.json({})
  throw new Error(`Unexpected request blocked ${options.method} ${path}`)
 }
 const harness=await import('../dist-session-tests/session-harness.js');const {act,getControls}=harness
 const flush=()=>act(async()=>{await new Promise(resolve=>setTimeout(resolve,0))});let root
 try {
  for(actor of [10,36]){
   await act(async()=>{root=harness.mount(document.getElementById('root'),order)});await flush()
   await act(async()=>getControls().auth.authenticateWithToken('qa-fixture-'+actor));await flush()
   const button=[...document.querySelectorAll('button')].find(el=>el.textContent.includes('Λίστα παραγγελίας'))
   assert.ok(button);assert.equal(button.getAttribute('aria-expanded'),'false')
   await act(async()=>button.click())
   const region=document.querySelector('[role="region"]');assert.ok(region)
   assert.equal(region.querySelector('img').src,'https://cardora.gr/book.jpg')
   assert.match(region.textContent,/Purchased book/);assert.match(region.textContent,/A book about comics/)
   assert.match(region.textContent,/4,00/);assert.match(region.textContent,/3,00/);assert.match(region.textContent,/0,75/);assert.match(region.textContent,/7,75/)
   await act(async()=>button.click());assert.equal(document.querySelector('[role="region"]'),null)
   await act(async()=>root.unmount());root=null
   await act(async()=>{root=harness.mount(document.getElementById('root'),null,true)});await flush()
   assert.ok(document.querySelector('h3')?.textContent.includes('Purchased book'))
   assert.ok(document.querySelector('img[alt="Purchased book"]'))
   assert.equal([...document.querySelectorAll('button')].some(el=>el.textContent.includes('Στείλε προσφορά')),false)
   await act(async()=>root.unmount());root=null
  }
 }finally{
  if(root)await act(async()=>root.unmount())
  globalThis.fetch=originalFetch;dom.window.close();for(const channel of channels){channel.port1.close();channel.port2.close()}
  globalThis.MessageChannel=Native;delete globalThis.window;delete globalThis.document;delete globalThis.CustomEvent;delete globalThis.IS_REACT_ACT_ENVIRONMENT
 }
})
