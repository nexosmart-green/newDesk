import { screen } from '@testing-library/react'
import { LocationProbe } from '@/test/LocationProbe'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import * as authApi from './authApi'
import { RequireAuth } from './RequireAuth'

function renderProtected(initialPath: string) {
  return renderRoutes(
    [
      { path: '/login', element: <LocationProbe /> },
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

  it('sends guests to the login', async () => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(null)
    renderProtected('/')

    expect(await screen.findByTestId('location')).toHaveTextContent('/login')
  })
})
