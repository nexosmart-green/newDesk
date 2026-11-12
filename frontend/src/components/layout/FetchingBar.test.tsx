import { screen } from '@testing-library/react'
import { Link, Outlet } from 'react-router'
import { renderRoutes } from '@/test/renderWithProviders'
import { FetchingBar } from './FetchingBar'

const bar = () => document.querySelector('[data-state]')

function Shell() {
  return (
    <>
      <FetchingBar />
      <Link to="/lenta">Ir</Link>
      <Outlet />
    </>
  )
}

describe('FetchingBar', () => {
  it('shows while a lazy route loads its code, keeping the current page on screen', async () => {
    let loaded!: () => void
    const lazyPage = new Promise<void>((resolve) => (loaded = resolve))
    renderRoutes(
      [
        {
          element: <Shell />,
          children: [
            { path: '/', element: <p>Inicio</p> },
            {
              path: '/lenta',
              lazy: async () => {
                await lazyPage
                return { Component: () => <p>Página lenta</p> }
              },
            },
          ],
        },
      ],
      '/',
    )
    expect(bar()).toHaveAttribute('data-state', 'idle')

    screen.getByRole('link', { name: 'Ir' }).click()

    await vi.waitFor(() => expect(bar()).toHaveAttribute('data-state', 'busy'), { timeout: 2000 })
    expect(screen.getByText('Inicio')).toBeInTheDocument()

    loaded()
    expect(await screen.findByText('Página lenta')).toBeInTheDocument()
    await vi.waitFor(() => expect(bar()).toHaveAttribute('data-state', 'idle'))
  })
})
