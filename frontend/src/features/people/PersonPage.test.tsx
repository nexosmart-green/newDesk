import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import { buildProfile } from '@/test/fixtures'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import * as peopleApi from './peopleApi'
import { PersonPage } from './PersonPage'

function renderPerson(permissions: string[]) {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ id: 99, permissions }))
  vi.spyOn(peopleApi, 'fetchPerson').mockResolvedValue(buildProfile({ id: 4, first_name: 'Juan', last_name: 'Pérez', full_name: 'Juan Pérez' }))
  const fetchRates = vi.spyOn(peopleApi, 'fetchRates').mockResolvedValue([
    { id: 1, amount_cents: 1250000, currency: 'ARS', valid_from: '2026-10-01', valid_to: null },
  ])
  renderRoutes([{ path: '/personas/:personId', element: <PersonPage /> }], '/personas/4')
  return { fetchRates }
}

describe('PersonPage', () => {
  it('hides the rates without `rates.view` and never asks for them (CA-01.09)', async () => {
    const { fetchRates } = renderPerson([])

    expect(await screen.findByRole('heading', { name: 'Juan Pérez' })).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Tarifas' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Dar de baja' })).not.toBeInTheDocument()
    expect(fetchRates).not.toHaveBeenCalled()
  })

  it('shows the rate history formatted in the currency when allowed', async () => {
    renderPerson(['rates.view'])

    await userEvent.click(await screen.findByRole('tab', { name: 'Tarifas' }))

    expect(await screen.findByText(/12\.500,00/)).toBeInTheDocument()
    expect(screen.getByText('Vigente')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Registrar tarifa' })).not.toBeInTheDocument()
  })
})
