import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as rolesApi from '@/features/roles/rolesApi'
import { page } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import type { Role } from '@/features/roles/types'
import type { UserSummary } from './types'
import { UsersTab } from './UsersTab'
import * as usersApi from './usersApi'

const roles: Role[] = [
  { id: 1, name: 'Admin', is_system: true, permissions: [], users_count: 1 },
  { id: 2, name: 'Colaborador', is_system: false, permissions: [], users_count: 2 },
]

function buildUserRow(id: number, first_name: string, last_name: string): UserSummary {
  return {
    id,
    first_name,
    last_name,
    full_name: `${first_name} ${last_name}`,
    email: `${first_name.toLowerCase()}@nexosmart.com`,
    status: 'active',
    role: { id: 2, name: 'Colaborador' },
    last_login_at: null,
  }
}

describe('UsersTab', () => {
  it('changing one role locks only that row and patches it with the answer', async () => {
    const ana = buildUserRow(1, 'Ana', 'García')
    const juan = buildUserRow(2, 'Juan', 'Pérez')
    vi.spyOn(usersApi, 'fetchUsers').mockResolvedValue(page([ana, juan]))
    vi.spyOn(rolesApi, 'fetchRoles').mockResolvedValue(roles)
    let answer!: (user: UserSummary) => void
    vi.spyOn(usersApi, 'changeUserRole').mockReturnValue(new Promise((resolve) => (answer = resolve)))
    renderAt(<UsersTab />)

    // The role options arrive with the roles catalog.
    expect(await screen.findAllByRole('option', { name: 'Admin' })).toHaveLength(2)
    await userEvent.selectOptions(await screen.findByLabelText('Rol de Ana García'), 'Admin')

    expect(screen.getByLabelText('Rol de Ana García')).toBeDisabled()
    expect(screen.getByLabelText('Rol de Juan Pérez')).toBeEnabled()

    answer({ ...ana, role: { id: 1, name: 'Admin' } })
    expect(await screen.findByText('Rol de Ana García actualizado.')).toBeInTheDocument()
    expect(screen.getByLabelText('Rol de Ana García')).toHaveValue('1')
    expect(screen.getByLabelText('Rol de Ana García')).toBeEnabled()
  })
})
