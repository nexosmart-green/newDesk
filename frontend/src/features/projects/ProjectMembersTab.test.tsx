import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as peopleApi from '@/features/people/peopleApi'
import { ApiError } from '@/lib/api'
import { buildPerson, buildProject, page } from '@/test/fixtures'
import { renderRoutes } from '@/test/renderWithProviders'
import { ProjectMembersTab } from './ProjectMembersTab'
import { ProjectPage } from './ProjectPage'
import * as projectsApi from './projectsApi'

const ana = buildPerson({ id: 1 })
const juan = buildPerson({ id: 2, first_name: 'Juan', last_name: 'Pérez' })
const pedro = buildPerson({ id: 3, first_name: 'Pedro', last_name: 'Gómez' })

function renderMembers() {
  vi.spyOn(projectsApi, 'fetchProject').mockResolvedValue(buildProject({ members: [ana, juan] }))
  vi.spyOn(peopleApi, 'fetchPeople').mockResolvedValue(page([ana, juan, pedro]))
  renderRoutes(
    [{ path: '/proyectos/:projectId', element: <ProjectPage />, children: [{ path: 'miembros', element: <ProjectMembersTab /> }] }],
    '/proyectos/9/miembros',
  )
}

describe('ProjectMembersTab', () => {
  it('adds a person who is not a member yet', async () => {
    const add = vi.spyOn(projectsApi, 'addProjectMember').mockResolvedValue(pedro)
    renderMembers()

    const select = await screen.findByLabelText('Agregar miembro')
    await screen.findByRole('option', { name: 'Pedro Gómez' })
    expect(screen.queryByRole('option', { name: 'Juan Pérez' })).not.toBeInTheDocument()
    await userEvent.selectOptions(select, 'Pedro Gómez')
    await userEvent.click(screen.getByRole('button', { name: 'Agregar' }))

    expect(add).toHaveBeenCalledWith(9, 3)
  })

  it('removes a member', async () => {
    const remove = vi.spyOn(projectsApi, 'removeProjectMember').mockResolvedValue('Miembro quitado.')
    renderMembers()

    await userEvent.click(await screen.findByRole('button', { name: 'Quitar a Juan Pérez' }))

    expect(remove).toHaveBeenCalledWith(9, 2)
  })

  it('explains why the leader cannot be removed (member_is_leader)', async () => {
    const message = 'Es el responsable del proyecto: asigná otro responsable antes de quitarlo.'
    vi.spyOn(projectsApi, 'removeProjectMember').mockRejectedValue(new ApiError(422, message, {}, 'member_is_leader'))
    renderMembers()

    expect(await screen.findByText('Responsable')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Quitar a Ana García' }))

    expect(await screen.findByRole('alert')).toHaveTextContent(message)
  })
})
