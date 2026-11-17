import { expect, test, type Page } from '@playwright/test'

// RF-14 in a real browser, desktop and mobile, as the collaborator (one login per project and run).
// The real part: a role without `assistant.use` has no "Asistente". The flow part simulates the assistant's
// API (never the real engine, which answers through `claude -p`): the screen, polling, proposal and toast.
const now = () => new Date().toISOString()
const question = { id: 1, conversation_id: 7, role: 'user', content: '¿Qué bolsas se están por agotar?', status: 'done', error: null, created_at: now(), proposals: [], files: [] }
const thinking = { ...question, id: 2, role: 'assistant', content: null, status: 'running' }
const proposal = { id: 9, summary: 'Cargar 20h 00m a bhn «BH News» — motivo: Presupuesto #123', status: 'pending', error: null, expires_at: now(), resolved_at: null }
const answered = {
  ...thinking,
  status: 'done',
  content: 'Hay **1 bolsa** por encima del 80 %:\n\n| Proyecto | Disponibles | % |\n|---|---:|---:|\n| bhn — BH News | 3h 50m | 90 % |\n\nTe dejé la carga lista para confirmar.',
  proposals: [proposal],
}

async function simulateAssistant(page: Page) {
  let confirmed = false
  let messageCalls = 0
  await page.route('**/api/v1/me', async (route) => {
    const response = await route.fetch()
    const body = await response.json()
    body.data.permissions.push('assistant.use')
    await route.fulfill({ response, json: body })
  })
  await page.route('**/api/v1/assistant/conversations', (route) =>
    route.fulfill({ json: { data: messageCalls ? [{ id: 7, title: question.content, channel: 'web', last_message_at: now() }] : [], meta: { enabled: true, discord_linked: true, discord_available: false } } }),
  )
  await page.route('**/api/v1/assistant/messages', (route) => route.fulfill({ status: 202, json: { data: thinking, message: null, errors: null } }))
  await page.route('**/api/v1/assistant/conversations/7/messages', (route) => {
    messageCalls++
    const answer = messageCalls < 3 ? thinking : { ...answered, proposals: [{ ...proposal, status: confirmed ? 'confirmed' : 'pending' }] }
    return route.fulfill({ json: { data: [question, answer], message: null, errors: null } })
  })
  await page.route('**/api/v1/assistant/proposals/9/confirm', (route) => {
    confirmed = true
    return route.fulfill({ json: { data: { ...proposal, status: 'confirmed' }, message: 'Listo, quedó hecho.', errors: null } })
  })
}

test('the assistant: hidden without the permission; with it, ask, see the answer and confirm the change', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('Email').fill('colaborador@nexosmart.com')
  await page.getByLabel('Contraseña').fill('password')
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: /Hola/ })).toBeVisible()

  // Real backend: the collaborator's role has no assistant.
  await expect(page.getByRole('link', { name: 'Asistente' })).toHaveCount(0)
  await page.goto('/asistente')
  await expect(page.getByText('Tu rol no tiene habilitado el asistente de IA.')).toBeVisible()

  await simulateAssistant(page)
  await page.reload()
  await page.getByRole('button', { name: '¿Qué bolsas se están por agotar?' }).click()

  await expect(page.getByText('Pensando…')).toBeVisible()
  await expect(page).toHaveURL(/\/asistente\?c=7$/)
  const log = page.getByRole('log', { name: 'Conversación con el asistente' })
  await expect(log.getByRole('table')).toBeVisible({ timeout: 15_000 })
  // The composer stays on screen (the thread scrolls, not the page).
  const composer = page.getByLabel('Tu pedido')
  await expect(composer).toBeInViewport()

  const card = log.getByRole('group', { name: 'Cambio para confirmar' })
  await expect(card.getByText('Todavía no se hizo. Confirmalo para que pase.')).toBeVisible()
  await card.getByRole('button', { name: 'Confirmar' }).click()
  await expect(page.getByText('Listo, quedó hecho.')).toBeVisible()
  await expect(log.getByText('Hecho:')).toBeVisible()
  await expect(log.getByRole('button', { name: 'Confirmar' })).toHaveCount(0)
})
