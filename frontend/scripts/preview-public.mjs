import { createServer } from 'vite'

// Local browser QA only. No account requests, writes, scanner or inference calls.
const server = await createServer({
  server: {
    host: '127.0.0.1', port: 5178, strictPort: true,
    proxy: Object.fromEntries(['/index.php/api', '/asset.php', '/storage'].map((prefix) => [prefix, {
      target: 'https://cardora.gr', changeOrigin: true,
      configure(proxy) {
        proxy.on('proxyReq', (request) => {
          request.removeHeader('cookie')
          request.removeHeader('authorization')
        })
      },
    }])),
  },
  plugins: [{
    name: 'public-preview-read-only',
    configureServer(vite) {
      vite.middlewares.use((request, response, next) => {
        const path = new URL(request.url, 'http://localhost').pathname
        const blocked = !['GET', 'HEAD'].includes(request.method)
          || (/^\/index.php\/api/.test(path) && !/^\/index.php\/api\/(bootstrap|seo|listings\/\d+)$/.test(path))
          || /\/(scanner|cardora-scanner|runpod)(\/|$)/i.test(path)
        if (blocked) { response.writeHead(403); response.end('Read-only public preview'); return }
        next()
      })
    },
    transformIndexHtml(html) {
      // Local QA should not count as a production visit or open a support chat.
      return html.replace(/<script>[\s\S]*?<\/script>/g, '').replace(/<noscript>[\s\S]*?<\/noscript>/g, '')
    },
  }],
})
await server.listen()
server.printUrls()
