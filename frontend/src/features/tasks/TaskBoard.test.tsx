import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as timerApi from '@/features/timer/timerApi'
import { ApiError } from '@/lib/api'
import { buildTask, buildTimerState, page, taskStatuses } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import { TaskBoard } from './TaskBoard'
import * as tasksApi from './tasksApi'
import { defaultTaskFilters, type Task } from './types'
import { useProjectTasks, useTaskStatuses } from './useTasks'

const first = buildTask({ id: 60, code: 'bhn-60', title: 'Arreglar el login' })
const second = buildTask({ id: 61, code: 'bhn-61', title: 'Maquetar home' })

/** The board over the real list query, as ProjectTasksTab renders it. */
function Board() {
  const tasks = useProjectTasks(9, defaultTaskFilters)
  const statuses = useTaskStatuses()
  if (!tasks.data || !statuses.data) return null
  return <TaskBoard tasks={tasks.data.data} statuses={statuses.data} filters={defaultTaskFilters} userId={undefined} />
}

function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason: unknown) => void
  const promise = new Promise<T>((onResolve, onReject) => {
    resolve = onResolve
    reject = onReject
  })
  return { promise, resolve, reject }
}

function renderBoard() {
  vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
  vi.spyOn(tasksApi, 'fetchTaskStatuses').mockResolvedValue(taskStatuses)
  vi.spyOn(tasksApi, 'fetchProjectTasks').mockResolvedValue(page([first, second]))
  renderAt(<Board />)
}

const column = (name: string) => screen.getByRole('region', { name: `Columna ${name}` })

describe('TaskBoard', () => {
  it('moves the card at once and only that card waits for the server', async () => {
    const saving = deferred<Task>()
    vi.spyOn(tasksApi, 'updateTaskStatus').mockReturnValue(saving.promise)
    renderBoard()

    await userEvent.selectOptions(await screen.findByLabelText('Estado de bhn-60'), 'En curso')

    expect(within(column('En curso')).getByText('Arreglar el login')).toBeInTheDocument()
    expect(within(column('Pendiente')).queryByText('Arreglar el login')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Estado de bhn-60')).toBeDisabled()
    expect(screen.getByLabelText('Estado de bhn-61')).toBeEnabled()

    saving.resolve({ ...first, status: taskStatuses[1] })
    expect(await screen.findByText('Estado actualizado.')).toBeInTheDocument()
    await vi.waitFor(() => expect(screen.getByLabelText('Estado de bhn-60')).toBeEnabled())
  })

  it('puts the card back and says why when the server refuses', async () => {
    const message = 'La tarea tiene subtareas pendientes.'
    vi.spyOn(tasksApi, 'updateTaskStatus').mockRejectedValue(new ApiError(422, message, {}, 'rule'))
    renderBoard()

    await userEvent.selectOptions(await screen.findByLabelText('Estado de bhn-60'), 'Completada')

    expect(await within(screen.getByRole('region', { name: 'Avisos' })).findByText(message)).toBeInTheDocument()
    expect(within(column('Pendiente')).getByText('Arreglar el login')).toBeInTheDocument()
    expect(screen.getByLabelText('Estado de bhn-60')).toHaveValue('1')
  })

  it('a refused move does not undo another card moved meanwhile', async () => {
    const refused = deferred<Task>()
    const secondMoved = { ...second, status: taskStatuses[1] }
    vi.spyOn(tasksApi, 'updateTaskStatus').mockImplementation((code) => (code === 'bhn-60' ? refused.promise : Promise.resolve(secondMoved)))
    renderBoard()
    // What the server holds once the second move is saved.
    vi.spyOn(tasksApi, 'fetchProjectTasks').mockResolvedValue(page([first, secondMoved]))

    await userEvent.selectOptions(await screen.findByLabelText('Estado de bhn-60'), 'En curso')
    await userEvent.selectOptions(screen.getByLabelText('Estado de bhn-61'), 'En curso')
    // While the first is still on its way, no refetch puts it back in its old column.
    expect(within(column('En curso')).getByText('Arreglar el login')).toBeInTheDocument()
    refused.reject(new ApiError(422, 'No se pudo.', {}, 'rule'))

    expect(await within(column('Pendiente')).findByText('Arreglar el login')).toBeInTheDocument()
    expect(within(column('En curso')).getByText('Maquetar home')).toBeInTheDocument()
  })
})
