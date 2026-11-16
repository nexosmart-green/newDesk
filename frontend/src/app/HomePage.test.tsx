import { screen } from '@testing-library/react'
import { MODULES } from '@/features/auth/modules'
import { currentUserKey } from '@/lib/queryKeys'
import * as dashboardApi from '@/features/dashboard/dashboardApi'
import { buildUser, createTestQueryClient, renderAt } from '@/test/renderWithProviders'
import { HomePage } from './HomePage'

describe('HomePage', () => {
  it('when every Panel block comes back empty, welcomes with links to what the role has', async () => {
    vi.spyOn(dashboardApi, 'fetchDashboard').mockResolvedValue({ tasks: null, projects: null, week: null, bags_running_out: null })
    const client = createTestQueryClient()
    client.setQueryData(currentUserKey, buildUser({}, [MODULES.people]))
    renderAt(<HomePage />, '/', client)

    expect(screen.getByRole('heading', { name: 'Hola, Ana' })).toBeInTheDocument()
    expect(await screen.findByRole('link', { name: 'Personas' })).toHaveAttribute('href', '/personas')
    expect(screen.queryByRole('link', { name: 'Mis tareas' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Inicio' })).not.toBeInTheDocument()
  })
})
