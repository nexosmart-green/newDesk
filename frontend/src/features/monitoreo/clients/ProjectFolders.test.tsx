import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import type { ComponentInput } from '../api/types'
import { reply } from '../test/mockApi'
import { mockPortalAdmin } from '../test/portalApi'
import { renderWithProviders } from '../test/render'
import { ProjectAdminPage } from './ProjectAdminPage'

vi.mock('uplot', () => ({ default: class {} }))

const W = '/var/www/clientes.nexosmart.com.ar'

function renderProject() {
  return renderWithProviders(
    <Routes>
      <Route path="/monitoreo/clientes/:id/proyectos/:pid" element={<ProjectAdminPage />} />
    </Routes>,
    { route: '/monitoreo/clientes/1/proyectos/1', admin: true },
  )
}

// Arranca plegado: se abre como lo haría el equipo, con un clic en el título.
async function foldersPanel(user: ReturnType<typeof userEvent.setup>) {
  const heading = await screen.findByRole('heading', { name: /^Carpetas vinculadas · / })
  const details = heading.closest('details') as HTMLDetailsElement
  expect(details.open).toBe(false)
  await user.click(heading)
  expect(details.open).toBe(true)
  return heading.closest('section') as HTMLElement
}
const row = (group: HTMLElement, name: string) => within(group).getByText(name, { selector: 'label' }).closest('li') as HTMLElement

afterEach(() => vi.restoreAllMocks())

describe('carpetas vinculadas (D-044)', () => {
  it('lista el inventario agrupado por servidor y no deja tildar las carpetas de otro cliente', async () => {
    const user = userEvent.setup()
    mockPortalAdmin()
    renderProject()
    const panel = await foldersPanel(user)
    const clientes = await within(panel).findByRole('region', { name: 'Servidor clientes' })
    const reaquila = within(panel).getByRole('region', { name: 'Servidor reaquila' })
    expect(within(panel).getByRole('region', { name: 'Servidor cms-demo' })).toBeInTheDocument()

    const playinn = row(clientes, 'playinn')
    expect(within(playinn).getByRole('checkbox')).toBeChecked()
    expect(playinn).toHaveTextContent(/Actualizado hace 3 d/)

    const olimpo = row(reaquila, 'clubolimpo.com.ar')
    expect(within(olimpo).getByRole('checkbox')).toBeDisabled()
    expect(olimpo).toHaveTextContent('ya es de: Club Olimpo · Sitio del club')
    expect(within(olimpo).getByRole('link', { name: 'Club Olimpo · Sitio del club' })).toHaveAttribute('href', '/monitoreo/clientes/2/proyectos/2')
  })

  it('guarda la selección y avisa los rechazos del back', async () => {
    const user = userEvent.setup()
    const { routes, api } = mockPortalAdmin()
    renderProject()
    const panel = await foldersPanel(user)
    await user.type(within(panel).getByRole('searchbox', { name: 'Buscar carpeta' }), 'medplace-app')
    const clientes = await within(panel).findByRole('region', { name: 'Servidor clientes' })
    expect(within(panel).queryByRole('region', { name: 'Servidor reaquila' })).not.toBeInTheDocument()
    await user.click(within(row(clientes, 'medplace-app')).getByRole('checkbox'))
    await user.click(within(panel).getByRole('button', { name: 'Guardar carpetas' }))

    expect(await screen.findByRole('heading', { name: 'Carpetas vinculadas · 2' })).toBeInTheDocument()
    expect(api.put.mock.calls[0]?.[1]).toEqual({ folders: [{ server_id: 5, path: `${W}/playinn` }, { server_id: 5, path: `${W}/medplace-app` }] })

    routes['PUT portal-admin/projects/:id/folders'] = reply({
      status: 422, message: 'Revisá los campos marcados.', errors: { folders: ['medplace-cms ya es de Otro (Otro proyecto).'] },
    })
    await user.clear(within(panel).getByRole('searchbox', { name: 'Buscar carpeta' }))
    await user.type(within(panel).getByRole('searchbox', { name: 'Buscar carpeta' }), 'medplace-cms')
    await user.click(within(row(within(panel).getByRole('region', { name: 'Servidor clientes' }), 'medplace-cms')).getByRole('checkbox'))
    await user.click(within(panel).getByRole('button', { name: 'Guardar carpetas' }))
    expect(await within(panel).findByText('medplace-cms ya es de Otro (Otro proyecto).')).toBeInTheDocument()
  })

  it('propone componentes a partir de las carpetas y agrega los elegidos', async () => {
    const user = userEvent.setup()
    const { api } = mockPortalAdmin()
    renderProject()
    const panel = (await screen.findByRole('heading', { name: 'Sugerencias de componentes' })).closest('section') as HTMLElement
    const list = await within(panel).findByRole('list', { name: 'Sugerencias' })
    // playinn-queue ya es un componente; playinn-scheduler y la app de PM2 son nuevos.
    const existing = within(list).getByText('clientes · playinn-queue').closest('li') as HTMLElement
    expect(within(existing).getByRole('checkbox')).toBeDisabled()
    expect(existing).toHaveTextContent('ya agregado')
    expect(within(list).getByText('clientes · playinn-scheduler')).toBeInTheDocument()
    expect(within(list).getByText('clientes · playinn-web (root)')).toBeInTheDocument() // la app de PM2 con su usuario (NV-10)
    // Las partes vienen pre-tildadas (D-045): un proceso nuevo, en las que ya tiene el proyecto; se corrigen antes de agregar.
    expect(within(existing).queryByRole('group')).not.toBeInTheDocument() // lo ya agregado se cambia en el componente
    const scheduler = within(list).getByText('clientes · playinn-scheduler').closest('li') as HTMLElement
    const parts = within(scheduler).getByRole('group', { name: /^Partes que afecta/ })
    expect(within(parts).getAllByRole('checkbox').map((box) => (box as HTMLInputElement).checked)).toEqual([true, true, true])
    await user.click(within(parts).getByRole('checkbox', { name: 'Web' }))

    await user.click(within(panel).getByRole('button', { name: /^Agregar seleccionados/ }))
    await waitFor(() => expect(within(panel).queryByRole('button', { name: /^Agregar seleccionados/ })).not.toBeInTheDocument())
    const added = api.post.mock.calls.filter((call) => String(call[0]).endsWith('/components')).map((call) => call[1] as ComponentInput)
    expect(added.map((input) => input.target)).toEqual(['playinn-scheduler', 'playinn-web'])
    expect(added.find((input) => input.target === 'playinn-scheduler')?.parts).toEqual(['app', 'cms'])
    expect(added.find((input) => input.target === 'playinn-web')?.target_owner).toBe('root')
  })
})
