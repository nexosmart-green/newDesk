import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import * as projectsApi from '@/features/projects/projectsApi'
import * as timerApi from '@/features/timer/timerApi'
import { ApiError } from '@/lib/api'
import { buildPerson, buildProject, buildTask, buildTimerState, page, taskStatuses } from '@/test/fixtures'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import { TaskPage } from './TaskPage'
import * as tasksApi from './tasksApi'

const ana = buildPerson({ id: 1 })
const juan = buildPerson({ id: 2, first_name: 'Juan', last_name: 'Pérez' })

function renderTask() {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser())
  vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
  vi.spyOn(tasksApi, 'fetchTaskStatuses').mockResolvedValue(taskStatuses)
  vi.spyOn(tasksApi, 'fetchTaskComments').mockResolvedValue(page([]))
  vi.spyOn(projectsApi, 'fetchProject').mockResolvedValue(buildProject({ members: [ana, juan] }))
  renderRoutes([{ path: '/tareas/:code', element: <TaskPage /> }], '/tareas/bhn-60')
}

describe('TaskPage', () => {
  it('posts a comment with the @mentioned people (RF-04.06)', async () => {
    vi.spyOn(tasksApi, 'fetchTask').mockResolvedValue(buildTask({ description: null }))
    const post = vi.spyOn(tasksApi, 'postTaskComment').mockResolvedValue({
      id: 1,
      body: 'Mirá esto @Juan Pérez',
      author: { id: 1, full_name: 'Ana García' },
      mentions: [{ id: 2, full_name: 'Juan Pérez' }],
      created_at: null,
    })
    renderTask()

    const box = await screen.findByLabelText('Nuevo comentario')
    await screen.findByRole('button', { name: 'Iniciar cronómetro en bhn-60' })
    await userEvent.type(box, 'Mirá esto @Ju')
    await userEvent.click(await screen.findByRole('option', { name: 'Juan Pérez' }))
    expect(box).toHaveValue('Mirá esto @Juan Pérez ')
    await userEvent.click(screen.getByRole('button', { name: 'Comentar' }))

    expect(post).toHaveBeenCalledWith('bhn-60', { body: 'Mirá esto @Juan Pérez', mentioned_user_ids: [2] })
  })

  it('picks a mention with the keyboard', async () => {
    vi.spyOn(tasksApi, 'fetchTask').mockResolvedValue(buildTask())
    renderTask()

    const box = await screen.findByLabelText('Nuevo comentario')
    await userEvent.type(box, '@')
    await screen.findByRole('option', { name: 'Juan Pérez' })
    await userEvent.type(box, '{ArrowDown}{Enter}')

    expect(box).toHaveValue('@Juan Pérez ')
  })

  it('says "Tarea no encontrada" on 404', async () => {
    vi.spyOn(tasksApi, 'fetchTask').mockRejectedValue(new ApiError(404, 'No encontrado.'))
    renderTask()

    expect(await screen.findByRole('heading', { name: 'Tarea no encontrada' })).toBeInTheDocument()
  })

  it('changes the status from the task page', async () => {
    vi.spyOn(tasksApi, 'fetchTask').mockResolvedValue(buildTask())
    const update = vi.spyOn(tasksApi, 'updateTaskStatus').mockResolvedValue(buildTask())
    renderTask()

    const select = await screen.findByLabelText('Estado de bhn-60')
    await screen.findByRole('option', { name: 'En curso' })
    await userEvent.selectOptions(select, 'En curso')

    await vi.waitFor(() => expect(update).toHaveBeenCalledWith('bhn-60', 2))
  })
})
