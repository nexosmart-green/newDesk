import { screen } from '@testing-library/react'
import { LocationProbe } from '@/test/LocationProbe'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import * as authApi from './authApi'
import { RequireAuth } from './RequireAuth'

function renderProtected(initialPath: string) {
  return renderRoutes(
    [
      { path: '/login', element: <LocationProbe /> },
      { path: '/portal', element: <LocationProbe /> },
      {
        element: <RequireAuth />,
        children: [
          { path: '/', element: <p>Inicio</p> },
          { path: '/seguridad', element: <LocationProbe /> },
        ],
      },
    ],
    initialPath,
  )
}

describe('RequireAuth', () => {
  it('forces the 2FA setup page when the role requires it and it is off', async () => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(
      buildUser({ two_factor: { required: true, enabled: false } }),
    )
    renderProtected('/')

    expect(await screen.findByTestId('location')).toHaveTextContent('/seguridad')
    expect(screen.queryByText('Inicio')).not.toBeInTheDocument()
  })

  it('lets the user through once 2FA is enabled', async () => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ two_factor: { required: true, enabled: true } }))
    renderProtected('/')

    expect(await screen.findByText('Inicio')).toBeInTheDocument()
  })

  it('sends a client user to the portal, never to the 2FA setup', async () => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(
      buildUser({ client: { id: 1, name: 'Playinn' }, two_factor: { required: true, enabled: false } }),
    )
    renderProtected('/')

    expect(await screen.findByTestId('location')).toHaveTextContent('/portal')
  })

  it('sends guests to the login', async () => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(null)
    renderProtected('/')

    expect(await screen.findByTestId('location')).toHaveTextContent('/login')
  })
})
