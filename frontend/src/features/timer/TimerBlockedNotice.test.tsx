import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as projectsApi from '@/features/projects/projectsApi'
import * as tasksApi from '@/features/tasks/tasksApi'
import { ApiError } from '@/lib/api'
import { buildDayClock, buildTimerState, page } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import { StartTimerButton } from './StartTimerButton'
import * as timerApi from './timerApi'

const task = { code: 'bhn-60', title: 'Arreglar el login', project: { id: 9, hours_exhausted: false } }

async function refuseStart(code: string, dayClock = buildDayClock()) {
  vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState({ day_clock: dayClock }))
  vi.spyOn(timerApi, 'startTimer').mockRejectedValue(new ApiError(422, 'Mensaje del servidor', {}, code))
  vi.spyOn(projectsApi, 'fetchProjects').mockResolvedValue(page([]))
  vi.spyOn(tasksApi, 'fetchMyTasks').mockResolvedValue(page([]))
  const { client } = renderAt(<StartTimerButton task={task} />)
  await vi.waitFor(() => expect(client.getQueryData(['timer'])).toBeDefined())
  await userEvent.click(screen.getByRole('button', { name: 'Iniciar cronómetro en bhn-60' }))
  return screen.findByRole('dialog', { name: 'No se pudo iniciar el cronómetro' })
}

describe('▶ refused by the day clock (RF-05.19)', () => {
  it('while the day clock runs, says to stop it first', async () => {
    const dialog = await refuseStart('day_clock_running', buildDayClock({ status: 'running', ended_at: null }))

    expect(within(dialog).getByRole('alert')).toHaveTextContent('Frená el reloj del día antes de usar el ▶')
    expect(within(dialog).queryByRole('button', { name: 'Repartir día' })).not.toBeInTheDocument()
  })

  it('with a day still to split, says so and opens the split from there', async () => {
    const dialog = await refuseStart('day_split_pending')

    expect(within(dialog).getByRole('alert')).toHaveTextContent('Repartí el día pendiente antes de seguir')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Repartir día' }))

    expect(await screen.findByRole('dialog', { name: 'Repartir el día' })).toBeInTheDocument()
  })
})
