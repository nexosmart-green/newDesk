import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { CatalogsTab } from '@/features/catalogs/CatalogsTab'
import * as catalogsApi from '@/features/catalogs/catalogsApi'
import { currentUserKey } from '@/lib/queryKeys'
import { ApiError } from '@/lib/api'
import { mockTaskCatalogs, taskCategories } from '@/test/mockTaskCatalogs'
import { buildUser, createTestQueryClient, renderAt } from '@/test/renderWithProviders'
import { CategoryPicker } from './CategoryPicker'
import { LabelPicker } from './LabelPicker'
import * as taskCatalogsApi from './taskCatalogsApi'

function renderSettings(permissions: string[]) {
  const client = createTestQueryClient()
  client.setQueryData(currentUserKey, buildUser({ permissions }))
  vi.spyOn(catalogsApi, 'fetchCatalog').mockResolvedValue([])
  mockTaskCatalogs()
  renderAt(<CatalogsTab />, '/configuracion/catalogos', client)
}

describe('Configuración → Catálogos (tareas)', () => {
  it('shows the task catalogs only to projects.manage', async () => {
    renderSettings(['users.manage'])
    expect(await screen.findByRole('heading', { name: 'Puestos' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Categorías de tarea' })).not.toBeInTheDocument()
  })

  it('creates, renames and deletes a category, saying how many tasks it leaves without one', async () => {
    const create = vi.spyOn(taskCatalogsApi, 'createTaskCategory').mockResolvedValue({ id: 3, name: 'Ticket' })
    const rename = vi.spyOn(taskCatalogsApi, 'renameTaskCategory').mockResolvedValue({ id: 1, name: 'Backend' })
    const remove = vi.spyOn(taskCatalogsApi, 'deleteTaskCategory').mockResolvedValue('Categoría borrada.')
    renderSettings(['projects.manage'])

    const list = await screen.findByRole('list', { name: 'Categorías de tarea' })
    expect(within(list).getByText('3 tareas')).toBeInTheDocument()

    await userEvent.type(screen.getByLabelText('Nueva categoría'), 'Ticket')
    await userEvent.click(screen.getByRole('button', { name: 'Crear categoría' }))
    expect(create).toHaveBeenCalledWith('Ticket')
    expect(await screen.findByText('Categoría «Ticket» creada.')).toBeInTheDocument()

    await userEvent.click(within(list).getByRole('button', { name: 'Renombrar Backend-API' }))
    const input = within(list).getByLabelText('Nuevo nombre para Backend-API')
    await userEvent.clear(input)
    await userEvent.type(input, 'Backend{Enter}')
    expect(rename).toHaveBeenCalledWith(1, 'Backend')

    await userEvent.click(within(list).getByRole('button', { name: 'Borrar Diseño' }))
    const dialog = await screen.findByRole('dialog', { name: '¿Borrar «Diseño»?' })
    expect(within(dialog).getByText(/Ninguna tarea la usa/)).toBeInTheDocument()
    await userEvent.click(within(dialog).getByRole('button', { name: 'Borrar' }))
    expect(remove).toHaveBeenCalledWith(2)
  })

  it('shows a duplicate name under the field', async () => {
    vi.spyOn(taskCatalogsApi, 'createTaskCategory').mockRejectedValue(
      new ApiError(422, 'Datos inválidos', { name: ['Ya existe la categoría Diseño.'] }),
    )
    renderSettings(['projects.manage'])

    await userEvent.type(await screen.findByLabelText('Nueva categoría'), 'Diseño')
    await userEvent.click(screen.getByRole('button', { name: 'Crear categoría' }))

    expect(await screen.findByText('Ya existe la categoría Diseño.')).toBeInTheDocument()
  })

  it('creates a label with a chosen color', async () => {
    const create = vi.spyOn(taskCatalogsApi, 'createTaskLabel').mockResolvedValue({ id: 9, name: 'QA', color: '#8b5cf6' })
    renderSettings(['projects.manage'])

    await userEvent.type(await screen.findByLabelText('Nueva etiqueta'), 'QA')
    await userEvent.click(screen.getByLabelText('Color #8b5cf6'))
    await userEvent.click(screen.getByRole('button', { name: 'Crear etiqueta' }))

    expect(create).toHaveBeenCalledWith({ name: 'QA', color: '#8b5cf6' })
  })
})

describe('pickers in the task form', () => {
  it('"Crear categoría…" creates one inline and selects it', async () => {
    mockTaskCatalogs()
    vi.spyOn(taskCatalogsApi, 'createTaskCategory').mockResolvedValue({ id: 3, name: 'Ticket' })
    const onChange = vi.fn()
    renderAt(<CategoryPicker value={null} onChange={onChange} />)

    await screen.findByRole('option', { name: taskCategories[0]?.name })
    await userEvent.selectOptions(screen.getByLabelText('Categoría'), '＋ Crear categoría…')
    await userEvent.type(screen.getByLabelText('Nueva categoría'), 'Ticket{Enter}')

    await vi.waitFor(() => expect(onChange).toHaveBeenCalledWith(3))
  })

  it('toggles labels and creates a new one already selected', async () => {
    mockTaskCatalogs()
    vi.spyOn(taskCatalogsApi, 'createTaskLabel').mockResolvedValue({ id: 9, name: 'QA', color: '#309798' })
    const onChange = vi.fn()
    renderAt(<LabelPicker selected={[7]} onChange={onChange} />)

    expect(await screen.findByRole('button', { name: 'Cliente urgente' })).toHaveAttribute('aria-pressed', 'true')
    await userEvent.click(screen.getByRole('button', { name: 'Deuda técnica' }))
    expect(onChange).toHaveBeenLastCalledWith([7, 8])

    await userEvent.click(screen.getByRole('button', { name: 'Nueva etiqueta' }))
    await userEvent.type(screen.getByLabelText('Nombre de la etiqueta'), 'QA')
    await userEvent.click(screen.getByRole('button', { name: 'Crear etiqueta' }))
    await vi.waitFor(() => expect(onChange).toHaveBeenLastCalledWith([7, 9]))
  })
})
