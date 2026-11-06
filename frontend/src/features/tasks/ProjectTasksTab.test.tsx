import { fireEvent, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import * as hoursApi from '@/features/hours/hoursApi'
import { ProjectPage } from '@/features/projects/ProjectPage'
import * as projectsApi from '@/features/projects/projectsApi'
import * as timerApi from '@/features/timer/timerApi'
import { ApiError } from '@/lib/api'
import { buildProject, buildTask, buildTimerState, page, taskStatuses } from '@/test/fixtures'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import { ProjectTasksTab } from './ProjectTasksTab'
import * as tasksApi from './tasksApi'
import type { SaveTaskInput } from './types'

const task = buildTask()

function renderTab(tasks = [task]) {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser())
  vi.spyOn(projectsApi, 'fetchProject').mockResolvedValue(buildProject())
  vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
  vi.spyOn(tasksApi, 'fetchTaskStatuses').mockResolvedValue(taskStatuses)
  vi.spyOn(tasksApi, 'fetchProjectTasks').mockResolvedValue(page(tasks))
  renderRoutes(
    [{ path: '/proyectos/:projectId', element: <ProjectPage />, children: [{ path: 'tareas', element: <ProjectTasksTab /> }] }],
    '/proyectos/9/tareas',
  )
}

const newTaskInput: SaveTaskInput = {
  title: 'Nueva home',
  description: null,
  priority: 'medium',
  starts_on: null,
  due_on: null,
  estimate_seconds: null,
  assignee_ids: [],
}

async function fillNewTask() {
  await userEvent.click(await screen.findByRole('button', { name: 'Nueva tarea' }))
  await userEvent.type(screen.getByLabelText('Título'), 'Nueva home')
}

beforeEach(() => localStorage.clear())

// Typing through dialogs is slow when the whole suite runs in parallel.
describe('ProjectTasksTab', () => {
  it('keeps the task and explains why the timer did not start (CA-04.02b)', async () => {
    const message = 'Sin horas disponibles en Bahía Web: pedí una ampliación.'
    vi.spyOn(tasksApi, 'createAndStartTask').mockResolvedValue({
      task: buildTask({ id: 61, code: 'bhn-61', title: 'Nueva home' }),
      time_entry: null,
      timer_error: { code: 'no_hours_available', message, project_id: 9 },
    })
    const request = vi.spyOn(hoursApi, 'requestExtension').mockResolvedValue({
      id: 1,
      status: 'pending',
      message: null,
      project: { id: 9, name: 'Bahía Web' },
      requested_by: { id: 1, full_name: 'Ana García' },
      created_at: null,
    })
    renderTab()

    await fillNewTask()
    await userEvent.click(screen.getByRole('button', { name: /Crear e iniciar/ }))

    const dialog = await screen.findByRole('dialog', { name: 'Tarea creada' })
    expect(dialog).toHaveTextContent('bhn-61')
    expect(within(dialog).getByRole('alert')).toHaveTextContent(message)
    await userEvent.click(within(dialog).getByRole('button', { name: 'Pedir ampliación' }))
    expect(request).toHaveBeenCalledWith(9, undefined)
    expect(await screen.findByText('Pedido de ampliación enviado.')).toBeInTheDocument()
  })

  it('asks the running timer comment and retries "Crear e iniciar" with it (CA-04.02)', async () => {
    const createAndStart = vi
      .spyOn(tasksApi, 'createAndStartTask')
      .mockRejectedValueOnce(new ApiError(422, 'Contá qué hiciste.', {}, 'previous_comment_required'))
      .mockResolvedValueOnce({ task: buildTask({ code: 'bhn-61' }), time_entry: null, timer_error: null })
    renderTab()

    await fillNewTask()
    await userEvent.click(screen.getByRole('button', { name: /Crear e iniciar/ }))
    const comment = await screen.findByLabelText('¿Qué hiciste?')
    await userEvent.type(comment, 'Maqueté el header')
    await userEvent.click(screen.getByRole('button', { name: 'Detener y crear' }))

    expect(createAndStart).toHaveBeenLastCalledWith(9, newTaskInput, 'Maqueté el header')
    expect(createAndStart).toHaveBeenCalledTimes(2)
    await vi.waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('moves a card by drag & drop and by its status select (keyboard)', async () => {
    const updateStatus = vi.spyOn(tasksApi, 'updateTaskStatus').mockResolvedValue(task)
    renderTab()

    await userEvent.click(await screen.findByRole('button', { name: 'Tablero' }))
    const target = await screen.findByRole('region', { name: 'Columna En curso' })
    expect(screen.queryByRole('region', { name: 'Columna Completada' })).not.toBeInTheDocument()

    const card = within(screen.getByRole('region', { name: 'Columna Pendiente' })).getByText('Arreglar el login')
    let dragged = ''
    const dataTransfer = { setData: (_type: string, value: string) => (dragged = value), getData: () => dragged }
    fireEvent.dragStart(card.closest('li') as HTMLElement, { dataTransfer })
    fireEvent.dragOver(target, { dataTransfer })
    fireEvent.drop(target, { dataTransfer })
    await vi.waitFor(() => expect(updateStatus).toHaveBeenCalledWith('bhn-60', 2))

    await userEvent.selectOptions(screen.getByLabelText('Estado de bhn-60'), 'Bloqueado')
    await vi.waitFor(() => expect(updateStatus).toHaveBeenLastCalledWith('bhn-60', 3))
  })

  it('shows overdue tasks with a "Vencida" label, not only color', async () => {
    renderTab([buildTask({ due_on: '2026-11-01', is_overdue: true })])

    expect(await screen.findByText('Vencida')).toBeInTheDocument()
  })
})
