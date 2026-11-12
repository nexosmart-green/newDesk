import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ApiError } from '@/lib/api'
import { buildTask } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import { buildChecklistItem } from '@/test/taskExtrasFixtures'
import * as tasksApi from '../tasksApi'
import type { ChecklistItem, Task } from '../types'
import { useTask } from '../useTasks'
import * as checklistApi from './checklistApi'
import { dropIndex, moveItem } from './moveItem'
import { TaskChecklist } from './TaskChecklist'

const first = buildChecklistItem({ id: 1, text: 'Revisar el login', position: 1, done: true, done_by: { id: 2, full_name: 'Juan Pérez' } })
const second = buildChecklistItem({ id: 2, text: 'Probar en Safari', position: 2 })
const third = buildChecklistItem({ id: 3, text: 'Avisar al cliente', position: 3 })

function Harness() {
  const task = useTask('bhn-60')
  return task.data ? <TaskChecklist task={task.data} /> : null
}

/** What the backend holds: mutations' mocks update it, so the refetch after each change agrees. */
let server: ChecklistItem[] = []

function taskWith(items: ChecklistItem[]): Task {
  return buildTask({ checklist: items, checklist_progress: { done: items.filter((item) => item.done).length, total: items.length } })
}

function renderChecklist(items = [first, second, third]) {
  server = items
  vi.spyOn(tasksApi, 'fetchTask').mockImplementation(async () => taskWith(server))
  return renderAt(<Harness />)
}

function deferred<T>() {
  let resolve: (value: T) => void = () => undefined
  let reject: (error: unknown) => void = () => undefined
  const promise = new Promise<T>((onResolve, onReject) => {
    resolve = onResolve
    reject = onReject
  })
  return { promise, resolve, reject }
}

describe('moveItem / dropIndex', () => {
  it('moves an id to a clamped index and keeps the same array when nothing moves', () => {
    const ids = [1, 2, 3]
    expect(moveItem(ids, 1, 2)).toEqual([2, 3, 1])
    expect(moveItem(ids, 3, -5)).toEqual([3, 1, 2])
    expect(moveItem(ids, 2, 1)).toBe(ids)
  })

  it('turns a drop before/after a row into the final index', () => {
    expect(dropIndex(0, 2, true)).toBe(2)
    expect(dropIndex(0, 2, false)).toBe(1)
    expect(dropIndex(2, 0, false)).toBe(0)
    expect(dropIndex(2, 0, true)).toBe(1)
  })
})

