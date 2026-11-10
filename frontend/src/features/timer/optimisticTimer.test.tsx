import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ApiError } from '@/lib/api'
import { buildDayClock, buildTimeEntry, buildTimerState } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import { GlobalTimer } from './GlobalTimer'
import { StartTimerButton } from './StartTimerButton'
import * as timerApi from './timerApi'

const task = { code: 'bhn-60', title: 'Arreglar el login', project: { id: 9, name: 'Bahía Web', hours_exhausted: false } }

function deferred<T>() {
  let resolve: (value: T) => void = () => {}
  let reject: (reason: unknown) => void = () => {}
  const promise = new Promise<T>((onResolve, onReject) => {
    resolve = onResolve
    reject = onReject
  })
  return { promise, resolve, reject }
}

function renderHeaderAndRow() {
  return renderAt(
    <>
      <GlobalTimer />
      <StartTimerButton task={task} />
    </>,
  )
}

describe('optimistic timer', () => {
  it('▶ shows the task in the header at once and rolls back when the server refuses', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
    const pending = deferred<never>()
    vi.spyOn(timerApi, 'startTimer').mockReturnValue(pending.promise)
    renderHeaderAndRow()

    await userEvent.click(await screen.findByRole('button', { name: 'Iniciar cronómetro en bhn-60' }))

    // Before the server answers: the header already runs, the row button is busy.
    expect(screen.getByRole('timer')).toHaveTextContent('00:00:00')
    expect(screen.getByRole('link', { name: 'bhn-60' })).toBeInTheDocument()
    for (const stop of screen.getAllByRole('button', { name: 'Detener cronómetro de bhn-60' })) {
      expect(stop).toHaveAttribute('aria-busy', 'true')
    }

    pending.reject(new ApiError(422, 'Sin horas disponibles en Bahía Web.', {}, 'no_hours_available'))

    const dialog = await screen.findByRole('dialog', { name: 'No se pudo iniciar el cronómetro' })
    expect(within(dialog).getByRole('alert')).toHaveTextContent('Sin horas disponibles en Bahía Web.')
    expect(screen.queryByRole('timer')).not.toBeInTheDocument()
  })

  it('▶ does not fake-start while a day clock is running or pending: the server will refuse (RF-05.19)', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState({ day_clock: buildDayClock({ status: 'running', ended_at: null }) }))
    const pending = deferred<never>()
    vi.spyOn(timerApi, 'startTimer').mockReturnValue(pending.promise)
    const { client } = renderHeaderAndRow()
    await vi.waitFor(() => expect(client.getQueryData(['timer'])).toBeDefined())

    await userEvent.click(screen.getByRole('button', { name: 'Iniciar cronómetro en bhn-60' }))

    expect(screen.queryByRole('timer')).not.toBeInTheDocument()
    pending.reject(new ApiError(422, 'Hay un reloj del día corriendo.', {}, 'day_clock_running'))
    expect(await screen.findByRole('dialog', { name: 'No se pudo iniciar el cronómetro' })).toBeInTheDocument()
  })

  it('■ clears the header on send and toasts the recorded duration', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState({ active: buildTimeEntry() }))
    const pending = deferred<ReturnType<typeof buildTimeEntry>>()
    vi.spyOn(timerApi, 'stopTimer').mockReturnValue(pending.promise)
    renderAt(<GlobalTimer />)

    await userEvent.click(await screen.findByRole('button', { name: 'Detener cronómetro de bhn-60' }))
    await userEvent.type(screen.getByLabelText('¿Qué hiciste?'), 'Arreglé el login')
    await userEvent.click(screen.getByRole('button', { name: 'Detener' }))

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(screen.queryByRole('timer')).not.toBeInTheDocument()
    pending.resolve(buildTimeEntry({ is_running: false, elapsed_seconds: 3900 }))

    expect(await screen.findByText('Registraste 1h 05m en bhn-60.')).toBeInTheDocument()
  })

  it('■ brings the timer back with the reason when the stop fails', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState({ active: buildTimeEntry() }))
    vi.spyOn(timerApi, 'stopTimer').mockRejectedValue(new ApiError(0, 'No se pudo conectar con el servidor'))
    renderAt(<GlobalTimer />)

    await userEvent.click(await screen.findByRole('button', { name: 'Detener cronómetro de bhn-60' }))
    await userEvent.type(screen.getByLabelText('¿Qué hiciste?'), 'Arreglé el login')
    await userEvent.click(screen.getByRole('button', { name: 'Detener' }))

    expect(await screen.findByText('El cronómetro sigue corriendo')).toBeInTheDocument()
    expect(screen.getByText('Revisá tu conexión a internet y probá de nuevo.')).toBeInTheDocument()
    expect(screen.getByRole('timer')).toBeInTheDocument()
  })
})
