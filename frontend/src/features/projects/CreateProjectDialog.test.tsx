import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as clientsApi from '@/features/clients/clientsApi'
import * as peopleApi from '@/features/people/peopleApi'
import { buildClient, buildPerson, buildProject, page } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import { CreateProjectDialog } from './CreateProjectDialog'
import * as projectsApi from './projectsApi'

describe('CreateProjectDialog', () => {
  it('offers only active clients and sends the project with its members (CA-02.01)', async () => {
    const fetchClients = vi.spyOn(clientsApi, 'fetchClients').mockResolvedValue(page([buildClient()]))
    vi.spyOn(peopleApi, 'fetchPeople').mockResolvedValue(
      page([buildPerson({ id: 1 }), buildPerson({ id: 2, first_name: 'Juan', last_name: 'Pérez' })]),
    )
    const create = vi.spyOn(projectsApi, 'createProject').mockResolvedValue(buildProject())
    const onCreated = vi.fn()
    renderAt(<CreateProjectDialog onClose={vi.fn()} onCreated={onCreated} />)

    const clientSelect = await screen.findByLabelText('Cliente')
    await within(clientSelect).findByRole('option', { name: 'Bahía' })
    expect(fetchClients).toHaveBeenCalledWith({ status: 'active', search: '', page: 1 })
    expect(within(clientSelect).getAllByRole('option').map((option) => option.textContent)).toEqual(['Sin cliente', 'Bahía'])

    await userEvent.type(screen.getByLabelText('Nombre'), 'Bahía Web')
    await userEvent.type(screen.getByLabelText('Prefijo'), 'BHN-')
    await userEvent.selectOptions(clientSelect, 'Bahía')
    await userEvent.selectOptions(screen.getByLabelText('Responsable'), 'Ana García')
    await userEvent.click(await screen.findByRole('checkbox', { name: 'Juan Pérez' }))
    await userEvent.click(screen.getByRole('button', { name: 'Crear proyecto' }))

    expect(create).toHaveBeenCalledWith({
      name: 'Bahía Web',
      code_prefix: 'bhn',
      type: 'bag',
      client_id: 5,
      description: null,
      leader_user_id: 1,
      starts_on: null,
      ends_on: null,
      member_ids: [2],
    })
    await vi.waitFor(() => expect(onCreated).toHaveBeenCalled())
  }, 30_000)
})
