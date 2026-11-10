import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import * as timerApi from '@/features/timer/timerApi'
import { buildTask, buildTimerState, page, taskStatuses } from '@/test/fixtures'
import { mockTaskCatalogs } from '@/test/mockTaskCatalogs'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import { MyTasksPage } from './MyTasksPage'
import * as tasksApi from './tasksApi'
import { defaultTaskFilters } from './types'
import { taskFiltersStorageKey } from './useTaskFilters'

function renderAs(userId: number, path = '/mis-tareas') {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ id: userId }))
  vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
  vi.spyOn(tasksApi, 'fetchTaskStatuses').mockResolvedValue(taskStatuses)
  mockTaskCatalogs()
  const fetchMine = vi.spyOn(tasksApi, 'fetchMyTasks').mockResolvedValue(page([buildTask({ id: 1, code: 'bhn-1', title: 'Mía' })]))
  return { ...renderRoutes([{ path: '/mis-tareas', element: <MyTasksPage /> }], path), fetchMine }
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
    await screen.findByRole('button', { name: 'Iniciar cronómetro en bhn-1' })
    await userEvent.click(screen.getByLabelText('Solo vencidas'))

    expect(JSON.parse(localStorage.getItem(taskFiltersStorageKey('mine', 1)) ?? '{}')).toMatchObject({ overdue: true })
    first.unmount()

    const again = renderAs(1)
    await screen.findByRole('button', { name: 'Iniciar cronómetro en bhn-1' })
    expect(screen.getByLabelText('Solo vencidas')).toBeChecked()
    await vi.waitFor(() => expect(again.fetchMine).toHaveBeenLastCalledWith({ ...defaultTaskFilters, overdue: true }))
    again.unmount()

    renderAs(2)
    await screen.findByText('Mía')
    await vi.waitFor(() => expect(screen.queryByRole('button', { name: 'Iniciar cronómetro en bhn-1' })).not.toBeInTheDocument())
    expect(screen.getByLabelText('Solo vencidas')).not.toBeChecked()
  })

  it.each(['{not json', '{"overdue":"yes","status_ids":["x"]}', '[1,2]'])('falls back to defaults on corrupted storage: %s', async (stored) => {
    localStorage.setItem(taskFiltersStorageKey('mine', 1), stored)
    const { fetchMine } = renderAs(1)

    expect(await screen.findByText('Mía')).toBeInTheDocument()
    expect(screen.getByLabelText('Solo vencidas')).not.toBeChecked()
    expect(fetchMine).toHaveBeenLastCalledWith(defaultTaskFilters)
  })

  it('opens on the overdue ones from the Panel link (?vencidas=1)', async () => {
    const { fetchMine } = renderAs(1, '/mis-tareas?vencidas=1')

    expect(await screen.findByLabelText('Solo vencidas')).toBeChecked()
    expect(fetchMine).toHaveBeenLastCalledWith({ ...defaultTaskFilters, overdue: true })
  })

  it('filters by category and label', async () => {
    const { fetchMine } = renderAs(1)
    await screen.findByRole('option', { name: 'Backend-API' })

    await userEvent.selectOptions(screen.getByLabelText('Categoría'), 'Backend-API')
    await userEvent.selectOptions(screen.getByLabelText('Etiqueta'), 'Deuda técnica')

    await vi.waitFor(() => expect(fetchMine).toHaveBeenLastCalledWith({ ...defaultTaskFilters, category_id: 1, label_id: 8 }))
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
})
