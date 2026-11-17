import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import * as assistantApi from './assistantApi'
import { AssistantPage } from './AssistantPage'
import type { AssistantMessage, AssistantProposal } from './types'

const question: AssistantMessage = {
  id: 1,
  conversation_id: 7,
  role: 'user',
  content: 'cargale 20 h a bhn',
  status: 'done',
  error: null,
  created_at: new Date().toISOString(),
  proposals: [],
  files: [],
}

const proposal: AssistantProposal = {
  id: 9,
  summary: 'Cargar 20h 00m a bhn «BHN» — motivo: Presupuesto #123',
  status: 'pending',
  error: null,
  expires_at: '2026-11-17T15:00:00+00:00',
  resolved_at: null,
}

const answer: AssistantMessage = {
  ...question,
  id: 2,
  role: 'assistant',
  content: 'Te dejé la propuesta para confirmar.\n\n| Proyecto | Disponibles |\n|---|---|\n| bhn | 10h 00m |',
  proposals: [proposal],
  files: [{ uuid: 'u1', name: 'horas-por-persona-2026-11-16.xlsx', format: 'xlsx', size: 6200, url: '/api/v1/assistant/files/u1', expires_at: '2026-11-23T00:00:00+00:00' }],
}

function renderPage(path = '/asistente', { enabled = true, permissions = ['assistant.use'], discordLinked = true } = {}) {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ permissions }))
  vi.spyOn(assistantApi, 'fetchConversations').mockResolvedValue({
    enabled,
    discordLinked,
    discordAvailable: true,
    conversations: [{ id: 7, title: 'cargale 20 h a bhn', channel: 'web', last_message_at: '2026-11-16T15:00:05+00:00' }],
  })
  renderRoutes([{ path: '/asistente', element: <AssistantPage /> }], path)
}

