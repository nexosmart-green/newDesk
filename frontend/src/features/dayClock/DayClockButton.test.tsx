import { act, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as projectsApi from '@/features/projects/projectsApi'
import * as tasksApi from '@/features/tasks/tasksApi'
import { GlobalTimer } from '@/features/timer/GlobalTimer'
import * as timerApi from '@/features/timer/timerApi'
import type { TimerState } from '@/features/timer/types'
import { buildDayClock, buildTimeEntry, buildTimerState, page } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import { DayClockButton } from './DayClockButton'

const runningDay = buildDayClock({ status: 'running', ended_at: null, elapsed_seconds: 3665 })

/** A fake server: whatever the day clock is right now. */
function serve(initial: TimerState) {
  const server = { state: initial }
  vi.spyOn(timerApi, 'fetchTimer').mockImplementation(async () => server.state)
  vi.spyOn(projectsApi, 'fetchProjects').mockResolvedValue(page([]))
  vi.spyOn(tasksApi, 'fetchMyTasks').mockResolvedValue(page([]))
  return server
}

describe('DayClockButton (RF-05.19)', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('idle: "Iniciar día" starts the clock and the header shows it running', async () => {
    const server = serve(buildTimerState())
    const start = vi.spyOn(timerApi, 'startDay').mockImplementation(async () => {
      server.state = buildTimerState({ day_clock: runningDay })
      return runningDay
    })
    renderAt(<DayClockButton />)

    await userEvent.click(await screen.findByRole('button', { name: 'Iniciar día' }))

    expect(start).toHaveBeenCalled()
    expect(await screen.findByRole('group', { name: 'Reloj del día' })).toBeInTheDocument()
  })

  it('running: ticks HH:MM:SS from the server figure; ■ stops it and opens the split at once', async () => {
    vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] })
    const server = serve(buildTimerState({ day_clock: runningDay }))
    const stop = vi.spyOn(timerApi, 'stopDay').mockImplementation(async () => {
      server.state = buildTimerState({ day_clock: buildDayClock() })
      return buildDayClock()
    })
    renderAt(<DayClockButton />)

    expect(await screen.findByRole('timer')).toHaveTextContent('01:01:05')
    act(() => vi.advanceTimersByTime(2000))
    expect(screen.getByRole('timer')).toHaveTextContent('01:01:07')

    await userEvent.click(screen.getByRole('button', { name: 'Frenar el reloj del día y repartirlo' }))

    expect(stop).toHaveBeenCalled()
    expect(await screen.findByRole('dialog', { name: 'Repartir el día' })).toBeInTheDocument()
  })

  it('pending: a warning pill that opens the split; "Repartir más tarde" keeps it pending', async () => {
    serve(buildTimerState({ day_clock: buildDayClock() }))
    renderAt(<DayClockButton />)

    await userEvent.click(await screen.findByRole('button', { name: 'Repartir día' }))
    await userEvent.click(await screen.findByRole('button', { name: 'Repartir más tarde' }))

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Repartir día' })).toBeInTheDocument()
  })

  it('is hidden while a ▶ timer runs (they never run together)', async () => {
    serve(buildTimerState({ active: buildTimeEntry() }))
    renderAt(
      <>
        <GlobalTimer />
        <DayClockButton />
      </>,
    )

    expect(await screen.findByRole('group', { name: 'Cronómetro en curso' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Iniciar día' })).not.toBeInTheDocument()
    expect(screen.queryByRole('group', { name: 'Reloj del día' })).not.toBeInTheDocument()
  })
})
