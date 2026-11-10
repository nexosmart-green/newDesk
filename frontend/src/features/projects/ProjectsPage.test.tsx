import { screen } from '@testing-library/react'
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

    expect(await screen.findByText('85% consumido')).toBeInTheDocument()
    expect(screen.getByRole('meter', { name: 'Horas consumidas' })).toHaveAttribute('data-level', 'warning')
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
