import { mergeConfig } from 'vite'
import base from './vite.config.js'

export default mergeConfig(base, {
  ssr: { target: 'webworker', noExternal: true },
  build: {
    ssr: 'src/entry-server.jsx',
    outDir: 'dist-ssr',
    copyPublicDir: false,
    rollupOptions: { output: { inlineDynamicImports: true } },
  },
})
