import { ListTodo } from 'lucide-react'
import { screen } from '@testing-library/react'
import { MODULES } from '@/features/auth/modules'
import { currentUserKey } from '@/features/auth/useAuth'
import { buildUser, createTestQueryClient, renderAt } from '@/test/renderWithProviders'
import { sectionTitle, useNavigation } from './navigation'
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

    for (const label of ['Inicio', 'Mis tareas', 'Tareas', 'Mi semana', 'Calendario', 'Proyectos', 'Clientes', 'Personas']) {
      expect(screen.getByRole('link', { name: label })).toBeInTheDocument()
    }
    expect(screen.getByRole('link', { name: 'Inicio' })).toHaveAttribute('href', '/')
    expect(screen.getByRole('link', { name: 'Mis tareas' })).toHaveAttribute('href', '/mis-tareas')
    expect(screen.getByRole('link', { name: 'Tareas' })).toHaveAttribute('href', '/tareas')
  })

  it('hides the entries of a module the role lacks', () => {
    renderNavFor([MODULES.projects, MODULES.clients, MODULES.people, MODULES.calendar, MODULES.reports])

    expect(screen.queryByRole('link', { name: 'Mi semana' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Mis tareas' })).toBeInTheDocument()
  })

  it('without the projects module, neither Mis tareas, Tareas nor Proyectos (Inicio stays)', () => {
    renderNavFor([MODULES.time, MODULES.people])

    expect(screen.getByRole('link', { name: 'Inicio' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Mis tareas' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Tareas' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Proyectos' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Clientes' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Mi semana' })).toBeInTheDocument()
  })
})

describe('sectionTitle', () => {
  it('names the list and a single task apart', () => {
    const groups = [{ label: 'Mi trabajo', items: [{ to: '/tareas', label: 'Tareas', icon: ListTodo }] }]
    expect(sectionTitle('/tareas', groups, [])).toBe('Tareas')
    expect(sectionTitle('/tareas/bhn-60', groups, [])).toBe('Tarea')
  })
})
