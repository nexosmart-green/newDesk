import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as hoursApi from '@/features/hours/hoursApi'
import { ApiError } from '@/lib/api'
import { buildTimeEntry, buildTimerState } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import { StartTimerButton } from './StartTimerButton'
import * as timerApi from './timerApi'

const task = { code: 'bhn-60', title: 'Arreglar el login' }
const otherEntry = buildTimeEntry({ task: { id: 12, code: 'bhn-12', title: 'Maquetar home' } })
const previousComment = 'Terminé la maqueta de la home'

function renderButton(hoursExhausted = false) {
  return renderAt(<StartTimerButton task={{ ...task, project: { id: 9, hours_exhausted: hoursExhausted } }} />)
}

function mockExtensionRequest() {
  return vi.spyOn(hoursApi, 'requestExtension').mockResolvedValue({
    id: 1,
    status: 'pending',
    message: null,
    project: { id: 9, name: 'Bahía Web' },
    requested_by: { id: 1, full_name: 'Ana García' },
    created_at: null,
  })
}

describe('StartTimerButton', () => {
  it('starts the timer on the task', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
    const start = vi.spyOn(timerApi, 'startTimer').mockResolvedValue(buildTimeEntry())
    renderButton()

    await userEvent.click(screen.getByRole('button', { name: 'Iniciar cronómetro en bhn-60' }))

    expect(start).toHaveBeenCalledWith('bhn-60', undefined)
  })

  it('with another timer running asks what was done there and starts with that comment (RF-05.02/16)', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState({ active: otherEntry }))
    const start = vi.spyOn(timerApi, 'startTimer').mockResolvedValue(buildTimeEntry())
    renderButton()

    await screen.findByRole('button', { name: 'Iniciar cronómetro en bhn-60' })
    await vi.waitFor(() => expect(timerApi.fetchTimer).toHaveBeenCalled())
    await userEvent.click(screen.getByRole('button', { name: 'Iniciar cronómetro en bhn-60' }))
    const dialog = await screen.findByRole('dialog', { name: 'Antes de cambiar de tarea' })
    expect(dialog).toHaveTextContent('bhn-12')
    await userEvent.type(within(dialog).getByLabelText('¿Qué hiciste?'), previousComment)
    await userEvent.click(within(dialog).getByRole('button', { name: 'Detener y empezar' }))

    expect(start).toHaveBeenCalledTimes(1)
    expect(start).toHaveBeenCalledWith('bhn-60', previousComment)
  })

  it('retries with the previous comment when the backend reports another running timer', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
    const start = vi
      .spyOn(timerApi, 'startTimer')
      .mockRejectedValueOnce(new ApiError(422, 'Contá qué hiciste.', {}, 'previous_comment_required'))
      .mockResolvedValueOnce(buildTimeEntry())
    renderButton()

    await userEvent.click(screen.getByRole('button', { name: 'Iniciar cronómetro en bhn-60' }))
    const dialog = await screen.findByRole('dialog', { name: 'Antes de cambiar de tarea' })
    await userEvent.type(within(dialog).getByLabelText('¿Qué hiciste?'), previousComment)
    await userEvent.click(within(dialog).getByRole('button', { name: 'Detener y empezar' }))

    expect(start).toHaveBeenNthCalledWith(1, 'bhn-60', undefined)
    expect(start).toHaveBeenNthCalledWith(2, 'bhn-60', previousComment)
    await vi.waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('without hours shows the reason and asks for an extension in one click (CA-05.05, RF-05.08)', async () => {
    const message = 'Sin horas disponibles en Bahía Web: pedí una ampliación.'
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
    vi.spyOn(timerApi, 'startTimer').mockRejectedValue(new ApiError(422, message, {}, 'no_hours_available'))
    const request = mockExtensionRequest()
    renderButton()

    await userEvent.click(screen.getByRole('button', { name: 'Iniciar cronómetro en bhn-60' }))
    const dialog = await screen.findByRole('dialog', { name: 'No se pudo iniciar el cronómetro' })
    expect(within(dialog).getByRole('alert')).toHaveTextContent(message)
    await userEvent.click(within(dialog).getByRole('button', { name: 'Pedir ampliación' }))

    expect(request).toHaveBeenCalledWith(9, undefined)
    expect(await within(dialog).findByText('Pedido de ampliación enviado.')).toBeInTheDocument()
  })

  it('on a project already out of hours says so before any click and asks for an extension (RF-05.05)', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
    const start = vi.spyOn(timerApi, 'startTimer')
    const request = mockExtensionRequest()
    renderButton(true)

    const play = screen.getByRole('button', { name: 'Iniciar cronómetro en bhn-60' })
    expect(play).toBeDisabled()
    expect(play).toHaveAccessibleDescription('Sin horas disponibles — pedir ampliación')
    await userEvent.click(screen.getByRole('button', { name: 'Pedir ampliación' }))

    expect(request).toHaveBeenCalledWith(9, undefined)
    expect(await screen.findByText('Pedido de ampliación enviado.')).toBeInTheDocument()
    expect(start).not.toHaveBeenCalled()
  })

  it('on the running task offers ■ and stops it with the comment', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState({ active: buildTimeEntry() }))
    const stop = vi.spyOn(timerApi, 'stopTimer').mockResolvedValue(buildTimeEntry({ is_running: false }))
    renderButton()

    await userEvent.click(await screen.findByRole('button', { name: 'Detener cronómetro de bhn-60' }))
    await userEvent.type(screen.getByLabelText('¿Qué hiciste?'), 'Arreglé el login')
    await userEvent.click(screen.getByRole('button', { name: 'Detener' }))

    expect(stop).toHaveBeenCalledWith('Arreglé el login')
  })
})
