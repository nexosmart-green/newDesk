import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import * as projectsApi from '@/features/projects/projectsApi'
import * as timerApi from '@/features/timer/timerApi'
import { buildPerson, buildProject, buildTask, buildTimeEntry, buildTimerState, page, taskStatuses } from '@/test/fixtures'
import { buildUser, renderAt } from '@/test/renderWithProviders'
import { MyTasksPage } from './MyTasksPage'
import * as tasksApi from './tasksApi'
import { defaultTaskFilters } from './types'
import { taskFiltersStorageKey } from './useTaskFilters'

function renderAs(userId: number) {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ id: userId }))
  vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
  vi.spyOn(tasksApi, 'fetchTaskStatuses').mockResolvedValue(taskStatuses)
  const fetchMine = vi.spyOn(tasksApi, 'fetchMyTasks').mockResolvedValue(page([buildTask({ id: 1, code: 'bhn-1', title: 'Mía' })]))
  return { ...renderAt(<MyTasksPage />), fetchMine }
}

beforeEach(() => localStorage.clear())

describe('MyTasksPage', () => {
  it('lists my tasks across projects with their project', async () => {
    renderAs(1)

    expect(await screen.findByText('Mía')).toBeInTheDocument()
    expect(screen.getByText('Bahía Web')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Iniciar cronómetro en bhn-1' })).toBeInTheDocument()
  })

  it('remembers the filters per user (RF-04.05)', async () => {
    const first = renderAs(1)
    await screen.findByText('Hola, Ana')
    await userEvent.click(screen.getByLabelText('Solo vencidas'))

    expect(JSON.parse(localStorage.getItem(taskFiltersStorageKey('mine', 1)) ?? '{}')).toMatchObject({ overdue: true })
    first.unmount()

    const again = renderAs(1)
    await screen.findByText('Hola, Ana')
    expect(screen.getByLabelText('Solo vencidas')).toBeChecked()
    await vi.waitFor(() => expect(again.fetchMine).toHaveBeenLastCalledWith({ ...defaultTaskFilters, overdue: true }))
    again.unmount()

    renderAs(2)
    await screen.findByText('Hola, Ana')
    expect(screen.getByLabelText('Solo vencidas')).not.toBeChecked()
  })

  it.each(['{not json', '{"overdue":"yes","status_ids":["x"]}', '[1,2]'])('falls back to defaults on corrupted storage: %s', async (stored) => {
    localStorage.setItem(taskFiltersStorageKey('mine', 1), stored)
    const { fetchMine } = renderAs(1)

    expect(await screen.findByText('Mía')).toBeInTheDocument()
    expect(screen.getByLabelText('Solo vencidas')).not.toBeChecked()
    expect(fetchMine).toHaveBeenLastCalledWith(defaultTaskFilters)
  })

  it('still works when storage throws', async () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('blocked')
    })
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new Error('blocked')
    })
    renderAs(1)

    await userEvent.click(await screen.findByLabelText('Solo vencidas'))
    expect(screen.getByLabelText('Solo vencidas')).toBeChecked()
  })

  // CA-04.07b
  it('creates a task from here, in the project picked, and starts its timer', async () => {
    const team = [buildPerson({ id: 1 }), buildPerson({ id: 2, first_name: 'Beto' })]
    vi.spyOn(projectsApi, 'fetchProjects').mockResolvedValue(page([buildProject(), buildProject({ id: 10, name: 'Otro', code_prefix: 'otr' })]))
    vi.spyOn(projectsApi, 'fetchProject').mockResolvedValue(buildProject({ members: team }))
    const start = vi.spyOn(tasksApi, 'createAndStartTask').mockResolvedValue({
      task: buildTask({ id: 2, code: 'bhn-2', title: 'Arreglar el login' }),
      time_entry: buildTimeEntry(),
      timer_error: null,
    })
    renderAs(1)

    await userEvent.click(await screen.findByRole('button', { name: 'Nueva tarea' }))
    expect(screen.getByRole('button', { name: 'Crear e iniciar' })).toBeDisabled() // no project yet
    await userEvent.selectOptions(screen.getByLabelText('Proyecto'), 'Bahía Web (bhn)')
    // The person is ticked: the new task lands in their list, with its ▶.
    expect(await screen.findByLabelText('Ana García')).toBeChecked()
    expect(screen.getByLabelText('Beto García')).not.toBeChecked()
    await userEvent.type(screen.getByLabelText(/Título/), 'Arreglar el login')
    await userEvent.click(screen.getByRole('button', { name: 'Crear e iniciar' }))

    await vi.waitFor(() =>
      expect(start).toHaveBeenCalledWith(9, expect.objectContaining({ title: 'Arreglar el login', assignee_ids: [1] }), undefined),
    )
  })
})
