import { screen, within } from '@testing-library/react'
import { Route, Routes } from 'react-router'
import { RequireAuth } from '@/features/auth/RequireAuth'
import { mockClientPortal } from '../test/clientPortalApi'
import { olimpoUser, playinnUser } from '../test/fixtures/clientPortal'
import { teamAdmin } from '../test/fixtures/portal'
import { renderWithProviders } from '../test/render'
import { PortalHome, PortalLayout, PortalProjectPage, RequireClient } from './PortalPages'

// jsdom no dibuja en canvas ni trae ResizeObserver: el gráfico de tiempos se monta sobre dobles inertes.
vi.mock('uplot', () => ({ default: class { setData() {} setSize() {} destroy() {} } }))
vi.stubGlobal('ResizeObserver', class { observe() {} disconnect() {} })

function renderApp(route: string) {
  return renderWithProviders(
    <Routes>
      <Route
        path="/portal"
        element={
          <RequireClient>
            <PortalLayout />
          </RequireClient>
        }
      >
        <Route index element={<PortalHome />} />
        <Route path=":slug" element={<PortalProjectPage />} />
      </Route>
      <Route path="/portal/login" element={<div>login del cliente</div>} />
      <Route element={<RequireAuth />}>
        <Route path="/" element={<div>panel del equipo</div>} />
        <Route path="/monitoreo" element={<div>monitoreo del equipo</div>} />
      </Route>
    </Routes>,
    { route },
  )
}

afterEach(() => vi.restoreAllMocks())

describe('portal del cliente', () => {
  it('con un solo proyecto entra directo a su página de estado', async () => {
    mockClientPortal(playinnUser)
    renderApp('/portal')
    expect(await screen.findByRole('heading', { name: 'Playinn', level: 1 })).toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent('Degradado: problemas en App y CMS')
    expect(screen.getByRole('button', { name: 'Salir' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Playinn' })).toHaveAttribute('href', '/portal')
    // El cliente ve su proyecto como Web, App y CMS (D-045): ni la API, ni los workers, ni los backups aparecen como tales.
    const parts = screen.getByRole('heading', { name: 'Componentes' }).closest('section') as HTMLElement
    expect(within(parts).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      expect.stringMatching(/^WebFunciona normalmente/),
      expect.stringMatching(/^AppResponde lento/),
      expect.stringMatching(/^CMSResponde lento/),
    ])
    expect(parts).not.toHaveTextContent(/API|Workers|Backups/)
    // El logo del portal, por la ruta espejo del portal.
    expect(screen.getByRole('img', { name: 'Logo de Playinn' })).toHaveAttribute('src', '/api/v1/portal/projects/playinn/logo?v=1759320000')
  })

  it('muestra 90 días de disponibilidad y aplica el color de la marca', async () => {
    mockClientPortal(playinnUser)
    renderApp('/portal/playinn')
    const days = within(await screen.findByRole('list', { name: 'Disponibilidad por día' })).getAllByRole('listitem')
    expect(days).toHaveLength(90)
    expect(days.at(-1)).toHaveAttribute('title', expect.stringMatching(/degradado$/))
    expect(screen.getByTestId('portal-brand').style.getPropertyValue('--accent')).toBe('#e11d48')
    expect(screen.getByText(/este mes · .* en 90 días/)).toBeInTheDocument()
    expect(screen.getByText('Último backup OK hace 5 h')).toBeInTheDocument()
  })

  it('con varios proyectos los lista y cada uno lleva a su estado', async () => {
    mockClientPortal(olimpoUser)
    renderApp('/portal')
    expect(await screen.findByRole('heading', { name: 'Tus proyectos' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Club Olimpo' })).toHaveAttribute('href', '/portal')
    expect(screen.getByRole('link', { name: /Sitio del club/ })).toHaveAttribute('href', '/portal/olimpo-web')
    expect(screen.getByRole('link', { name: /App de socios.*Caído.*En resolución/ })).toHaveAttribute('href', '/portal/olimpo-app')
    expect(screen.getByRole('link', { name: /Sitio del club/ })).not.toHaveTextContent('En resolución')
  })

  it('avisa que el equipo ya está resolviendo la caída, solo si hay incidente', async () => {
    const notice = 'Estamos al tanto del problema y ya lo estamos resolviendo. Lo vas a ver normalizado acá apenas esté.'
    mockClientPortal(olimpoUser)
    const { unmount } = renderApp('/portal/olimpo-app')
    expect(await screen.findByRole('heading', { name: 'App de socios', level: 1 })).toBeInTheDocument()
    expect(screen.getAllByRole('status').some((each) => each.textContent === notice)).toBe(true)
    // Con varios proyectos, la vuelta a la lista.
    expect(await screen.findByRole('link', { name: 'Tus proyectos' })).toHaveAttribute('href', '/portal')
    unmount()

    renderApp('/portal/olimpo-web')
    expect(await screen.findByRole('heading', { name: 'Sitio del club', level: 1 })).toBeInTheDocument()
    expect(screen.queryByText(notice)).not.toBeInTheDocument()
  })

  it('un proyecto de otro cliente no existe para él', async () => {
    mockClientPortal(playinnUser)
    renderApp('/portal/olimpo-web')
    expect(await screen.findByText('No encontramos ese proyecto.')).toBeInTheDocument()
  })

  it('el botón de reportar lleva al formulario y el de reportes a la lista', async () => {
    mockClientPortal(playinnUser)
    renderApp('/portal/playinn')
    expect(await screen.findByRole('link', { name: 'Reportar un problema' })).toHaveAttribute('href', '/portal/playinn/reportar')
    expect(screen.getByRole('link', { name: 'Mis reportes' })).toHaveAttribute('href', '/portal/playinn/reportes')
  })
})

describe('cada rol en su zona', () => {
  it('un cliente que entra al panel del equipo vuelve a su portal', async () => {
    mockClientPortal(playinnUser)
    renderApp('/monitoreo')
    expect(await screen.findByText('Degradado: problemas en App y CMS')).toBeInTheDocument()
    expect(screen.queryByText('monitoreo del equipo')).not.toBeInTheDocument()
  })

  it('el equipo que entra al portal vuelve al panel', async () => {
    mockClientPortal(teamAdmin)
    renderApp('/portal/playinn')
    expect(await screen.findByText('panel del equipo')).toBeInTheDocument()
  })

  it('sin sesión, el portal pide el código del cliente', async () => {
    mockClientPortal(null)
    renderApp('/portal/playinn')
    expect(await screen.findByText('login del cliente')).toBeInTheDocument()
  })
})
