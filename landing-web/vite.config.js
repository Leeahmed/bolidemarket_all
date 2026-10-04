import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue({ template: { transformAssetUrls: false } })],
  // Video exports can be locked by Windows; serve them without watching them.
  server: { watch: { ignored: ['**/public/videos/**'] } },
  test: { environment: 'jsdom', setupFiles: './tests/setup.js', clearMocks: true, pool: 'threads', maxWorkers: 1 },
  build: { target: 'es2022' },
})
