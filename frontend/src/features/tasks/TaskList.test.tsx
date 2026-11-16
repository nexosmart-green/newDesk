import { screen } from '@testing-library/react'
import * as timerApi from '@/features/timer/timerApi'
import { buildTask, buildTimeEntry, buildTimerState } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import { TaskList } from './TaskList'

const tasks = [buildTask(), buildTask({ id: 61, code: 'bhn-61', title: 'Maquetar home' })]

describe('TaskList', () => {
  it('marks the task being timed with the pink rule and its time instead of ▶', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState({ active: buildTimeEntry({ elapsed_seconds: 90 }) }))
    renderAt(<TaskList tasks={tasks} userId={1} />)

    const stop = await screen.findByRole('button', { name: 'Detener cronómetro de bhn-60' })
    await vi.waitFor(() => expect(stop).toHaveTextContent('00:01:30'))
    const [running, other] = Array.from(screen.getByRole('list', { name: 'Tareas' }).children)
    expect(running?.className).toContain('var(--color-live)')
    expect(other?.className).not.toContain('var(--color-live)')
    expect(screen.getByRole('button', { name: 'Iniciar cronómetro en bhn-61' })).toBeInTheDocument()
  })

  it('without matches says so with the shared empty state', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
    renderAt(<TaskList tasks={[]} userId={1} />)

    expect(screen.getByText('No hay tareas que coincidan con los filtros.')).toBeInTheDocument()
  })
})
