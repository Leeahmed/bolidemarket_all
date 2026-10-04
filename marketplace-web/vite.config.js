import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
export default defineConfig({
  plugins: [vue({ template: { transformAssetUrls: false } })],
  test: { environment: 'jsdom', setupFiles: './tests/setup.js', clearMocks: true, pool: 'forks', maxWorkers: 1 },
  build: { target: 'es2022' },
})
