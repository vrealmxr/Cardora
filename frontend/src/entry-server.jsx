import { renderToReadableStream } from 'react-dom/server.browser'
import { StaticRouter } from 'react-router-dom/server'
import App from './App'
import { DEFAULT_SHARE_IMAGE, getSeoUrls } from './utils/seoUrls'

// The caller must supply an anonymous, allowlisted public snapshot. No cookies,
// authorization headers or account/bootstrap payloads belong in this renderer.
export async function renderPublicPage(url, publicData, seoSettings = {}) {
  const address = new URL(url, 'https://cardora.gr')
  const locale = address.pathname.startsWith('/en/') || address.pathname === '/en' ? 'en' : 'el'
  const head = {
    title: 'Cardora',
    image: DEFAULT_SHARE_IMAGE,
    type: 'website',
    description: locale === 'en'
      ? 'Discover cards, figures, comics and collectibles on Cardora.'
      : 'Ανακάλυψε κάρτες, φιγούρες, κόμικς και συλλεκτικά στην Cardora.',
    locale,
    ...getSeoUrls(address.pathname, address.search, locale),
  }
  const errors = []
  const controller = new AbortController()
  const timeout = setTimeout(() => controller.abort(), 8000)
  try {
    const stream = await renderToReadableStream(
      <StaticRouter location={address.pathname + address.search}>
        <App initialPublicData={publicData} initialSeo={seoSettings} serverHead={head} />
      </StaticRouter>,
      { onError: (error) => { errors.push(error) }, signal: controller.signal },
    )
    await stream.allReady
    if (errors.length) throw errors[0]
    const html = await new Response(stream).text()
    return { html, head }
  } finally { clearTimeout(timeout) }
}
