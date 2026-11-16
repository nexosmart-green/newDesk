import { expect, test } from '@playwright/test'

// Desk parity (tasks/paridad-desk.md): panel, all tasks, task detail tabs, hours history.
// Desktop only and as the gestor: login is throttled per email, and login.spec uses the collaborator on desktop.
test('the gestor walks the panel, all tasks, a task detail and the hours history', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop', 'one login per user per run')

  await page.goto('/login')
  await page.getByLabel('Email').fill('gestor@nexosmart.com')
  await page.getByLabel('Contraseña').fill('password')
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: /Hola/ })).toBeVisible()

  const nav = page.getByRole('navigation').first()
  await nav.getByRole('link', { name: 'Tareas', exact: true }).click()
  await expect(page).toHaveURL(/\/tareas$/)
  const firstTask = page.getByRole('list', { name: 'Tareas' }).getByRole('link', { name: /^bhn-1(?!\d)/ })
  await expect(firstTask).toBeVisible()
  await firstTask.click()

  await expect(page.getByText(/Creada por/)).toBeVisible()
  await page.getByRole('tab', { name: 'Horas' }).click()
  await expect(page).toHaveURL(/tab=horas/)
  await page.getByRole('tab', { name: 'Historial' }).click()
  await expect(page.getByRole('tabpanel')).not.toContainText('No pudimos')

  await page.goto('/mi-semana?vista=historial')
  await expect(page.getByRole('button', { name: /Exportar CSV/ })).toBeVisible()
})
