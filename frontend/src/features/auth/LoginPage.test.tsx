import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router'
import { ApiError } from '@/lib/api'
import * as authApi from './authApi'
import { LoginPage } from './LoginPage'

function renderLogin() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <LoginPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('LoginPage', () => {
  it('shows the backend validation message when credentials are wrong', async () => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(null)
    vi.spyOn(authApi, 'login').mockRejectedValue(
      new ApiError(422, 'Datos inválidos', { email: ['Credenciales incorrectas.'] }),
    )
    renderLogin()

    await userEvent.type(screen.getByLabelText('Email'), 'ana@nexosmart.com')
    await userEvent.type(screen.getByLabelText('Contraseña'), 'wrong-password')
    await userEvent.click(screen.getByRole('button', { name: 'Ingresar' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Credenciales incorrectas.')
  })
})
