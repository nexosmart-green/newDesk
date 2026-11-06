import { act, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { buildTimeEntry, buildTimerState } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import { GlobalTimer } from './GlobalTimer'
import * as timerApi from './timerApi'
import { elapsedSince } from './useElapsedSeconds'

describe('GlobalTimer', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders HH:MM:SS from the server elapsed seconds and ticks every second (RF-05.01/03)', async () => {
    // Only the clock is faked: React Query and RTL keep their real setTimeout.
    vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] })
    vi.setSystemTime(new Date('2030-01-01T10:00:00Z')) // a wrong local clock must not matter
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState({ active: buildTimeEntry({ elapsed_seconds: 3665 }) }))

    renderAt(<GlobalTimer />)

    expect(await screen.findByRole('timer')).toHaveTextContent('01:01:05')
    expect(screen.getByRole('link', { name: 'bhn-60' })).toHaveAttribute('href', '/tareas/bhn-60')

    act(() => vi.advanceTimersByTime(2000))
    expect(screen.getByRole('timer')).toHaveTextContent('01:01:07')
  })

  it('renders nothing when no timer is running', async () => {
    const fetchTimer = vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
    renderAt(<GlobalTimer />)

    await vi.waitFor(() => expect(fetchTimer).toHaveBeenCalled())
    expect(screen.queryByRole('timer')).not.toBeInTheDocument()
  })

  it('only stops with a comment of at least 10 characters and sends it (RF-05.16)', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState({ active: buildTimeEntry() }))
    const stop = vi.spyOn(timerApi, 'stopTimer').mockResolvedValue(buildTimeEntry({ is_running: false }))
    renderAt(<GlobalTimer />)

    await userEvent.click(await screen.findByRole('button', { name: 'Detener cronómetro de bhn-60' }))
    const dialog = await screen.findByRole('dialog', { name: 'Detener cronómetro' })
    const comment = screen.getByLabelText('¿Qué hiciste?')
    const submit = screen.getByRole('button', { name: 'Detener' })

    await userEvent.type(comment, 'Login')
    expect(submit).toBeDisabled()
    expect(dialog).toHaveTextContent('faltan 5')
    await userEvent.click(submit)
    expect(stop).not.toHaveBeenCalled()

    await userEvent.type(comment, ' arreglado')
    expect(submit).toBeEnabled()
    await userEvent.click(submit)

    expect(stop).toHaveBeenCalledWith('Login arreglado')
  })
})

describe('elapsedSince', () => {
  it('adds only whole seconds since the response arrived, never negative', () => {
    expect(elapsedSince(100, 1_000, 3_999)).toBe(102)
    expect(elapsedSince(100, 5_000, 1_000)).toBe(100)
  })
})
