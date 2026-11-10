import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import * as peopleApi from '@/features/people/peopleApi'
import * as projectsApi from '@/features/projects/projectsApi'
import * as timerApi from '@/features/timer/timerApi'
import { buildPerson, buildProject, buildTask, buildTimerState, page, taskStatuses } from '@/test/fixtures'
import { mockTaskCatalogs } from '@/test/mockTaskCatalogs'
import { buildUser, renderAt } from '@/test/renderWithProviders'
import { AllTasksPage } from './AllTasksPage'
import * as tasksApi from './tasksApi'
import { defaultTaskFilters } from './types'

beforeEach(() => localStorage.clear())

function renderPage() {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser())
  vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
  vi.spyOn(tasksApi, 'fetchTaskStatuses').mockResolvedValue(taskStatuses)
  vi.spyOn(projectsApi, 'fetchProjects').mockResolvedValue(page([buildProject({ id: 9, name: 'Bahía Web' }), buildProject({ id: 12, name: 'Otro Cliente' })]))
  vi.spyOn(peopleApi, 'fetchPeople').mockResolvedValue(page([buildPerson({ id: 2, first_name: 'Juan', last_name: 'Pérez' })]))
  mockTaskCatalogs()
  const fetchAll = vi.spyOn(tasksApi, 'fetchAllTasks').mockResolvedValue({
    data: [
      buildTask({
        id: 1,
        code: 'bhn-1',
        title: 'Home nueva',
        logged_seconds: 5400,
        estimate_seconds: 3600,
        category: { id: 1, name: 'Backend-API' },
        labels: [{ id: 7, name: 'Cliente urgente', color: '#f27474' }],
        assignees: [{ id: 2, full_name: 'Juan Pérez', avatar: null }],
      }),
    ],
    meta: { current_page: 1, last_page: 3, per_page: 50, total: 120 },
  })
  renderAt(<AllTasksPage />, '/tareas')
  return fetchAll
}

describe('AllTasksPage', () => {
  it('lists tasks across projects with project, hours, category and labels', async () => {
    renderPage()

    const row = (await screen.findByText('Home nueva')).closest('li')
    expect(row).not.toBeNull()
    const item = within(row as HTMLElement)
    expect(item.getByText('Bahía Web')).toBeInTheDocument()
    expect(item.getByText('1h 30m')).toBeInTheDocument()
    expect(item.getByText('(supera la estimación)')).toBeInTheDocument()
    expect(item.getByText('Backend-API')).toBeInTheDocument()
    expect(item.getByText('Cliente urgente')).toBeInTheDocument()
    expect(screen.getByText('120 tareas')).toBeInTheDocument()
  })

  it('filters by project, person, category and label, and pages', async () => {
    const fetchAll = renderPage()
    await screen.findByText('Home nueva')
    await screen.findByRole('option', { name: 'Otro Cliente' })
    await screen.findByRole('option', { name: 'Juan Pérez' })

    await userEvent.selectOptions(screen.getByLabelText('Proyecto'), 'Otro Cliente')
    await userEvent.selectOptions(screen.getByLabelText('Asignado'), 'Juan Pérez')
    await userEvent.selectOptions(screen.getByLabelText('Categoría'), 'Backend-API')
    await userEvent.selectOptions(screen.getByLabelText('Etiqueta'), 'Cliente urgente')

    const expected = { ...defaultTaskFilters, project_id: 12, assignee_id: 2, category_id: 1, label_id: 7 }
    await vi.waitFor(() => expect(fetchAll).toHaveBeenLastCalledWith(expected, 1))

    await userEvent.click(screen.getByRole('button', { name: 'Siguiente' }))
    await vi.waitFor(() => expect(fetchAll).toHaveBeenLastCalledWith(expected, 2))
  })

  it('offers to clear the filters when nothing matches', async () => {
    renderPage()
    vi.spyOn(tasksApi, 'fetchAllTasks').mockResolvedValue(page([]))
    await screen.findByText('Home nueva')

    await userEvent.click(screen.getByLabelText('Solo vencidas'))

    expect(await screen.findByText('Ninguna tarea coincide con los filtros')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Limpiar filtros' }))
    expect(screen.getByLabelText('Solo vencidas')).not.toBeChecked()
  })
})
