import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { OLIMPO_CHANNEL_ID } from '../test/fixtures/portal'
import { reply } from '../test/mockApi'
import { mockPortalAdmin } from '../test/portalApi'
import { renderWithProviders } from '../test/render'
import { ClientPage } from './ClientPage'
import { ProjectAdminPage } from './ProjectAdminPage'

vi.mock('uplot', () => ({ default: class {} }))

function renderClient() {
  return renderWithProviders(
    <Routes>
      <Route path="/monitoreo/clientes" element={<p>lista de clientes</p>} />
      <Route path="/monitoreo/clientes/:id" element={<ClientPage />} />
      <Route path="/monitoreo/clientes/:id/proyectos/:pid" element={<ProjectAdminPage />} />
    </Routes>,
    { route: '/monitoreo/clientes/1', admin: true },
  )
}

const formPanel = async () => (await screen.findByRole('heading', { name: 'Datos del proyecto' })).closest('section') as HTMLElement
const optionTexts = (select: HTMLElement) => within(select).getAllByRole('option').map((option) => option.textContent)

async function fillNewProject(user: ReturnType<typeof userEvent.setup>, code = 'tienda') {
  await user.type(await screen.findByLabelText('Nuevo proyecto'), 'Tienda de Playinn')
  await user.type(screen.getByLabelText('Código'), code)
}

afterEach(() => vi.restoreAllMocks())

describe('alta de proyecto con dev principal y canal de Discord', () => {
  it('pide al bot un canal nuevo y queda elegido en el proyecto', async () => {
    const user = userEvent.setup()
    const { state, api } = mockPortalAdmin()
    renderClient()
    await fillNewProject(user)
    const lead = screen.getByRole('combobox', { name: 'Dev principal' })
    await user.selectOptions(lead, await within(lead).findByRole('option', { name: 'teamviolet@nexosmart.com.ar' }))
    const discord = await screen.findByRole('combobox', { name: 'Discord' })
    // Solo los canales donde el bot puede escribir.
    expect(optionTexts(discord)).toEqual(['Sin canal', 'Crear canal nuevo', '# playinn', '# club-olimpo', '# general', '# Monitoring (hilo)', '# bienvenida'])
    await user.selectOptions(discord, 'Crear canal nuevo')
    await user.click(screen.getByRole('button', { name: 'Crear proyecto' }))

    expect(await screen.findByText('Proyecto creado.')).toBeInTheDocument()
    // El cuerpo de monitoreo más el código del proyecto de newDesk.
    expect(api.post.mock.calls.map((call) => call[1])).toEqual([{ name: 'Tienda de Playinn', code_prefix: 'tienda', lead_user_id: 1, create_discord_channel: true }])
    const created = state.projects.at(-1)
    expect(created).toMatchObject({ name: 'Tienda de Playinn', code_prefix: 'tienda', developers: [{ user_id: 1, role: 'lead' }] })
    expect(created?.discord_channel_id).toMatch(/^\d{17,20}$/)
    const select = within(await formPanel()).getByRole('combobox', { name: 'Canal de Discord' })
    expect(await within(select).findByRole('option', { name: '# tienda-de-playinn' })).toBeInTheDocument()
    expect(select).toHaveValue(created?.discord_channel_id)
    expect(within(await formPanel()).getByLabelText('Código')).toHaveValue('tienda')
  })

  it('elige un canal existente', async () => {
    const user = userEvent.setup()
    const { api } = mockPortalAdmin()
    renderClient()
    await fillNewProject(user)
    await user.selectOptions(await screen.findByRole('combobox', { name: 'Discord' }), '# club-olimpo')
    await user.click(screen.getByRole('button', { name: 'Crear proyecto' }))
    expect(await screen.findByText('Proyecto creado.')).toBeInTheDocument()
    expect(api.post.mock.calls.map((call) => call[1])).toEqual([{ name: 'Tienda de Playinn', code_prefix: 'tienda', discord_channel_id: OLIMPO_CHANNEL_ID }])
  })

  it('muestra en el proyecto el aviso de que el bot no pudo crear el canal', async () => {
    const user = userEvent.setup()
    const warning = 'Proyecto creado, pero no se pudo crear el canal de Discord: el bot no tiene permiso para crear canales.'
    const { state } = mockPortalAdmin({
      'POST portal-admin/clients/:id/projects': () => reply({ status: 201, data: { ...state.projects[0], discord_channel_id: null }, message: warning }),
    })
    renderClient()
    await fillNewProject(user)
    await user.selectOptions(await screen.findByRole('combobox', { name: 'Discord' }), 'Crear canal nuevo')
    await user.click(screen.getByRole('button', { name: 'Crear proyecto' }))
    expect((await screen.findByText(warning)).closest('[role="alert"]')).toBeInTheDocument()
  })

  it('sin bot no ofrece Discord y crea el proyecto solo con el nombre y el código', async () => {
    const user = userEvent.setup()
    const { state, api } = mockPortalAdmin({ 'GET discord/channels': reply({ status: 503, message: 'El bot de Discord no está configurado.' }) })
    renderClient()
    await fillNewProject(user)
    await within(screen.getByRole('combobox', { name: 'Dev principal' })).findByRole('option', { name: 'teamviolet@nexosmart.com.ar' })
    expect(screen.queryByRole('combobox', { name: 'Discord' })).not.toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Crear proyecto' }))
    expect(await screen.findByText('Proyecto creado.')).toBeInTheDocument()
    expect(api.post.mock.calls.map((call) => call[1])).toEqual([{ name: 'Tienda de Playinn', code_prefix: 'tienda' }])
    expect(state.projects.at(-1)?.discord_channel_id).toBeNull()
  })
})

