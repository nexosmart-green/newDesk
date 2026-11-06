import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import type { ProjectVulns } from '../api/types'
import { mockPortalAdmin } from '../test/portalApi'
import { renderWithProviders } from '../test/render'
import { ProjectAdminPage } from './ProjectAdminPage'

vi.mock('uplot', () => ({ default: class {} }))

function renderProject(clientId: number, projectId: number) {
  return renderWithProviders(
    <Routes>
      <Route path="/monitoreo/clientes/:id/proyectos/:pid" element={<ProjectAdminPage />} />
    </Routes>,
    { route: `/monitoreo/clientes/${clientId}/proyectos/${projectId}`, admin: true },
  )
}

const vulnsPanel = async () => (await screen.findByRole('heading', { name: 'Vulnerabilidades de dependencias' })).closest('section') as HTMLElement

afterEach(() => vi.restoreAllMocks())

describe('vulnerabilidades de dependencias (D-053)', () => {
  it('muestra cada carpeta del proyecto con sus hallazgos, las limpias y las auditorías que fallaron', async () => {
    const user = userEvent.setup()
    mockPortalAdmin()
    renderProject(2, 2)
    const panel = await vulnsPanel()
    const api = (await within(panel).findByText('/var/www/clubolimpo.com.ar/api')).closest('li') as HTMLElement
    expect(api).toHaveTextContent('composer')
    expect(api).toHaveTextContent('1 alta')
    expect(api).toHaveTextContent('1 baja')
    await user.click(within(api).getByText('/var/www/clubolimpo.com.ar/api'))
    expect(within(api).getByRole('link', { name: /league\/commonmark allows cross-site scripting/ })).toHaveAttribute('href', 'https://github.com/advisories/GHSA-3527-qv2q-pfvx')
    expect(api).toHaveTextContent('CVE-2025-46734')

    const failed = within(panel).getByText('/var/www/clubolimpo.com.ar/olimpo_app').closest('li') as HTMLElement
    expect(failed).toHaveTextContent('la auditoría falló')
    expect(within(panel).getByText('/var/www/clubolimpo.com.ar/landing-olimpo').closest('li')).toHaveTextContent('limpia')
  })

  it('solo pone como link los advisories https; los demás quedan como texto (H18)', async () => {
    const user = userEvent.setup()
    const finding = (title: string, url: string) => ({ package: 'paquete', severity: 'high' as const, title, url })
    const vulns: ProjectVulns = {
      counts: { critical: 0, high: 4, moderate: 0, low: 0 },
      servers: [{ server_id: 1, server_name: 'reaquila', has_scan: true, scanned_at: null }],
      dirs: [{
        server_id: 1, server_name: 'reaquila', scanned_at: null, path: '/var/www/app', manager: 'npm', counts: { critical: 0, high: 4, moderate: 0, low: 0 },
        findings: [
          finding('Advisory seguro', 'https://github.com/advisories/GHSA-xxxx'),
          finding('Advisory en http', 'http://example.com/advisory'),
          finding('Advisory data', 'data:text/html,<script>alert(1)</script>'),
          finding('Advisory roto', 'no es una url'),
        ],
      }],
    }
    mockPortalAdmin({ 'GET portal-admin/projects/:id/vulnerabilities': vulns })
    renderProject(2, 2)
    const panel = await vulnsPanel()
    await user.click(await within(panel).findByText('/var/www/app'))
    expect(within(panel).getByRole('link', { name: 'Advisory seguro' })).toHaveAttribute('href', 'https://github.com/advisories/GHSA-xxxx')
    for (const title of ['Advisory en http', 'Advisory data', 'Advisory roto']) {
      expect(within(panel).getByText(title).closest('a')).toBeNull()
    }
  })

  it('avisa cuando el server de las carpetas no manda resultados', async () => {
    mockPortalAdmin()
    renderProject(1, 1)
    const panel = await vulnsPanel()
    expect(await within(panel).findByText(/clientes: este server no manda resultados/)).toBeInTheDocument()
  })
})

describe('desarrolladores del proyecto (D-053)', () => {
  it('elige el principal y los secundarios y los guarda con el proyecto', async () => {
    const user = userEvent.setup()
    const { state, api } = mockPortalAdmin()
    renderProject(1, 1)
    const lead = (await screen.findByRole('combobox', { name: 'Desarrollador principal' })) as HTMLSelectElement
    await waitFor(() => expect(lead).toHaveValue('1'))
    // El principal no aparece como secundario.
    const secondaries = screen.getByRole('group', { name: 'Desarrolladores secundarios' })
    expect(within(secondaries).queryByLabelText('teamviolet@nexosmart.com.ar')).not.toBeInTheDocument()

    await user.selectOptions(lead, 'dev@nexosmart.com.ar')
    await user.click(within(secondaries).getByLabelText('teamviolet@nexosmart.com.ar'))
    await user.click(screen.getByRole('button', { name: 'Guardar' }))
    expect(await screen.findByText('Guardado.')).toBeInTheDocument()
    expect(api.put.mock.calls[0]?.[1]).toMatchObject({ developers: { lead_user_id: 2, secondary_user_ids: [1] } })
    expect(state.projects[0]?.developers).toEqual([
      { user_id: 2, email: 'dev@nexosmart.com.ar', role: 'lead' },
      { user_id: 1, email: 'teamviolet@nexosmart.com.ar', role: 'secondary' },
    ])
  })
})
