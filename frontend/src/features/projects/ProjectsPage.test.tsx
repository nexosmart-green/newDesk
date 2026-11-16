import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import { buildHourBag, buildProject, page } from '@/test/fixtures'
import { buildUser, renderAt } from '@/test/renderWithProviders'
import { ProjectsPage } from './ProjectsPage'
import * as projectsApi from './projectsApi'

describe('ProjectsPage', () => {
  beforeEach(() => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser())
  })

  it('shows the % consumed of each project (RF-03.07)', async () => {
    vi.spyOn(projectsApi, 'fetchProjects').mockResolvedValue(
      page([buildProject({ hours: buildHourBag({ percent_consumed: 85 }) })]),
    )
    renderAt(<ProjectsPage />, '/proyectos')

    expect(await screen.findByText('85%')).toBeInTheDocument()
    expect(screen.getByRole('meter', { name: 'Horas consumidas' })).toHaveAttribute('data-level', 'warning')
  })

  it('reads each row as structure: name then prefix, client below, faces for the people, status only when not active', async () => {
    const faces = [
      { id: 1, full_name: 'Ana García' },
      { id: 2, full_name: 'Juan Pérez' },
    ]
    vi.spyOn(projectsApi, 'fetchProjects').mockResolvedValue(
      page([
        buildProject({ id: 1, name: 'BH News', code_prefix: 'bhn', members_count: 2, member_faces: faces }),
        buildProject({ id: 2, name: 'Pausado', code_prefix: 'pau', status: 'paused', client: null, leader: null, members_count: 1, member_faces: [faces[1]!] }),
      ]),
    )
    renderAt(<ProjectsPage />, '/proyectos')

    const active = await screen.findByRole('link', { name: /BH News/ })
    expect(within(active).getByText('bhn')).toBeInTheDocument()
    expect(within(active).getByText('Responsable: Ana García')).toBeInTheDocument()
    expect(within(active).getByText('1 miembro: Juan Pérez')).toBeInTheDocument()
    expect(within(active).queryByText('Activo')).not.toBeInTheDocument()
    expect(active.textContent).not.toContain('·')

    const paused = screen.getByRole('link', { name: /Pausado/ })
    expect(within(paused).getByText('Sin cliente')).toBeInTheDocument()
    expect(within(paused).getByText('En pausa')).toBeInTheDocument()
    expect(within(paused).getByText('1 miembro: Juan Pérez')).toBeInTheDocument()
  })

  it('filters "Con horas por agotarse"', async () => {
    const fetchProjects = vi.spyOn(projectsApi, 'fetchProjects').mockResolvedValue(page([buildProject()]))
    renderAt(<ProjectsPage />, '/proyectos')

    await userEvent.click(await screen.findByLabelText('Con horas por agotarse'))

    await vi.waitFor(() =>
      expect(fetchProjects).toHaveBeenLastCalledWith(expect.objectContaining({ running_out: true, page: 1 })),
    )
  })
})
