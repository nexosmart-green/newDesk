import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import type { User } from '../api/types'
import { discordMembers, team } from '../test/fixtures/portal'
import { mockMonitoringApi, reply } from '../test/mockApi'
import { renderWithProviders } from '../test/render'
import { SettingsPage } from './SettingsPage'

const ORLANDO = '1077787097219473408'

// The team in memory: what a PUT saves comes back on the refetch, as with the back (which clears "verified" on change).
function mockTeam(overrides: Parameters<typeof mockMonitoringApi>[0] = {}) {
  const people: User[] = team.map((user) => ({ ...user }))
  const saved: Array<string | null> = []
  mockMonitoringApi({
    'GET users': () => people.map((user) => ({ ...user })),
    'GET discord/members': discordMembers(),
    'PUT users/:id': ({ params, body }) => {
      const person = people.find((user) => user.id === Number(params.id))!
      const id = (body as { discord_user_id: string | null }).discord_user_id
      saved.push(id)
      if (person.discord_user_id !== id) Object.assign(person, { discord_user_id: id, discord_verified: false })
      return { ...person }
    },
    ...overrides,
  })
  return saved
}

function renderTeam() {
  return renderWithProviders(
    <Routes>
      <Route path="/monitoreo/settings/:tab?" element={<SettingsPage />} />
    </Routes>,
    { route: '/monitoreo/settings/equipo', admin: true },
  )
}

const row = async (name: string) => (await screen.findByText(name)).closest('tr') as HTMLElement

afterEach(() => vi.restoreAllMocks())

describe('Configuración → Equipo: cuenta de Discord de cada persona', () => {
  it('elige la cuenta entre la gente del servidor y dice quién la conectó', async () => {
    const saved = mockTeam()
    renderTeam()

    const violet = await row('Team Violet')
    expect(within(violet).getByText('La conectó la persona')).toBeInTheDocument()
    const dev = await row('Dev Uno')
    expect(within(dev).getByText('Sin cuenta', { selector: 'span' })).toBeInTheDocument()

    const select = within(dev).getByRole('combobox', { name: 'Cuenta de Discord de Dev Uno' })
    await within(select).findByRole('option', { name: 'Orlando (@orlando85.)' })
    await userEvent.selectOptions(select, ORLANDO)

    await waitFor(() => expect(within(dev).getByText('Cargada a mano')).toBeInTheDocument())
    expect(saved).toEqual([ORLANDO])
    expect(select).toHaveValue(ORLANDO)
  })

  it('muestra el error al lado de la persona y deja la cuenta como estaba', async () => {
    mockTeam({ 'PUT users/:id': reply({ status: 422, message: 'Datos inválidos.', errors: { discord_user_id: ['Esa cuenta de Discord ya es de otra persona.'] } }) })
    renderTeam()

    const dev = await row('Dev Uno')
    const select = within(dev).getByRole('combobox', { name: 'Cuenta de Discord de Dev Uno' })
    await within(select).findByRole('option', { name: 'Team Violet (@teamviolet)' })
    await userEvent.selectOptions(select, '1403351571303104612')

    expect(await within(dev).findByRole('alert')).toHaveTextContent('Esa cuenta de Discord ya es de otra persona.')
    expect(select).toHaveValue('')
  })

  it('sin bot, se pega el ID; vacío la quita', async () => {
    const saved = mockTeam({ 'GET discord/members': reply({ status: 503, message: 'El bot de Discord no está configurado.' }) })
    renderTeam()

    expect(await screen.findByText(/El bot de Discord no está configurado\. Pegá el ID numérico de cada uno\./)).toBeInTheDocument()
    const dev = await row('Dev Uno')
    const input = within(dev).getByRole('textbox', { name: 'Cuenta de Discord de Dev Uno' })
    const save = within(dev).getByRole('button', { name: 'Guardar' })
    expect(save).toBeDisabled()
    await userEvent.type(input, ORLANDO)
    await userEvent.click(save)
    await waitFor(() => expect(within(dev).getByText('Cargada a mano')).toBeInTheDocument())

    const violet = await row('Team Violet')
    await userEvent.clear(within(violet).getByRole('textbox', { name: 'Cuenta de Discord de Team Violet' }))
    await userEvent.click(within(violet).getByRole('button', { name: 'Guardar' }))
    await waitFor(() => expect(saved).toEqual([ORLANDO, null]))
  })
})
