import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import * as aiConnectionsApi from './aiConnectionsApi'
import { AiConnectionsTab } from './AiConnectionsTab'
import type { AiToken, IssuedToken } from './types'

const existing: AiToken = {
  id: 1,
  name: 'Notebook',
  last_used_at: null,
  expires_at: null,
  created_at: '2026-11-01T12:00:00+00:00',
}

const command = 'claude mcp add --transport http newdesk https://desk.test/mcp --header "Authorization: Bearer nd_1|secret"'

const issued: IssuedToken = {
  token: { ...existing, id: 2, name: 'CLI' },
  plain_text_token: 'nd_1|secret',
  claude_code_command: command,
  project_mcp_json: {
    mcpServers: {
      newdesk: { type: 'http', url: 'https://desk.test/mcp', headers: { Authorization: 'Bearer ${NEWDESK_TOKEN}' } },
    },
  },
}

function renderTab(permissions: string[]) {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ permissions }))
  vi.spyOn(aiConnectionsApi, 'fetchMyAiTokens').mockResolvedValue({ endpoint: 'https://desk.test/mcp', tokens: [existing] })
  renderRoutes([{ path: '/perfil', element: <AiConnectionsTab /> }], '/perfil')
}

describe('AiConnectionsTab', () => {
  it('shows the new token and the claude mcp add command exactly once (RF-12.02/04)', async () => {
    const create = vi.spyOn(aiConnectionsApi, 'createAiToken').mockResolvedValue(issued)
    renderTab(['mcp.use'])

    await userEvent.click(await screen.findByRole('button', { name: 'Generar token' }))
    await userEvent.type(screen.getByLabelText('Nombre'), 'CLI')
    await userEvent.selectOptions(screen.getByLabelText('Vencimiento'), '30')
    await userEvent.click(screen.getByRole('button', { name: 'Generar' }))

    expect(create).toHaveBeenCalledWith({ name: 'CLI', expires_in_days: 30 })
    const dialog = await screen.findByRole('dialog', { name: 'Token «CLI» generado' })
    expect(within(dialog).getByText('nd_1|secret')).toBeInTheDocument()
    expect(within(dialog).getByText(command)).toBeInTheDocument()
    expect(within(dialog).getByText(/"Authorization": "Bearer \$\{NEWDESK_TOKEN\}"/)).toBeInTheDocument()
    expect(within(dialog).getByText(/Nunca commitees el token/)).toBeInTheDocument()
    expect(within(dialog).getByRole('button', { name: 'Copiar token' })).toBeInTheDocument()
    expect(within(dialog).getByRole('button', { name: 'Copiar comando de Claude Code' })).toBeInTheDocument()

    await userEvent.click(within(dialog).getByRole('button', { name: 'Ya lo guardé' }))

    expect(screen.queryByText('nd_1|secret')).not.toBeInTheDocument()
    expect(screen.queryByText(command)).not.toBeInTheDocument()
  })

  it('revokes a token only after confirming', async () => {
    const revoke = vi.spyOn(aiConnectionsApi, 'revokeAiToken').mockResolvedValue()
    renderTab(['mcp.use'])

    await userEvent.click(await screen.findByRole('button', { name: 'Revocar Notebook' }))
    expect(revoke).not.toHaveBeenCalled()
    await userEvent.click(within(screen.getByRole('dialog', { name: 'Revocar Notebook' })).getByRole('button', { name: 'Revocar' }))

    expect(revoke).toHaveBeenCalledWith(1)
  })

  it('explains instead of offering "Generar token" without mcp.use', async () => {
    renderTab([])

    expect(await screen.findByText(/Tu rol no tiene habilitado el uso de MCP/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Generar token' })).not.toBeInTheDocument()
    expect(await screen.findByText('Notebook')).toBeInTheDocument()
  })
})
