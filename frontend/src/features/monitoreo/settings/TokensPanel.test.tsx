import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { currentUserKey } from '@/features/auth/useAuth'
import { teamAdmin } from '../test/fixtures/portal'
import { mockMonitoringApi, reply } from '../test/mockApi'
import { SettingsPage } from './SettingsPage'
import type { PersonalToken } from './useTokens'

// Configuración → Tokens (RF-12.02): el TokensPanel de monitoreo con los tokens personales de newDesk.
function renderSettings(permissions: string[]) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  client.setQueryData(currentUserKey, { ...teamAdmin, permissions })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/monitoreo/settings']}>
        <Routes>
          <Route path="/monitoreo/settings/:tab?" element={<SettingsPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

afterEach(() => vi.restoreAllMocks())

it('un dev con mcp.use crea un token, ve el token y el comando para Claude Code una sola vez, y lo revoca', async () => {
  const user = userEvent.setup()
  let tokens: PersonalToken[] = []
  const api = mockMonitoringApi({
    'GET me/tokens': () => tokens,
    'POST me/tokens': ({ body }) => {
      const created = { id: 7, name: (body as { name: string }).name, created_at: '2026-11-06T12:00:00Z', last_used_at: null, expires_at: '2027-02-04T12:00:00Z' }
      tokens = [created]
      return reply({ status: 201, data: { ...created, token: '7|nd_secreto' }, message: 'Token creado. Copialo ahora: no se vuelve a mostrar.' })
    },
    'DELETE me/tokens/:id': ({ params }) => {
      tokens = tokens.filter((token) => String(token.id) !== params.id)
      return reply({ message: 'Token revocado.' })
    },
  })
  renderSettings(['monitoring.view', 'mcp.use'])

  // Sin monitoring.manage no hay Servidores: solo sus tokens.
  expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['Tokens'])
  expect(await screen.findByText('Todavía no creaste tokens.')).toBeInTheDocument()

  await user.type(screen.getByLabelText('Nombre'), 'Claude de Iña')
  await user.selectOptions(screen.getByLabelText('Vence'), '90 días')
  await user.click(screen.getByRole('button', { name: 'Crear token' }))
  expect(api.post).toHaveBeenCalledWith('/me/tokens', { name: 'Claude de Iña', expires_in_days: 90 })

  const shown = await screen.findByRole('region', { name: 'Token Claude de Iña' })
  expect(shown).toHaveTextContent('Copialo ahora: no se vuelve a mostrar.')
  expect(within(shown).getByText('7|nd_secreto')).toBeInTheDocument()
  expect(
    within(shown).getByText(`claude mcp add --transport http monitoreo ${window.location.origin}/mcp/monitoreo --header "Authorization: Bearer 7|nd_secreto"`),
  ).toBeInTheDocument()
  expect(screen.getByLabelText('Nombre')).toHaveValue('')
  expect(await screen.findByText('Tokens de API · 1')).toBeInTheDocument()

  await user.click(screen.getByRole('button', { name: 'Revocar' }))
  await user.click(screen.getByRole('button', { name: '¿Seguro? Claude deja de tener acceso' }))
  expect(api.delete).toHaveBeenCalledWith('/me/tokens/7')
  expect(await screen.findByText('Todavía no creaste tokens.')).toBeInTheDocument()
  expect(screen.queryByRole('region', { name: 'Token Claude de Iña' })).not.toBeInTheDocument()
})

it('muestra por qué no se pudo crear', async () => {
  const user = userEvent.setup()
  mockMonitoringApi({
    'GET me/tokens': [],
    'POST me/tokens': reply({ status: 422, message: 'Los datos enviados no son válidos.', errors: { name: ['Poné un nombre (hasta 80 caracteres), por ejemplo "Claude de Iña".'] } }),
  })
  renderSettings(['mcp.use'])

  await user.type(screen.getByLabelText('Nombre'), 'x')
  await user.click(screen.getByRole('button', { name: 'Crear token' }))
  expect(await screen.findByText('Poné un nombre (hasta 80 caracteres), por ejemplo "Claude de Iña".')).toBeInTheDocument()
  expect(screen.getByLabelText('Nombre')).toHaveAttribute('aria-invalid', 'true')
})

it('el admin sin mcp.use ve solo Servidores', () => {
  mockMonitoringApi({ 'GET servers': [] })
  renderSettings(['monitoring.view', 'monitoring.manage'])
  expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['Servidores'])
})
