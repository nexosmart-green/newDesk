import { expect, test } from '@playwright/test'

// RF-06.03 / RF-06.05 against the real backend: a recurring shift fills the week, the team calendar shows it,
// and deleting the rule leaves the data as it was. Desktop only, as the dev seed's shift coordinator (Gestor has no shifts.manage; Admin needs 2FA).
test('the shift coordinator creates a recurring shift, sees the team calendar and removes the rule', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop', 'one login per user per run')

  await page.goto('/login')
  await page.getByLabel('Email').fill('coordinador@nexosmart.com')
  await page.getByLabel('Contraseña').fill('password')
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: /Hola/ })).toBeVisible()

  await page.goto('/turnos')
  await page.getByRole('tab', { name: 'Turnos fijos' }).click()
  await page.getByRole('button', { name: 'Nuevo turno fijo' }).first().click()
  const dialog = page.getByRole('dialog')
  await dialog.getByRole('checkbox', { name: 'Gina Gestora' }).check()
  const template = dialog.getByRole('combobox', { name: 'Plantilla' })
  const fullDay = await template.locator('option', { hasText: 'Completo' }).first().getAttribute('value')
  await template.selectOption(fullDay ?? '')
  await dialog.getByRole('button', { name: 'Todos los días' }).click()
  await dialog.getByRole('button', { name: 'Crear turno fijo' }).click()
  await expect(page.getByText(/Turno fijo creado: se generaron \d+ días/)).toBeVisible()

  await page.goto('/calendario?vista=equipo')
  await expect(page.getByLabel(/^Trabajando ahora: \d+/).or(page.getByLabel(/^Más tarde: \d+/)).or(page.getByLabel(/^Ya terminó: \d+/)).first()).toBeVisible()
  await expect(page.getByText('Gina Gestora').first()).toBeVisible()

  await page.goto('/turnos')
  await page.getByRole('tab', { name: 'Turnos fijos' }).click()
  await page.getByRole('button', { name: 'Borrar turno fijo de Gina Gestora' }).first().click()
  await page.getByRole('dialog').getByRole('button', { name: /Borrar/ }).click()
  await expect(page.getByRole('button', { name: 'Borrar turno fijo de Gina Gestora' })).toHaveCount(0)
})
