import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { buildTask } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import { CreateTaskDialog } from './CreateTaskDialog'
import { TaskDetailsForm } from './TaskDetailsForm'
import * as tasksApi from './tasksApi'

describe('task form: optional dates and estimate', () => {
  it('"Nueva tarea" keeps them under a closed "Más opciones"; a cleared date is sent as null', async () => {
    const create = vi.spyOn(tasksApi, 'createTask').mockResolvedValue(buildTask({ code: 'bhn-3' }))
    renderAt(<CreateTaskDialog projectId={9} members={[]} onClose={vi.fn()} />)

    const more = screen.getByRole('button', { name: /Más opciones/ })
    expect(more).toHaveAttribute('aria-expanded', 'false')
    expect(screen.queryByLabelText('Vencimiento (opcional)')).not.toBeVisible()

    await userEvent.type(screen.getByLabelText('Título'), 'Nueva home')
    await userEvent.click(more)
    const due = screen.getByLabelText('Vencimiento (opcional)')
    await userEvent.type(due, '2026-12-01')
    expect(due).toHaveValue('2026-12-01')
    await userEvent.click(screen.getByRole('button', { name: 'Quitar vencimiento' }))
    expect(due).toHaveValue('')
    await userEvent.click(screen.getByRole('button', { name: 'Crear' }))

    expect(create).toHaveBeenCalledWith(9, expect.objectContaining({ starts_on: null, due_on: null, estimate_seconds: null }))
    expect(await screen.findByText('Tarea bhn-3 creada.')).toBeInTheDocument()
  })

  it('editing opens "Más opciones" when the task has dates, and clearing one sends null', async () => {
    const task = buildTask({ due_on: '2026-11-20', estimate_seconds: 5400 })
    const update = vi.spyOn(tasksApi, 'updateTask').mockResolvedValue({ ...task, due_on: null })
    renderAt(<TaskDetailsForm task={task} />)

    expect(screen.getByRole('button', { name: /Más opciones/ })).toHaveAttribute('aria-expanded', 'true')
    expect(screen.getByLabelText('Estimación en horas (opcional)')).toHaveValue('1,5')
    await userEvent.click(screen.getByRole('button', { name: 'Quitar vencimiento' }))
    await userEvent.clear(screen.getByLabelText('Estimación en horas (opcional)'))
    await userEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(update).toHaveBeenCalledWith(task.code, expect.objectContaining({ due_on: null, estimate_seconds: null }))
    expect(await screen.findByText('Tarea actualizada.')).toBeInTheDocument()
  })
})
