/// <reference types="vitest/config" />
import { fileURLToPath, URL } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

const backendUrl = process.env.VITE_BACKEND_URL ?? 'http://localhost:8000'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    port: 5173,
    // Fail instead of silently moving to 5174+: only :5173 is in SANCTUM_STATEFUL_DOMAINS, so a stray second
    // dev server on another port serves an app whose login dies with "Session store not set on request".
    strictPort: true,
    // Same-origin in dev so Sanctum's SPA cookie auth works without CORS juggling.
    proxy: {
      '/api': backendUrl,
      '/sanctum': backendUrl,
      '/mcp': backendUrl,
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    restoreMocks: true,
    // Typing-heavy RTL tests exceed 5 s when the whole suite runs jsdom files in parallel.
    testTimeout: 20_000,
    // Each worker boots its own jsdom (~150 MB). Uncapped, a dev machine ran out of RAM; CI keeps the default.
    maxWorkers: process.env.CI ? undefined : 2,
    include: ['src/**/*.test.{ts,tsx}'],
  },
})
