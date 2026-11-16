import { useMutation } from '@tanstack/react-query'
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { FormEvent } from 'react'
import { useLocation } from 'react-router'
import { Button } from '@/components/ui/Button'
import { TextField } from '@/components/ui/TextField'
import * as authApi from '@/features/auth/authApi'
import { RequireAuth } from '@/features/auth/RequireAuth'
import { buildUser, renderAt, renderRoutes } from '@/test/renderWithProviders'
import { ApiError, fieldErrorOf } from './api'

const toastRegion = () => screen.getByRole('region', { name: 'Avisos' })

function NameForm({ fail }: { fail: ApiError }) {
  const mutation = useMutation({
    mutationFn: async () => {
      throw fail
    },
  })
  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    mutation.mutate()
  }
  return (
    <form onSubmit={handleSubmit}>
      <TextField label="Notas" />
      <TextField label="Nombre" error={fieldErrorOf(mutation.error, 'name')} />
      <Button type="submit">Guardar cambios</Button>
    </form>
  )
}

describe('mutation field errors', () => {
  it('toasts a validation message no input on screen shows, keeps the rest under their fields and focuses the first one', async () => {
    const fail = new ApiError(422, 'Revisá los datos.', {
      name: ['El nombre es obligatorio.'],
      'assignee_ids.3': ['Esa persona ya no es miembro del proyecto.'],
    })
    renderAt(<NameForm fail={fail} />)

    await userEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(await within(toastRegion()).findByText('Esa persona ya no es miembro del proyecto.')).toBeInTheDocument()
    expect(within(toastRegion()).queryByText('El nombre es obligatorio.')).not.toBeInTheDocument()
    expect(screen.getByText('El nombre es obligatorio.')).toBeInTheDocument()
    expect(screen.getByLabelText('Nombre')).toHaveFocus()
  })

  it('stays quiet when every field error is under its input', async () => {
    renderAt(<NameForm fail={new ApiError(422, 'Revisá los datos.', { name: ['El nombre es obligatorio.'] })} />)

    await userEvent.click(screen.getByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByText('El nombre es obligatorio.')).toBeInTheDocument()
    await new Promise((resolve) => setTimeout(resolve, 150))
    expect(within(toastRegion()).queryAllByRole('listitem')).toHaveLength(0)
  })
})

function FailingAction() {
  const mutation = useMutation({
    mutationFn: async () => {
      throw new ApiError(401, 'Unauthenticated.')
    },
  })
  return <Button onClick={() => mutation.mutate()}>Guardar cambios</Button>
}

function LoginProbe() {
  const state = useLocation().state as { from?: string } | null
  return <p data-testid="login">Volver a {state?.from}</p>
}

describe('lost session', () => {
  afterEach(() => vi.restoreAllMocks())

  it('says the session closed and sends to /login keeping where the person was', async () => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser())
    renderRoutes(
      [
        { element: <RequireAuth />, children: [{ path: '/tareas', element: <FailingAction /> }] },
        { path: '/login', element: <LoginProbe /> },
      ],
      '/tareas?estado=abierta',
    )

    await userEvent.click(await screen.findByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByTestId('login')).toHaveTextContent('Volver a /tareas?estado=abierta')
    expect(within(toastRegion()).getByText('Tu sesión se cerró. Ingresá de nuevo para seguir.')).toBeInTheDocument()
  })
})
