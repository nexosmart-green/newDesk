import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, renderHook } from '@testing-library/react'
import type { ReactNode } from 'react'
import { currentUserKey } from '@/features/auth/useAuth'
import { createEcho } from '@/lib/echo'
import { keys } from '../api/useMonitoring'
import { playinnUser } from '../test/fixtures/clientPortal'
import { teamAdmin } from '../test/fixtures/portal'
import { usePortalEvents } from './usePortalEvents'

vi.mock('@/lib/echo', () => ({ createEcho: vi.fn() }))

// Doble de Echo: guarda los listeners para dispararlos desde el test.
function fakeEcho() {
  const listeners = new Map<string, (payload: unknown) => void>()
  const channel = {
    listen: (event: string, callback: (payload: unknown) => void) => (listeners.set(event, callback), channel),
    error: () => channel,
  }
  const echo = { private: vi.fn(() => channel), disconnect: vi.fn() }
  return { echo, listeners }
}

function setup(user: typeof playinnUser) {
  const fake = fakeEcho()
  vi.mocked(createEcho).mockReturnValue(fake.echo as unknown as ReturnType<typeof createEcho>)
  const client = new QueryClient()
  client.setQueryData(currentUserKey, user)
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
  return { fake, client, ...renderHook(() => usePortalEvents(), { wrapper }) }
}

afterEach(() => vi.clearAllMocks())

describe('usePortalEvents', () => {
  it('escucha el canal privado de su cliente y refresca los reportes del proyecto (solo ids, como el SSE del portal)', () => {
    const { fake, client, unmount } = setup(playinnUser)
    expect(fake.echo.private).toHaveBeenCalledWith('portal.client.1')
    expect([...fake.listeners.keys()]).toEqual(['.report', '.report.message'])

    client.setQueryData(keys.portalReports('playinn'), [])
    client.setQueryData(keys.portalReport('playinn', 3), { report: {}, messages: [] })
    client.setQueryData(keys.portalReports('olimpo-web'), [])
    act(() => fake.listeners.get('.report.message')?.({ report_id: 3, project_slug: 'playinn' }))
    // La lista y el chat abierto del proyecto; los de otro proyecto no.
    expect(client.getQueryState(keys.portalReports('playinn'))?.isInvalidated).toBe(true)
    expect(client.getQueryState(keys.portalReport('playinn', 3))?.isInvalidated).toBe(true)
    expect(client.getQueryState(keys.portalReports('olimpo-web'))?.isInvalidated).toBe(false)

    // Lo que no es un evento de reporte se ignora.
    client.setQueryData(keys.portalReports('olimpo-web'), [])
    act(() => fake.listeners.get('.report')?.({ project_slug: 'olimpo-web' }))
    expect(client.getQueryState(keys.portalReports('olimpo-web'))?.isInvalidated).toBe(false)

    unmount()
    expect(fake.echo.disconnect).toHaveBeenCalled()
  })

  it('el equipo no se conecta al canal del portal', () => {
    const { fake } = setup(teamAdmin)
    expect(createEcho).not.toHaveBeenCalled()
    expect(fake.echo.private).not.toHaveBeenCalled()
  })
})
