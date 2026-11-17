import { fireEvent, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { currentUserKey } from '@/features/auth/useAuth'
import * as projectsApi from '@/features/projects/projectsApi'
import * as tasksApi from '@/features/tasks/tasksApi'
import * as timerApi from '@/features/timer/timerApi'
import { ApiError } from '@/lib/api'
import { buildDayClock, buildHourBag, buildProject, buildTask, page } from '@/test/fixtures'
import { buildUser, createTestQueryClient, renderAt } from '@/test/renderWithProviders'
import { SplitDayDialog } from './SplitDayDialog'

const bahia = buildProject({ id: 9, name: 'Bahía Web', code_prefix: 'bhn', hours: buildHourBag({ available_seconds: 3600 }) })
const tol = buildProject({ id: 12, name: 'TOL App', code_prefix: 'tol', client: null, hours: buildHourBag({ available_seconds: 360_000 }) })
const comment = 'Arreglé el login con Google'

function renderDialog(props: { onClose?: () => void; onDone?: () => void } = {}) {
  vi.spyOn(projectsApi, 'fetchProjects').mockResolvedValue(page([bahia, tol]))
  vi.spyOn(tasksApi, 'fetchMyTasks').mockResolvedValue(
    page([
      buildTask({ id: 60, code: 'bhn-60', title: 'Arreglar el login' }),
      buildTask({ id: 70, code: 'tol-7', title: 'Ranking', project: { id: 12, name: 'TOL App', code_prefix: 'tol', hours_exhausted: false } }),
    ]),
  )
  const client = createTestQueryClient()
  client.setQueryData(currentUserKey, buildUser()) // Buenos Aires: the fixture day is 09:02:37 → 18:14
  const onClose = props.onClose ?? vi.fn()
  renderAt(<SplitDayDialog dayClock={buildDayClock()} onClose={onClose} onDone={props.onDone} />, '/', client)
  return { onClose }
}

const card = (number: number) => screen.getByRole('article', { name: new RegExp(`^Parte ${number} ·`) })

async function fillPart(number: number, projectName: string, taskName: string, text = comment) {
  const part = within(card(number))
  await userEvent.selectOptions(part.getByLabelText('Proyecto'), await part.findByRole('option', { name: projectName }))
  await userEvent.selectOptions(part.getByLabelText('Tarea'), await part.findByRole('option', { name: taskName }))
  await userEvent.type(part.getByLabelText('¿Qué hiciste?'), text)
}

describe('SplitDayDialog (RF-05.19)', () => {
  it('starts with one part for the whole day and sends offsets, tasks and new task titles', async () => {
    const split = vi.spyOn(timerApi, 'splitDay').mockResolvedValue({
      data: { entries: [], overdrawn: [{ project_id: 9, name: 'Bahía Web', seconds: -5663 }] },
      message: 'Día repartido en 2 partes.',
      errors: null,
    })
    const onDone = vi.fn()
    renderDialog({ onDone })

    const dialog = await screen.findByRole('dialog', { name: 'Repartir el día' })
    expect(dialog).toHaveTextContent('Martes 10/11 · de 09:02 a 18:14 · 9h 11m en el reloj')
    expect(within(card(1)).getByLabelText('Desde')).toHaveValue('09:02')
    expect(within(card(1)).getByLabelText('Hasta')).toHaveValue('18:14')

    // 11:37 ends part 1; the rest becomes a gap that one click turns into part 2.
    const until = within(card(1)).getByLabelText('Hasta')
    await userEvent.clear(until)
    await userEvent.type(until, '11:37')
    await userEvent.tab()
    expect(dialog).toHaveTextContent('6h 37m sin asignar: no se cuentan')
    await userEvent.click(screen.getByRole('button', { name: 'Sumar una parte de 11:37 a 18:14' }))

    await fillPart(1, 'Bahía Web (bhn) · Bahía', 'bhn-60 · Arreglar el login')
    await fillPart(2, 'TOL App (tol)', 'Nueva tarea…', 'Reunión con el cliente')
    expect(within(card(2)).getByText('Tarea nueva: se crea al guardar')).toBeInTheDocument()
    const save = screen.getByRole('button', { name: 'Guardar reparto' })
    expect(save).toBeDisabled() // the new task has no title yet
    await userEvent.type(within(card(2)).getByLabelText('Tarea nueva'), 'Reunión semanal')
    await userEvent.click(save)

    expect(split).toHaveBeenCalledWith(40, [
      { from: 0, to: 9263, project_id: 9, task_id: 60, new_task_title: null, comment },
      { from: 9263, to: 33_083, project_id: 12, task_id: null, new_task_title: 'Reunión semanal', comment: 'Reunión con el cliente' },
    ])
    await vi.waitFor(() => expect(onDone).toHaveBeenCalled())
    expect(await screen.findByText('Día repartido en 2 partes.')).toBeInTheDocument()
    expect(screen.getByText(/Bahía Web \(−1h 34m\)/)).toBeInTheDocument()
  })

  it('warns when a part leaves the bag below zero, and saves anyway', async () => {
    renderDialog()
    await fillPart(1, 'Bahía Web (bhn) · Bahía', 'bhn-60 · Arreglar el login')

    expect(card(1)).toHaveTextContent(
      'A Bahía Web le quedan 1h 00m: con esta parte la bolsa queda en −8h 11m. Se guarda igual y se avisa al admin y al responsable.',
    )
    expect(screen.getByRole('button', { name: 'Guardar reparto' })).toBeEnabled()
  })

  it('shows the server field errors on the part they belong to and business refusals inline', async () => {
    const split = vi
      .spyOn(timerApi, 'splitDay')
      .mockRejectedValueOnce(new ApiError(422, 'Revisá las partes.', { 'parts.0.task_id': ['Esa tarea no está asignada a vos.'] }))
      .mockRejectedValueOnce(new ApiError(422, 'Dos partes se pisan.', {}, 'day_part_overlaps'))
    renderDialog()
    await fillPart(1, 'Bahía Web (bhn) · Bahía', 'bhn-60 · Arreglar el login')

    await userEvent.click(screen.getByRole('button', { name: 'Guardar reparto' }))
    expect(await within(card(1)).findByText('Esa tarea no está asignada a vos.')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Guardar reparto' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Dos partes se pisan.')
    expect(split).toHaveBeenCalledTimes(2)
  })

  it('"Repartir más tarde" just closes: the day stays pending', async () => {
    const split = vi.spyOn(timerApi, 'splitDay')
    const { onClose } = renderDialog()

    await userEvent.click(await screen.findByRole('button', { name: 'Repartir más tarde' }))

    expect(onClose).toHaveBeenCalled()
    expect(split).not.toHaveBeenCalled()
  })

  it('"No conté nada este día" asks first, then sends an empty split', async () => {
    const split = vi.spyOn(timerApi, 'splitDay').mockResolvedValue({ data: { entries: [], overdrawn: [] }, message: null, errors: null })
    const onClose = vi.fn()
    renderDialog({ onClose })

    await userEvent.click(await screen.findByRole('button', { name: 'No conté nada este día' }))
    const confirm = await screen.findByRole('dialog', { name: '¿No contaste nada este día?' })
    expect(split).not.toHaveBeenCalled()
    await userEvent.click(within(confirm).getByRole('button', { name: 'Sí, no conté nada' }))

    expect(split).toHaveBeenCalledWith(40, [])
    await vi.waitFor(() => expect(onClose).toHaveBeenCalled())
  })

  it('moves cuts with the arrow keys (±1 min, Shift ±15) and by dragging the handle', async () => {
    renderDialog()
    const end = await screen.findByRole('slider', { name: 'Fin de la parte 1' })
    expect(end).toHaveAttribute('aria-valuetext', '18:14')

    fireEvent.keyDown(end, { key: 'ArrowLeft' })
    expect(end).toHaveAttribute('aria-valuetext', '18:13')
    fireEvent.keyDown(end, { key: 'ArrowLeft', shiftKey: true })
    expect(end).toHaveAttribute('aria-valuetext', '17:58')

    const track = end.parentElement!
    vi.spyOn(track, 'getBoundingClientRect').mockReturnValue(DOMRect.fromRect({ x: 0, y: 0, width: 1000, height: 56 }))
    end.setPointerCapture = vi.fn()
    fireEvent.pointerDown(end, { button: 0, clientX: 978, pointerId: 1 })
    fireEvent.pointerMove(end, { clientX: 500, pointerId: 1 })
    fireEvent.pointerUp(end, { pointerId: 1 })

    expect(end).toHaveAttribute('aria-valuetext', '13:38')
    expect(screen.getByRole('button', { name: 'Sumar una parte de 13:38 a 18:14' })).toBeInTheDocument()
  })
})
