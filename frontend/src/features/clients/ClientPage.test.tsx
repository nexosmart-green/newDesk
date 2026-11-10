import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import { ApiError } from '@/lib/api'
import { buildClient } from '@/test/fixtures'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import { ClientPage } from './ClientPage'
import * as clientsApi from './clientsApi'

describe('ClientPage', () => {
  it('explains that a client with projects is inactivated, not deleted (CA-02.01)', async () => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ permissions: ['clients.manage'] }))
    vi.spyOn(clientsApi, 'fetchClient').mockResolvedValue(buildClient({ projects: [] }))
    const message = 'Bahía tiene proyectos: no se borra, se inactiva.'
    vi.spyOn(clientsApi, 'deleteClient').mockRejectedValue(new ApiError(422, message, {}, 'client_has_projects'))
    const changeStatus = vi.spyOn(clientsApi, 'changeClientStatus').mockResolvedValue(buildClient({ status: 'inactive' }))
    renderRoutes([{ path: '/clientes/:clientId', element: <ClientPage /> }], '/clientes/5')

    await userEvent.click(await screen.findByRole('button', { name: 'Borrar' }))
    const dialog = screen.getByRole('dialog')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Borrar' }))

    expect(await within(dialog).findByRole('alert')).toHaveTextContent(message)
    await userEvent.click(within(dialog).getByRole('button', { name: 'Inactivar en su lugar' }))
    expect(changeStatus).toHaveBeenCalledWith(5, 'inactive')
  })
})
