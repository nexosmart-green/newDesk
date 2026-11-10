import { expect, test } from '@playwright/test'

// Requires the seeded sample project (bhn) with hours and task bhn-1 assigned to the collaborator.
// Desktop only: one person, one timer — running both viewports in parallel would share the same timer.
test('starts the timer from Mis tareas, shows it in the header and stops it with the mandatory comment', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop', 'single shared timer per user')

  await page.goto('/login')
  await page.getByLabel('Email').fill('colaborador@nexosmart.com')
  await page.getByLabel('Contraseña').fill('password')
  await page.getByRole('button', { name: 'Ingresar' }).click()

  await page.getByRole('button', { name: 'Iniciar cronómetro en bhn-1' }).first().click()
  await expect(page.getByRole('timer', { name: 'Tiempo transcurrido' })).toBeVisible()

  await page.getByRole('button', { name: 'Detener cronómetro de bhn-1' }).first().click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.getByRole('button', { name: 'Detener' })).toBeDisabled()
  await dialog.getByLabel('¿Qué hiciste?').fill('Maqueté el header responsive')
  await dialog.getByRole('button', { name: 'Detener' }).click()

  await expect(page.getByRole('timer', { name: 'Tiempo transcurrido' })).toBeHidden()
})
