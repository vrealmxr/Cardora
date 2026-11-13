import { mergeConfig } from 'vite'
import base from './vite.ssr.config.js'

export default mergeConfig(base, {
  build: { ssr: 'src/server/originRenderer.jsx', outDir: 'dist-origin',
    rollupOptions: { output: { entryFileNames: 'renderer.mjs', inlineDynamicImports: true } } },
})
