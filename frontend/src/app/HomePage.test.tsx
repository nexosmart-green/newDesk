import { screen } from '@testing-library/react'
import { MODULES } from '@/features/auth/modules'
import { currentUserKey } from '@/features/auth/useAuth'
import { buildUser, createTestQueryClient, renderAt } from '@/test/renderWithProviders'
import { HomePage } from './HomePage'

describe('HomePage', () => {
  it('without the projects module welcomes with links to what the role has', () => {
    const client = createTestQueryClient()
    client.setQueryData(currentUserKey, buildUser({}, [MODULES.time, MODULES.people]))
    renderAt(<HomePage />, '/', client)

    expect(screen.getByRole('heading', { name: 'Hola, Ana' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Mi semana' })).toHaveAttribute('href', '/mi-semana')
    expect(screen.getByRole('link', { name: 'Personas' })).toHaveAttribute('href', '/personas')
    expect(screen.queryByText('Mis tareas')).not.toBeInTheDocument()
  })
})
