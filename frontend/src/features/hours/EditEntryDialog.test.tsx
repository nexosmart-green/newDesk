import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { currentUserKey } from '@/features/auth/useAuth'
import * as projectsApi from '@/features/projects/projectsApi'
import * as tasksApi from '@/features/tasks/tasksApi'
import { defaultTaskFilters } from '@/features/tasks/types'
import * as timerApi from '@/features/timer/timerApi'
import { buildProject, buildTask, buildTimeEntry, page } from '@/test/fixtures'
import { buildUser, createTestQueryClient, renderRoutes } from '@/test/renderWithProviders'
import { EditEntryDialog } from './EditEntryDialog'

const entry = {
  ...buildTimeEntry({ is_running: false, comment: 'Revisé el formulario de login', user: { id: 2, full_name: 'Juan Pérez' } }),
  ended_at: '2026-11-06T15:00:00+00:00',
}

function renderDialog() {
  vi.spyOn(projectsApi, 'fetchProjects').mockResolvedValue(page([buildProject(), buildProject({ id: 12, name: 'Interno' })]))
  const fetchTasks = vi.spyOn(tasksApi, 'fetchProjectTasks').mockImplementation(async (projectId) =>
    projectId === 12
      ? page([buildTask({ id: 80, code: 'int-80', title: 'Soporte' })])
      : page([buildTask(), buildTask({ id: 61, code: 'bhn-61', title: 'Otra' })]),
  )
  const correct = vi.spyOn(timerApi, 'correctTimeEntry').mockResolvedValue(entry)
  const client = createTestQueryClient()
  client.setQueryData(currentUserKey, buildUser())
  renderRoutes([{ path: '/', element: <EditEntryDialog entry={entry} onClose={() => {}} /> }], '/', client)
  return { correct, fetchTasks }
}

describe('EditEntryDialog: moving an entry to another task', () => {
  it('offers only tasks assigned to the entry owner and sends task_id when it changed', async () => {
    const { correct, fetchTasks } = renderDialog()

    await userEvent.selectOptions(screen.getByLabelText('Tarea'), await screen.findByRole('option', { name: 'BHN-61 · Otra' }))
    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(fetchTasks).toHaveBeenCalledWith(9, { ...defaultTaskFilters, assignee_id: 2 })
    expect(correct).toHaveBeenCalledWith(300, { task_id: 61, comment: 'Revisé el formulario de login' })
  })

  it('changing the project asks for a task of that project before saving', async () => {
    const { correct } = renderDialog()

    await userEvent.selectOptions(screen.getByLabelText('Proyecto'), await screen.findByRole('option', { name: 'Interno' }))
    expect(screen.getByRole('button', { name: 'Guardar' })).toBeDisabled()
    await userEvent.selectOptions(screen.getByLabelText('Tarea'), await screen.findByRole('option', { name: 'INT-80 · Soporte' }))
    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(correct).toHaveBeenCalledWith(300, { task_id: 80, comment: 'Revisé el formulario de login' })
  })

  it('a comment-only fix sends neither times nor task', async () => {
    const { correct } = renderDialog()

    await userEvent.type(screen.getByLabelText('Comentario'), ' y el logout')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(correct).toHaveBeenCalledWith(300, { comment: 'Revisé el formulario de login y el logout' })
  })
})
