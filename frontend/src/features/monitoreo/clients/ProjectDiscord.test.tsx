import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { OLIMPO_CHANNEL_ID, PLAYINN_CHANNEL_ID } from '../test/fixtures/portal'
import { reply } from '../test/mockApi'
import { mockPortalAdmin } from '../test/portalApi'
import { renderWithProviders } from '../test/render'
import { ProjectAdminPage } from './ProjectAdminPage'

vi.mock('uplot', () => ({ default: class {} }))

const NO_BOT = 'El bot de Discord no está configurado.'
const withoutBot = { 'GET discord/channels': reply({ status: 503, message: NO_BOT }) }

function renderProject(clientId: number, projectId: number) {
  return renderWithProviders(
    <Routes>
      <Route path="/monitoreo/clientes/:id/proyectos/:pid" element={<ProjectAdminPage />} />
    </Routes>,
    { route: `/monitoreo/clientes/${clientId}/proyectos/${projectId}`, admin: true },
  )
}

const formPanel = async () => (await screen.findByRole('heading', { name: 'Datos del proyecto' })).closest('section') as HTMLElement
const optionTexts = (select: HTMLElement) => within(select).getAllByRole('option').map((option) => option.textContent)

afterEach(() => vi.restoreAllMocks())

describe('canal de Discord del proyecto', () => {
  it('lista los canales del bot por categoría y deshabilita los que no puede usar', async () => {
    mockPortalAdmin()
    renderProject(1, 1)
    const select = within(await formPanel()).getByRole('combobox', { name: 'Canal de Discord' })
    expect(await within(select).findByRole('option', { name: '# playinn' })).toBeEnabled()
    expect(select).toHaveValue(PLAYINN_CHANNEL_ID)
    expect(optionTexts(select)).toEqual([
      'Sin canal', 'Crear canal nuevo', '# playinn', '# reaquila — el bot no tiene acceso', '# club-olimpo', '# general', '# Monitoring (hilo)', '# bienvenida',
    ])
    expect([...select.querySelectorAll('optgroup')].map((group) => group.label)).toEqual(['Equipo BBG1', 'General', 'Sin categoría'])
    expect(within(select).getByRole('option', { name: '# reaquila — el bot no tiene acceso' })).toBeDisabled()
    expect(within(select).getByRole('option', { name: '# bienvenida' }).closest('optgroup')).toHaveAttribute('label', 'Sin categoría')
  })

  it('manda el canal elegido con el resto del formulario y "Sin canal" lo quita', async () => {
    const user = userEvent.setup()
    const { state, api } = mockPortalAdmin()
    renderProject(1, 1)
    const panel = await formPanel()
    const select = within(panel).getByRole('combobox', { name: 'Canal de Discord' })
    await within(select).findByRole('option', { name: '# club-olimpo' })
    expect(within(panel).queryByRole('button', { name: 'Quitar' })).not.toBeInTheDocument()

    await user.selectOptions(select, '# club-olimpo')
    await user.click(within(panel).getByRole('button', { name: 'Guardar' }))
    expect(await within(panel).findByText('Guardado.')).toBeInTheDocument()
    // Same body as monitoreo minus name and code, which are edited in Proyectos.
    expect(api.put.mock.calls[0]?.[1]).toMatchObject({
      slug: 'playinn', primary_color: '#e11d48', domain: 'playinn.com', discord_channel_id: OLIMPO_CHANNEL_ID,
    })
    expect(state.projects[0]?.discord_channel_id).toBe(OLIMPO_CHANNEL_ID)

    await user.selectOptions(select, 'Sin canal')
    await user.click(within(panel).getByRole('button', { name: 'Guardar' }))
    await waitFor(() => expect(within(panel).queryByRole('button', { name: 'Probar' })).not.toBeInTheDocument())
    expect(api.put.mock.calls.at(-1)?.[1]).toMatchObject({ slug: 'playinn', discord_channel_id: null })
    expect(state.projects[0]?.discord_channel_id).toBeNull()
  })

  it('pide al bot un canal nuevo al guardar y queda elegido', async () => {
    const user = userEvent.setup()
    const { state, api } = mockPortalAdmin()
    renderProject(2, 2)
    const panel = await formPanel()
    const select = within(panel).getByRole('combobox', { name: 'Canal de Discord' })
    await user.selectOptions(select, await within(select).findByRole('option', { name: 'Crear canal nuevo' }))
    await user.click(within(panel).getByRole('button', { name: 'Guardar' }))

    expect(await within(panel).findByText('Guardado.')).toBeInTheDocument()
    const body = api.put.mock.calls[0]?.[1]
    expect(body).toMatchObject({ create_discord_channel: true })
    expect(body).not.toHaveProperty('discord_channel_id')
    const created = state.projects[1]?.discord_channel_id
    expect(created).toMatch(/^\d{17,20}$/)
    expect(await within(select).findByRole('option', { name: '# sitio-del-club' })).toBeInTheDocument()
    expect(select).toHaveValue(created)
  })

  it('avisa si el bot no pudo crear el canal, con el proyecto guardado igual', async () => {
    const user = userEvent.setup()
    const warning = 'Proyecto guardado, pero no se pudo crear el canal de Discord: el bot no tiene permiso para crear canales.'
    const { state } = mockPortalAdmin({ 'PUT portal-admin/projects/:id': () => reply({ data: state.projects[1], message: warning }) })
    renderProject(2, 2)
    const panel = await formPanel()
    const select = within(panel).getByRole('combobox', { name: 'Canal de Discord' })
    await user.selectOptions(select, await within(select).findByRole('option', { name: 'Crear canal nuevo' }))
    await user.click(within(panel).getByRole('button', { name: 'Guardar' }))

    expect(await within(panel).findByRole('alert')).toHaveTextContent(warning)
    expect(select).toHaveValue('')
  })

  it('prueba el canal guardado y muestra lo que respondió', async () => {
    const user = userEvent.setup()
    const { routes } = mockPortalAdmin()
    renderProject(1, 1)
    const panel = await formPanel()
    await user.click(within(panel).getByRole('button', { name: 'Probar' }))
    expect(await within(panel).findByText('Mensaje de prueba enviado.')).toBeInTheDocument()

    routes['POST portal-admin/projects/:id/discord-test'] = reply({ status: 502, message: 'Discord respondió 403: Missing Access.' })
    await user.click(within(panel).getByRole('button', { name: 'Probar' }))
    expect(await within(panel).findByRole('alert')).toHaveTextContent('Discord respondió 403: Missing Access.')
    expect(within(panel).queryByText('Mensaje de prueba enviado.')).not.toBeInTheDocument()
  })

  it('sin un canal guardado no ofrece probar', async () => {
    mockPortalAdmin()
    renderProject(2, 2)
    const panel = await formPanel()
    expect(within(panel).getByRole('combobox', { name: 'Canal de Discord' })).toHaveValue('')
    expect(within(panel).queryByRole('button', { name: 'Probar' })).not.toBeInTheDocument()
  })

  it('sin bot avisa y conserva el canal guardado al guardar', async () => {
    const user = userEvent.setup()
    const { state, api } = mockPortalAdmin(withoutBot)
    renderProject(1, 1)
    const panel = await formPanel()
    expect(await within(panel).findByText(NO_BOT)).toBeInTheDocument()
    const select = within(panel).getByRole('combobox', { name: 'Canal de Discord' })
    expect(select).toHaveValue(PLAYINN_CHANNEL_ID)
    expect(optionTexts(select)).toEqual(['Sin canal', `Canal ${PLAYINN_CHANNEL_ID}`])

    await user.click(within(panel).getByRole('button', { name: 'Guardar' }))
    expect(await within(panel).findByText('Guardado.')).toBeInTheDocument()
    expect(api.put.mock.calls[0]?.[1]).toMatchObject({ discord_channel_id: PLAYINN_CHANNEL_ID })
    expect(state.projects[0]?.discord_channel_id).toBe(PLAYINN_CHANNEL_ID)
  })

  it('avisa cuando el desarrollador principal no tiene Discord cargado', async () => {
    mockPortalAdmin()
    const user = userEvent.setup()
    renderProject(1, 1)
    const lead = await screen.findByRole('combobox', { name: 'Desarrollador principal' })
    await waitFor(() => expect(lead).toHaveValue('1'))
    expect(screen.queryByText('(sin Discord cargado: no se lo etiqueta)')).not.toBeInTheDocument()

    await user.selectOptions(lead, 'dev@nexosmart.com.ar')
    expect(screen.getByText('(sin Discord cargado: no se lo etiqueta)')).toBeInTheDocument()
  })
})