describe('código del proyecto (newDesk, RF-03.01)', () => {
  it('no deja crear el proyecto sin un código válido y muestra el rechazo del back', async () => {
    const user = userEvent.setup()
    mockPortalAdmin({
      'POST portal-admin/clients/:id/projects': reply({ status: 422, message: 'Revisá los campos marcados.', errors: { code_prefix: ['Ese código ya está en uso.'] } }),
    })
    renderClient()
    const create = await screen.findByRole('button', { name: 'Crear proyecto' })
    await user.type(screen.getByLabelText('Nuevo proyecto'), 'Tienda de Playinn')
    expect(create).toBeDisabled()
    // Solo minúsculas y dígitos: lo demás no entra.
    await user.type(screen.getByLabelText('Código'), 'T')
    expect(screen.getByLabelText('Código')).toHaveValue('t')
    expect(create).toBeDisabled()
    await user.type(screen.getByLabelText('Código'), 'i-e')
    expect(screen.getByLabelText('Código')).toHaveValue('tie')
    expect(create).toBeEnabled()

    await user.click(create)
    expect(await screen.findByRole('alert')).toHaveTextContent('Ese código ya está en uso.')
  })

  it('se edita con el proyecto y muestra el error del back debajo del campo', async () => {
    const user = userEvent.setup()
    const { api } = mockPortalAdmin({
      'PUT portal-admin/projects/:id': reply({ status: 422, message: 'Revisá los campos marcados.', errors: { code_prefix: ['Ese código ya está en uso.'] } }),
    })
    renderWithProviders(
      <Routes>
        <Route path="/monitoreo/clientes/:id/proyectos/:pid" element={<ProjectAdminPage />} />
      </Routes>,
      { route: '/monitoreo/clientes/1/proyectos/1', admin: true },
    )
    const panel = await formPanel()
    const code = within(panel).getByLabelText('Código')
    expect(code).toHaveValue('playinn')
    await user.clear(code)
    await user.type(code, 'olimpo')
    await user.click(within(panel).getByRole('button', { name: 'Guardar' }))
    expect(await within(panel).findByText('Ese código ya está en uso.')).toBeInTheDocument()
    expect(code).toHaveAttribute('aria-invalid', 'true')
    expect(api.put.mock.calls[0]?.[1]).toMatchObject({ name: 'Playinn', code_prefix: 'olimpo' })
  })
})

describe('borrar un cliente', () => {
  it('con proyectos el back lo rechaza (422) y se muestra el motivo', async () => {
    const user = userEvent.setup()
    mockPortalAdmin({ 'DELETE portal-admin/clients/:id': reply({ status: 422, message: 'Un cliente con proyectos no se borra: inactivalo.' }) })
    renderClient()
    await user.click(await screen.findByRole('button', { name: 'Eliminar cliente' }))
    await user.click(screen.getByRole('button', { name: '¿Borrar el cliente y sus usuarios?' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('Un cliente con proyectos no se borra: inactivalo.')
    expect(screen.queryByText('lista de clientes')).not.toBeInTheDocument()
  })
})
