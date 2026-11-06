import { expect, test } from '@playwright/test'

// Requires backend (php artisan serve) with seeded admin + frontend dev server.
test('admin logs in and lands on the home page', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('Email').fill(process.env.E2E_ADMIN_EMAIL ?? 'admin@nexosmart.com')
  await page.getByLabel('Contraseña').fill(process.env.E2E_ADMIN_PASSWORD ?? 'password')
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: /Hola/ })).toBeVisible()
})
