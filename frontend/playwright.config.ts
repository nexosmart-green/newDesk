import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './e2e',
  // Local `php artisan serve` handles one request at a time and Vite compiles lazy routes on first hit.
  workers: process.env.CI ? undefined : 1,
  expect: { timeout: 15_000 },
  use: { baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:5173', trace: 'on-first-retry' },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
  ],
})
