import { expect, test } from '@playwright/test'

// RF-04.08 / RF-04.09 against the real backend: add and tick a subtask, upload and delete an attachment.
// Desktop only and as the gestor (login is throttled per email; the collaborator logs in on desktop elsewhere).
test('the gestor adds and ticks a subtask, then attaches and removes a file', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'desktop', 'one login per user per run')
  const stamp = Date.now()
  const subtask = `Revisar en Safari ${stamp}`
  const fileName = `nota-${stamp}.txt`

  await page.goto('/login')
  await page.getByLabel('Email').fill('gestor@nexosmart.com')
  await page.getByLabel('Contraseña').fill('password')
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: /Hola/ })).toBeVisible()

  await page.goto('/tareas/bb1-1')
  const input = page.getByPlaceholder('Agregar una subtarea y Enter')
  await input.fill(subtask)
  await input.press('Enter')
  const checkbox = page.getByRole('list', { name: 'Subtareas' }).getByRole('checkbox', { name: subtask })
  await expect(checkbox).toBeVisible()
  await checkbox.check()
  await page.reload()
  await expect(page.getByRole('list', { name: 'Subtareas' }).getByRole('checkbox', { name: subtask })).toBeChecked()

  await page.getByRole('tab', { name: /Archivos/ }).click()
  await page.locator('input[type=file]').setInputFiles({ name: fileName, mimeType: 'text/plain', buffer: Buffer.from('hola desde e2e') })
  const attachments = page.getByRole('list', { name: 'Archivos adjuntos' })
  await expect(attachments.getByText(fileName)).toBeVisible()

  await attachments.getByRole('button', { name: `Borrar ${fileName}` }).click()
  await page.getByRole('dialog').getByRole('button', { name: /Borrar/ }).click()
  await expect(attachments.getByText(fileName)).toBeHidden()
})
