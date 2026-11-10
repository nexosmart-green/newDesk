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
    include: ['src/**/*.test.{ts,tsx}'],
  },
})
