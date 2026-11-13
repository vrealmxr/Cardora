import { mergeConfig } from 'vite'
import base from './vite.ssr.config.js'
import { readFileSync } from 'node:fs'

export default mergeConfig(base, {
  define: { __PUBLIC_RENDER_RELEASE__: JSON.stringify(JSON.parse(readFileSync('dist-origin/release.json', 'utf8')).release) },
  build: {
    ssr: 'src/worker.jsx', outDir: 'dist', emptyOutDir: false,
    rollupOptions: { output: { entryFileNames: '_worker.js', inlineDynamicImports: true } },
  },
})
