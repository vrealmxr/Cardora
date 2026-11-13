import { createServer } from 'node:http'
import { readFile } from 'node:fs/promises'
import { resolve, extname, sep } from 'node:path'
import { renderPublicPage } from '../dist-ssr/entry-server.js'
import { createPublicHandler } from '../src/server/publicHandler.js'
import { sanitizePublicProfile } from '../src/utils/publicProfile.js'
import { normalizeTextTree } from '../src/utils/textEncoding.js'
import { createServer as createViteServer } from 'vite'

// QA uses previously captured anonymous data. No scans, writes or account calls.
const fixtures = process.env.CARDORA_PUBLIC_FIXTURES
if (!fixtures) throw new Error('Set CARDORA_PUBLIC_FIXTURES to the public snapshot directory')
const root = resolve('dist')
const readSnapshot = async (name, locale) => {
  const json = JSON.parse(await readFile(resolve(fixtures, `${name}-${locale}.json`), 'utf8'))
  return normalizeTextTree(json.data ?? json)
}
const vite = process.env.CARDORA_QA_DEV ? await createViteServer({ server: { middlewareMode: true }, appType: 'custom' }) : null
let template = (await readFile(vite ? 'index.html' : resolve(root, 'index.html'), 'utf8'))
  .replace(/<script>[\s\S]*?<\/script>/g, '').replace(/<noscript>[\s\S]*?<\/noscript>/g, '')
if (vite) template = await vite.transformIndexHtml('/', template)
const readProfile = async handle => handle === 'ninjaniarf'
  ? sanitizePublicProfile(JSON.parse(await readFile(resolve(fixtures, 'public-profile-ninjaniarf.json'), 'utf8')).data) : null
const readListing = async (slug, locale) => {
  const archived = await readSnapshot('public-listings', locale)
  if (archived[slug]) return archived[slug]
  const catalog = await readSnapshot('public-page-data', locale)
  const product = catalog.products.find(item => item.slug === slug)
  if (!product) return null
  return { ...product, seller: catalog.users.find(user => user.id === product.sellerId),
    category: catalog.categories.find(category => category.id === product.categoryId) }
}
const handle = createPublicHandler({ template, render: renderPublicPage, tutorialSlugs: [],
  loadProfile: readProfile,
  loadListing: readListing,
  loadListingPaths: async () => JSON.parse(await readFile(resolve(fixtures, 'public-listing-paths.json'), 'utf8')).data,
  loadData: (locale) => readSnapshot('public-page-data', locale), loadSeo: (locale) => readSnapshot('seo', locale) })
const mime = { '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.svg': 'image/svg+xml',
  '.jpg': 'image/jpeg', '.webp': 'image/webp', '.woff2': 'font/woff2', '.ico': 'image/x-icon', '.json': 'application/json' }
const env = { ASSETS: { fetch: async (request) => {
  const path = resolve(root, '.' + decodeURIComponent(new URL(request.url).pathname))
  if (!path.startsWith(root + sep)) return new Response('', { status: 404 })
  try { return new Response(await readFile(path), { headers: { 'Content-Type': mime[extname(path)] || 'application/octet-stream' } }) }
  catch { return new Response('', { status: 404 }) }
} } }
const serve = async (req, res) => {
  try {
    const url = new URL(req.url, 'http://127.0.0.1:5179')
    let response
    if (!['GET', 'HEAD'].includes(req.method) || /\/(scanner|cardora-scanner|runpod)(\/|$)/i.test(url.pathname)) {
      response = new Response('Read-only QA', { status: 403 })
    } else if (url.pathname.startsWith('/index.php/api/')) {
      const locale = req.headers['x-locale'] === 'en' ? 'en' : 'el'
      const name = url.pathname.split('/').at(-1)
      if (['bootstrap', 'public-page-data', 'seo'].includes(name)) {
        response = Response.json({ data: await readSnapshot(name === 'seo' ? 'seo' : 'public-page-data', locale) })
      } else if (url.pathname.startsWith('/index.php/api/profiles/')) {
        const profile = await readProfile(name)
        response = Response.json({ data: profile }, { status: profile ? 200 : 404 })
      } else if (url.pathname.startsWith('/index.php/api/public-listings/')) {
        const product = await readListing(decodeURIComponent(name), locale)
        response = Response.json({ data: product }, { status: product ? 200 : 404 })
      } else response = Response.json({ message: 'Account and other API requests are disabled in QA' }, { status: 403 })
    } else if (url.pathname === '/asset.php' || url.pathname.startsWith('/storage/')) {
      response = Response.redirect(`https://cardora.gr${url.pathname}${url.search}`, 302)
    } else response = await handle(new Request(url, { method: req.method }), env)
    res.writeHead(response.status, Object.fromEntries(response.headers))
    res.end(Buffer.from(await response.arrayBuffer()))
  } catch (error) { console.error(error); res.writeHead(500); res.end('QA error') }
}
createServer((req, res) => vite ? vite.middlewares(req, res, () => serve(req, res)) : serve(req, res))
  .listen(5179, '127.0.0.1', () => console.log('Rendered public QA: http://127.0.0.1:5179/en/kartes'))