describe('AssistantPage', () => {
  it('asks a frequent question from an empty conversation and opens it (RF-14.01)', async () => {
    const ask = vi.spyOn(assistantApi, 'askAssistant').mockResolvedValue({ ...answer, status: 'pending', content: null, proposals: [], files: [] })
    vi.spyOn(assistantApi, 'fetchMessages').mockResolvedValue([question, { ...answer, status: 'pending', content: null, proposals: [], files: [] }])
    renderPage()

    await userEvent.click(await screen.findByRole('button', { name: '¿Qué bolsas se están por agotar?' }))

    expect(ask).toHaveBeenCalledWith({ text: '¿Qué bolsas se están por agotar?' })
    expect(await screen.findByText('Pensando…')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Enviar' })).toBeDisabled()
    expect(screen.getByText('Esperá a que termine la respuesta para seguir.')).toBeInTheDocument()
  })

  it('shows the answer with its table, the report to download and the change waiting for confirmation (RF-14.02/05)', async () => {
    vi.spyOn(assistantApi, 'fetchMessages').mockResolvedValue([question, answer])
    const resolve = vi.spyOn(assistantApi, 'resolveProposal').mockResolvedValue({ data: { ...proposal, status: 'confirmed' }, message: 'Listo, quedó hecho.', errors: null })
    renderPage('/asistente?c=7')

    const log = await screen.findByRole('log', { name: 'Conversación con el asistente' })
    expect(within(log).getByRole('table')).toBeInTheDocument()
    expect(within(log).getByRole('link', { name: /Descargar horas-por-persona-2026-11-16\.xlsx/ })).toHaveAttribute('href', '/api/v1/assistant/files/u1')

    const card = within(log).getByRole('group', { name: 'Cambio para confirmar' })
    expect(within(card).getByText('Todavía no se hizo. Confirmalo para que pase.')).toBeInTheDocument()
    await userEvent.click(within(card).getByRole('button', { name: 'Confirmar' }))

    expect(resolve).toHaveBeenCalledWith(proposal, 'confirm')
    expect(await screen.findByText('Listo, quedó hecho.')).toBeInTheDocument()
  })

  it('records how a proposal ended instead of offering it again', async () => {
    vi.spyOn(assistantApi, 'fetchMessages').mockResolvedValue([
      question,
      { ...answer, proposals: [{ ...proposal, status: 'failed', error: 'Tu rol no tiene permiso para cargar horas.' }] },
    ])
    renderPage('/asistente?c=7')

    expect(await screen.findByText('No se pudo hacer:')).toBeInTheDocument()
    expect(screen.getByText('Tu rol no tiene permiso para cargar horas.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Confirmar' })).not.toBeInTheDocument()
  })

  it('offers to ask again when an answer failed', async () => {
    vi.spyOn(assistantApi, 'fetchMessages').mockResolvedValue([question, { ...answer, status: 'failed', content: null, proposals: [], files: [], error: 'Tardé demasiado en responder. Probá con un pedido más concreto.' }])
    const ask = vi.spyOn(assistantApi, 'askAssistant').mockResolvedValue({ ...answer, id: 4, status: 'pending' })
    renderPage('/asistente?c=7')

    await userEvent.click(await screen.findByRole('button', { name: 'Volver a preguntar' }))

    expect(ask).toHaveBeenCalledWith({ text: 'cargale 20 h a bhn', conversation_id: 7 })
  })

  it('sends with Enter and keeps Shift+Enter for a new line', async () => {
    vi.spyOn(assistantApi, 'fetchMessages').mockResolvedValue([question, answer])
    const ask = vi.spyOn(assistantApi, 'askAssistant').mockResolvedValue({ ...answer, id: 4, status: 'pending' })
    renderPage('/asistente?c=7')

    const box = await screen.findByLabelText('Tu pedido')
    await userEvent.type(box, 'resumime{Shift>}{Enter}{/Shift}la semana{Enter}')

    expect(ask).toHaveBeenCalledWith({ text: 'resumime\nla semana', conversation_id: 7 })
  })

  it('says when the assistant is off and does not offer to write', async () => {
    renderPage('/asistente', { enabled: false })

    expect(await screen.findByText(/El asistente está apagado/)).toBeInTheDocument()
    expect(screen.queryByLabelText('Tu pedido')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: '¿Qué bolsas se están por agotar?' })).toBeDisabled()
  })
})

describe('AssistantPage, a slow answer', () => {
  it('says why it may be taking long instead of just "pensando…"', async () => {
    const old = new Date(Date.now() - 5 * 60_000).toISOString()
    vi.spyOn(assistantApi, 'fetchMessages').mockResolvedValue([question, { ...answer, status: 'running', content: null, proposals: [], files: [], created_at: old }])
    renderPage('/asistente?c=7')

    expect(await screen.findByText(/tarda más de lo normal/)).toBeInTheDocument()
  })
})

describe('AssistantPage, Discord', () => {
  it('gives an unlinked person the one-time /newdesk vincular command (RF-14.08)', async () => {
    const create = vi.spyOn(assistantApi, 'createDiscordLinkCode').mockResolvedValue({ code: 'ABCD2345', command: '/newdesk vincular codigo:ABCD2345', expires_in_minutes: 10 })
    renderPage('/asistente', { discordLinked: false })

    await userEvent.click(await screen.findByRole('button', { name: 'Conectar Discord' }))

    expect(create).toHaveBeenCalledOnce()
    const dialog = await screen.findByRole('dialog', { name: 'Conectar Discord' })
    expect(within(dialog).getByText('/newdesk vincular codigo:ABCD2345')).toBeInTheDocument()
    expect(within(dialog).getByText(/vence en 10 minutos/)).toBeInTheDocument()
  })

  it('does not offer it to someone already linked', async () => {
    renderPage('/asistente')

    expect(await screen.findByRole('button', { name: '¿Qué bolsas se están por agotar?' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Conectar Discord' })).not.toBeInTheDocument()
  })
})
