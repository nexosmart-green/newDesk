import { screen } from '@testing-library/react'
import { MODULES } from '@/features/auth/modules'
import { currentUserKey } from '@/features/auth/useAuth'
import { buildUser, createTestQueryClient, renderAt } from '@/test/renderWithProviders'
import { useNavigation } from './navigation'
import { SidebarNav } from './SidebarNav'

function Nav() {
  const { groups, footer } = useNavigation()
  return <SidebarNav groups={groups} footer={footer} collapsed={false} />
}

function renderNavFor(modules: (typeof MODULES)[keyof typeof MODULES][]) {
  const client = createTestQueryClient()
  client.setQueryData(currentUserKey, buildUser({ permissions: ['clients.manage', 'reports.view'] }, modules))
  renderAt(<Nav />, '/', client)
}

describe('navigation (module switches)', () => {
  it('shows every module the role has', () => {
    renderNavFor(Object.values(MODULES))

    for (const label of ['Mis tareas', 'Mi semana', 'Calendario', 'Proyectos', 'Clientes', 'Personas']) {
      expect(screen.getByRole('link', { name: label })).toBeInTheDocument()
    }
  })

  it('hides the entries of a module the role lacks', () => {
    renderNavFor([MODULES.projects, MODULES.clients, MODULES.people, MODULES.calendar, MODULES.reports])

    expect(screen.queryByRole('link', { name: 'Mi semana' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Mis tareas' })).toBeInTheDocument()
  })

  it('without the projects module, neither Mis tareas nor Proyectos', () => {
    renderNavFor([MODULES.time, MODULES.people])

    expect(screen.queryByRole('link', { name: 'Mis tareas' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Proyectos' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Clientes' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Mi semana' })).toBeInTheDocument()
  })
})
