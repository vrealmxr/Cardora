import { mergeConfig } from 'vite'
import base from './vite.config.js'

export default mergeConfig(base, {
  define: { 'process.env.NODE_ENV': JSON.stringify('development') },
  ssr: { target: 'webworker', noExternal: true },
  build: { ssr: 'tests/session-harness.jsx', outDir: 'dist-session-tests', copyPublicDir: false,
    rollupOptions: { output: { inlineDynamicImports: true } } },
})
