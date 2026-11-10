import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import * as projectsApi from '@/features/projects/projectsApi'
import * as timerApi from '@/features/timer/timerApi'
import { buildProject, buildTask, buildTimerState, page, taskStatuses } from '@/test/fixtures'
import { mockTaskCatalogs } from '@/test/mockTaskCatalogs'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import { TaskPage } from './TaskPage'
import * as tasksApi from './tasksApi'
import type { Task, TaskTimeEntry } from './types'

const detailed = buildTask({
  description: '## Pasos\n\n- [ ] Revisar **login**\n\n[Spec](https://example.com/spec)\n\n![captura](/api/v1/task-images/abc)',
  logged_seconds: 9000,
  estimate_seconds: 18000,
  category: { id: 1, name: 'Backend-API' },
  labels: [{ id: 7, name: 'Cliente urgente', color: '#f27474' }],
  created_by: { id: 2, full_name: 'Juan Pérez' },
  created_at: '2026-11-09T13:05:00+00:00',
})

function entry(overrides: Partial<TaskTimeEntry> = {}): TaskTimeEntry {
  return {
    id: 1,
    user: { id: 1, full_name: 'Ana García', avatar: null },
    started_at: '2026-11-09T13:00:00+00:00',
    ended_at: '2026-11-09T15:30:00+00:00',
    seconds: 9000,
    is_running: false,
    comment: 'Corregí la validación',
    comment_pending: false,
    source: 'timer',
    ...overrides,
  }
}

function renderTask(task: Task = detailed, path = '/tareas/bhn-60') {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser())
  vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
  vi.spyOn(tasksApi, 'fetchTaskStatuses').mockResolvedValue(taskStatuses)
  vi.spyOn(tasksApi, 'fetchTask').mockResolvedValue(task)
  vi.spyOn(projectsApi, 'fetchProject').mockResolvedValue(buildProject())
  mockTaskCatalogs()
  return renderRoutes([{ path: '/tareas/:code', element: <TaskPage /> }], path)
}

describe('TaskPage tabs', () => {
  it('shows who created it and when, the logged hours, category and labels', async () => {
    renderTask()

    // Profile timezone (Buenos Aires), not the machine's: CI runs in UTC. Waits for the current user to load.
    await vi.waitFor(() => expect(screen.getByText(/Creada por Juan Pérez el/)).toHaveTextContent('Creada por Juan Pérez el 09/11/2026 10:05'))
    expect(screen.getAllByText('2h 30m').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Backend-API').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Cliente urgente').length).toBeGreaterThan(0)
    expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['Detalle', 'Comentarios', 'Horas', 'Historial'])
  })

  it('renders the description as safe markdown: links in a new tab, images by their authenticated URL', async () => {
    renderTask()

    expect(await screen.findByRole('heading', { name: 'Pasos' })).toBeInTheDocument()
    expect(screen.getByText('login').tagName).toBe('STRONG')
    expect(screen.getByRole('checkbox')).toBeDisabled()
    const link = screen.getByRole('link', { name: 'Spec' })
    expect(link).toHaveAttribute('target', '_blank')
    expect(link).toHaveAttribute('rel', 'noopener noreferrer')
    expect(screen.getByRole('img', { name: 'captura' })).toHaveAttribute('src', '/api/v1/task-images/abc')
  })

  it('never renders raw HTML from the description', async () => {
    renderTask(buildTask({ description: '<img src=x onerror="alert(1)"> <script>alert(2)</script> [x](javascript:alert(3))' }))

    await screen.findByRole('heading', { name: /arreglar el login/i })
    expect(document.querySelector('script')).toBeNull()
    expect(document.querySelector('img[onerror]')).toBeNull()
    expect(screen.queryByRole('link', { name: 'x' })?.getAttribute('href') ?? '').not.toContain('javascript')
  })

  it('edits category and labels in place and sends them', async () => {
    const update = vi.spyOn(tasksApi, 'updateTask').mockResolvedValue(detailed)
    renderTask()

    await userEvent.click(await screen.findByRole('button', { name: 'Editar detalles' }))
    const form = screen.getByRole('form', { name: 'Detalles de la tarea' })
    await within(form).findByRole('option', { name: 'Diseño' })
    await userEvent.selectOptions(within(form).getByLabelText('Categoría'), 'Diseño')
    await userEvent.click(within(form).getByRole('button', { name: 'Deuda técnica' }))
    await userEvent.click(within(form).getByRole('button', { name: 'Cliente urgente' }))
    await userEvent.click(within(form).getByRole('button', { name: 'Guardar cambios' }))

    expect(update).toHaveBeenCalledWith('bhn-60', expect.objectContaining({ category_id: 2, label_ids: [8] }))
    expect(await screen.findByRole('button', { name: 'Editar detalles' })).toBeInTheDocument()
  })

  it('lists the task hours with the whole-task total', async () => {
    const fetchEntries = vi.spyOn(tasksApi, 'fetchTaskTimeEntries').mockResolvedValue({
      data: [entry(), entry({ id: 2, is_running: true, ended_at: null, comment: null, seconds: 600 })],
      meta: { current_page: 1, last_page: 1, per_page: 50, total: 2, total_seconds: 9600 },
    })
    renderTask(detailed, '/tareas/bhn-60?tab=horas')

    expect(await screen.findByText('2h 40m')).toBeInTheDocument()
    expect(fetchEntries).toHaveBeenCalledWith('bhn-60', 1)
    const list = screen.getByRole('list', { name: 'Registros de horas' })
    expect(within(list).getByText('Corregí la validación')).toBeInTheDocument()
    expect(within(list).getByText('En curso')).toBeInTheDocument()
    expect(screen.getByText('Cargar horas a mano')).toBeInTheDocument()
  })

  it('explains an empty hours tab', async () => {
    vi.spyOn(tasksApi, 'fetchTaskTimeEntries').mockResolvedValue({ ...page([]), meta: { ...page([]).meta, total_seconds: 0 } })
    renderTask(detailed, '/tareas/bhn-60?tab=horas')

    expect(await screen.findByText('Todavía no hay horas en esta tarea')).toBeInTheDocument()
  })

  it('reads the history as sentences with each changed field', async () => {
    vi.spyOn(tasksApi, 'fetchTaskActivity').mockResolvedValue(
      page([
        {
          id: 2,
          event: 'task.updated',
          actor: { id: 1, full_name: 'Ana García' },
          source: 'web',
          token_name: null,
          before: { priority: 'medium', category: null },
          after: { priority: 'high', category: 'Backend-API', description_changed: true },
          created_at: '2026-11-10T12:00:00+00:00',
        },
        {
          id: 1,
          event: 'task.status_changed',
          actor: { id: 2, full_name: 'Juan Pérez' },
          source: 'mcp',
          token_name: 'Claude Code',
          before: { status: 'Pendiente' },
          after: { status: 'En curso' },
          created_at: '2026-11-09T12:00:00+00:00',
        },
      ]),
    )
    renderTask(detailed, '/tareas/bhn-60?tab=historial')

    expect(await screen.findByText('Ana García cambió la prioridad, la categoría y la descripción')).toBeInTheDocument()
    expect(screen.getByText('Media')).toBeInTheDocument()
    expect(screen.getByText('Alta')).toBeInTheDocument()
    expect(screen.queryByText('true')).not.toBeInTheDocument()
    expect(screen.getByText('Juan Pérez cambió el estado de «Pendiente» a «En curso»')).toBeInTheDocument()
    expect(screen.getByText(/vía MCP \(Claude Code\)/)).toBeInTheDocument()
  })
})
