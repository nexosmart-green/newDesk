import { expect, test } from '@playwright/test'

// Requires backend (php artisan serve, seeded) + frontend dev server.
// Each viewport logs in as a different seeded user: login is throttled to 5/min per email+IP.
const userByProject: Record<string, string> = {
  desktop: 'colaborador@nexosmart.com',
  mobile: 'gestor@nexosmart.com',
}

test('a non-admin user logs in and lands on Mis tareas', async ({ page }, testInfo) => {
  await page.goto('/login')
  await page.getByLabel('Email').fill(process.env.E2E_USER_EMAIL ?? userByProject[testInfo.project.name] ?? 'colaborador@nexosmart.com')
  await page.getByLabel('Contraseña').fill(process.env.E2E_USER_PASSWORD ?? 'password')
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: /Hola/ })).toBeVisible()
})
