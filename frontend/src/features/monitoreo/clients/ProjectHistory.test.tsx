import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { mockPortalAdmin } from '../test/portalApi'
import { renderWithProviders } from '../test/render'
import { ProjectAdminPage } from './ProjectAdminPage'

vi.mock('uplot', () => ({ default: class {} }))

function renderProject() {
  return renderWithProviders(
    <Routes>
      <Route path="/monitoreo/clientes/:id/proyectos/:pid" element={<ProjectAdminPage />} />
    </Routes>,
    { route: '/monitoreo/clientes/1/proyectos/1', admin: true },
  )
}

const incidentsPanel = async () => (await screen.findByRole('heading', { name: 'Incidentes (90 días)' })).closest('section') as HTMLElement

afterEach(() => vi.restoreAllMocks())

describe('proyecto del portal: incidentes y PageSpeed', () => {
  it('el equipo ve los incidentes de 90 días con el autor de cada nota, y agrega y borra notas', async () => {
    const user = userEvent.setup()
    const { api } = mockPortalAdmin()
    renderProject()
    const panel = await incidentsPanel()
    expect(await within(panel).findAllByTestId('incident')).toHaveLength(2)
    expect(panel).toHaveTextContent('teamviolet@nexosmart.com.ar')

    const first = within(panel).getAllByTestId('incident')[0] as HTMLElement
    await user.click(within(first).getByRole('button', { name: 'Agregar nota' }))
    const form = within(first).getByRole('form', { name: 'Nueva nota' })
    await user.click(within(form).getByRole('button', { name: 'Resuelto: …' }))
    const box = within(form).getByLabelText('Nota para el cliente')
    expect(box).toHaveValue('Resuelto: ')
    await user.type(box, 'volvió el proveedor de pagos.')
    await user.click(within(form).getByRole('button', { name: 'Guardar nota' }))
    expect(await within(panel).findByText('Resuelto: volvió el proveedor de pagos.')).toBeInTheDocument()
    expect(api.post).toHaveBeenCalledWith('/monitoring/portal-admin/incidents/11/notes', { body: 'Resuelto: volvió el proveedor de pagos.' })

    // La nota también la ve el cliente: aparece en la vista previa.
    const preview = (await screen.findByRole('heading', { name: 'Vista previa' })).closest('section') as HTMLElement
    expect(await within(preview).findByText('Resuelto: volvió el proveedor de pagos.')).toBeInTheDocument()

    const note = within(panel).getByText('Investigando: el proveedor de pagos no responde.').closest('li') as HTMLElement
    await user.click(within(note).getByRole('button', { name: 'Borrar' }))
    await user.click(within(note).getByRole('button', { name: 'Confirmar' }))
    await waitFor(() => expect(within(panel).queryByText('Investigando: el proveedor de pagos no responde.')).not.toBeInTheDocument())
  })

  it('pide una medición de PageSpeed para los componentes web', async () => {
    const user = userEvent.setup()
    const { api } = mockPortalAdmin()
    renderProject()
    // Componentes arranca plegado: se abre con un clic en el título.
    const heading = await screen.findByRole('heading', { name: /^Componentes · / })
    expect((heading.closest('details') as HTMLDetailsElement).open).toBe(false)
    await user.click(heading)
    const buttons = await screen.findAllByRole('button', { name: 'Medir PageSpeed' })
    expect(buttons).toHaveLength(3) // los componentes web (Web, App y CMS); la API (health) no
    await user.click(buttons[0] as HTMLElement)
    expect(await screen.findByText('Midiendo: el resultado aparece en un par de minutos.')).toBeInTheDocument()
    expect(api.post).toHaveBeenCalledWith('/monitoring/portal-admin/components/1/pagespeed', {})
  })
})
