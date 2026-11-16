import { useMutation } from '@tanstack/react-query'
import { act, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Button } from '@/components/ui/Button'
import { ApiError } from '@/lib/api'
import { renderAt } from '@/test/renderWithProviders'
import { TOAST_DURATION_MS } from './toastStore'

function SaveButton({ fail, silent = false }: { fail?: ApiError; silent?: boolean }) {
  const mutation = useMutation({
    meta: { successMessage: 'Horas cargadas.', silentError: silent },
    mutationFn: async () => {
      if (fail) throw fail
      return null
    },
  })
  return <Button onClick={() => mutation.mutate()}>Guardar</Button>
}

const toastRegion = () => screen.getByRole('region', { name: 'Avisos' })

describe('toasts', () => {
  afterEach(() => vi.useRealTimers())

  it('shows the success message of a mutation and leaves by itself after 4 s', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime })
    renderAt(<SaveButton />)
    await user.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await within(toastRegion()).findByText('Horas cargadas.')).toBeInTheDocument()
    act(() => vi.advanceTimersByTime(TOAST_DURATION_MS - 500))
    expect(within(toastRegion()).getByText('Horas cargadas.')).toBeInTheDocument()
    act(() => vi.advanceTimersByTime(600))
    expect(within(toastRegion()).queryByText('Horas cargadas.')).not.toBeInTheDocument()
  })

  it('shows a business-rule error with the backend message, closable by hand', async () => {
    renderAt(<SaveButton fail={new ApiError(422, 'Sin horas disponibles en Bahía Web.', {}, 'no_hours_available')} />)
    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }))

    const region = toastRegion()
    expect(await within(region).findByText('Sin horas disponibles en Bahía Web.')).toBeInTheDocument()
    expect(within(region).getByText('No se pudo completar')).toBeInTheDocument()
    await userEvent.click(within(region).getByRole('button', { name: 'Cerrar aviso' }))
    expect(within(region).queryByText('Sin horas disponibles en Bahía Web.')).not.toBeInTheDocument()
  })

  it('explains a network failure in plain words', async () => {
    renderAt(<SaveButton fail={new ApiError(0, '')} />)
    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Sin conexión con el servidor')).toBeInTheDocument()
    expect(screen.getByText('No se pudo llegar al servidor. Revisá la red y probá de nuevo.')).toBeInTheDocument()
  })

  it('stays quiet for mutations marked silentError (field errors: see lib/queryClient.test.tsx)', async () => {
    renderAt(<SaveButton fail={new ApiError(422, 'Contá qué hiciste.', {}, 'comment_required')} silent />)
    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }))

    await new Promise((resolve) => setTimeout(resolve, 150))
    expect(within(toastRegion()).queryAllByRole('listitem')).toHaveLength(0)
  })
})