describe('TaskChecklist', () => {
  it('shows the progress and who ticked each step', async () => {
    renderChecklist()

    expect(await screen.findByRole('progressbar', { name: 'Subtareas hechas' })).toHaveAttribute('aria-valuetext', '1 de 3')
    expect(screen.getByText('1 de 3')).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: 'Revisar el login' })).toBeChecked()
    expect(screen.getByRole('button', { name: 'Editar «Revisar el login»' })).toHaveTextContent('(Hecha por Juan Pérez)')
  })

  it('invites to write the first step when there are none', async () => {
    renderChecklist([])

    expect(await screen.findByText(/Dividí la tarea en pasos/)).toBeInTheDocument()
    expect(screen.queryByRole('progressbar')).not.toBeInTheDocument()
  })

  it('ticks at once and keeps it when the server agrees', async () => {
    const request = deferred<ReturnType<typeof buildChecklistItem>>()
    const update = vi.spyOn(checklistApi, 'updateChecklistItem').mockReturnValue(request.promise)
    renderChecklist()

    await userEvent.click(await screen.findByRole('checkbox', { name: 'Probar en Safari' }))

    expect(update).toHaveBeenCalledWith(2, { done: true })
    expect(screen.getByRole('checkbox', { name: 'Probar en Safari' })).toBeChecked()
    expect(screen.getByText('2 de 3')).toBeInTheDocument()
    const saved = { ...second, done: true, done_by: { id: 1, full_name: 'Ana García' } }
    server = [first, saved, third]
    request.resolve(saved)
    await vi.waitFor(() => expect(screen.getByRole('button', { name: 'Editar «Probar en Safari»' })).toHaveTextContent('Hecha por Ana García'))
  })

  it('rolls the tick back and says why when the server refuses', async () => {
    vi.spyOn(checklistApi, 'updateChecklistItem').mockRejectedValue(new ApiError(403, 'No podés editar esta tarea.'))
    renderChecklist()

    await userEvent.click(await screen.findByRole('checkbox', { name: 'Probar en Safari' }))

    expect(await screen.findByText('No podés editar esta tarea.')).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: 'Probar en Safari' })).not.toBeChecked()
    expect(screen.getByText('1 de 3')).toBeInTheDocument()
  })

  it('adds with Enter, clears the field and keeps typing there', async () => {
    const add = vi.spyOn(checklistApi, 'addChecklistItem').mockImplementation(async () => {
      const item = buildChecklistItem({ id: 9, text: 'Subir a producción', position: 4 })
      server = [...server, item]
      return item
    })
    renderChecklist()

    const field = await screen.findByLabelText('Nueva subtarea')
    await userEvent.type(field, '  Subir a producción {Enter}')

    expect(add).toHaveBeenCalledWith('bhn-60', 'Subir a producción')
    expect(await screen.findByRole('checkbox', { name: 'Subir a producción' })).toBeInTheDocument()
    expect(field).toHaveValue('')
    expect(field).toHaveFocus()
    expect(screen.getByText('1 de 4')).toBeInTheDocument()
  })

  it('shows the validation message under the field', async () => {
    vi.spyOn(checklistApi, 'addChecklistItem').mockRejectedValue(
      new ApiError(422, 'Datos inválidos', { text: ['La subtarea puede tener hasta 500 caracteres.'] }),
    )
    renderChecklist()

    await userEvent.type(await screen.findByLabelText('Nueva subtarea'), 'Algo{Enter}')

    expect(await screen.findByText('La subtarea puede tener hasta 500 caracteres.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nueva subtarea')).toHaveAttribute('aria-invalid', 'true')
  })

  it('renames inline with Enter and cancels with Escape', async () => {
    const update = vi.spyOn(checklistApi, 'updateChecklistItem').mockImplementation(async () => {
      const renamed = { ...third, text: 'Avisar a Bahía' }
      server = [first, second, renamed]
      return renamed
    })
    renderChecklist()

    await userEvent.click(await screen.findByRole('button', { name: 'Editar «Avisar al cliente»' }))
    await userEvent.keyboard('{Escape}')
    expect(update).not.toHaveBeenCalled()

    await userEvent.click(screen.getByRole('button', { name: 'Editar «Avisar al cliente»' }))
    const field = screen.getByLabelText('Texto de la subtarea')
    await userEvent.clear(field)
    await userEvent.type(field, 'Avisar a Bahía{Enter}')

    expect(update).toHaveBeenCalledWith(3, { text: 'Avisar a Bahía' })
    expect(await screen.findByRole('checkbox', { name: 'Avisar a Bahía' })).toBeInTheDocument()
  })

  it('reorders with the arrows (keyboard), optimistically, and announces the new place', async () => {
    const reorder = vi.spyOn(checklistApi, 'reorderChecklist').mockImplementation(async () => {
      server = [second, first, third]
      return server
    })
    renderChecklist()

    await userEvent.click(await screen.findByRole('button', { name: 'Bajar «Revisar el login»' }))

    expect(reorder).toHaveBeenCalledWith('bhn-60', [2, 1, 3])
    const rows = within(screen.getByRole('list', { name: 'Subtareas' })).getAllByRole('checkbox')
    expect(rows.map((row) => row.getAttribute('aria-label'))).toEqual(['Probar en Safari', 'Revisar el login', 'Avisar al cliente'])
    expect(screen.getByText('«Revisar el login» quedó en el lugar 2 de 3.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Subir «Probar en Safari»' })).toBeDisabled()
  })

  it('puts the order back when reordering fails', async () => {
    vi.spyOn(checklistApi, 'reorderChecklist').mockRejectedValue(new ApiError(422, 'Las subtareas cambiaron, recargá la página.'))
    renderChecklist()

    await userEvent.click(await screen.findByRole('button', { name: 'Subir «Avisar al cliente»' }))

    expect(await screen.findByText('Las subtareas cambiaron, recargá la página.')).toBeInTheDocument()
    const rows = within(screen.getByRole('list', { name: 'Subtareas' })).getAllByRole('checkbox')
    expect(rows.map((row) => row.getAttribute('aria-label'))).toEqual(['Revisar el login', 'Probar en Safari', 'Avisar al cliente'])
  })

  it('deletes after confirming', async () => {
    const remove = vi.spyOn(checklistApi, 'deleteChecklistItem').mockImplementation(async () => {
      server = [first, third]
    })
    renderChecklist()

    await userEvent.click(await screen.findByRole('button', { name: 'Borrar «Probar en Safari»' }))
    const dialog = screen.getByRole('dialog', { name: 'Borrar subtarea' })
    expect(within(dialog).getByText(/«Probar en Safari»/)).toBeInTheDocument()
    await userEvent.click(within(dialog).getByRole('button', { name: 'Borrar' }))

    expect(remove).toHaveBeenCalledWith(2)
    expect(await screen.findByText('Subtarea borrada.')).toBeInTheDocument()
    expect(screen.queryByRole('checkbox', { name: 'Probar en Safari' })).not.toBeInTheDocument()
    expect(screen.getByText('1 de 2')).toBeInTheDocument()
  })
})
