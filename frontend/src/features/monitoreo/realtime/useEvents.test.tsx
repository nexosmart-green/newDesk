import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, renderHook } from '@testing-library/react'
import type { ReactNode } from 'react'
import { currentUserKey } from '@/features/auth/useAuth'
import type { CurrentUser } from '@/features/auth/types'
import { createEcho, isRealtimeConfigured } from '@/lib/echo'
import { keys } from '../api/useMonitoring'
import type { ServerSummary } from '../api/types'
import { useEvents } from './useEvents'

vi.mock('@/lib/echo', () => ({ createEcho: vi.fn(), isRealtimeConfigured: vi.fn(() => true) }))

// Doble de Echo: guarda los listeners para dispararlos desde el test.
function fakeEcho() {
  const listeners = new Map<string, (payload: unknown) => void>()
  let onSubscribed = () => {}
  let onStatus: (status: string) => void = () => {}
  const channel = {
    listen: (event: string, callback: (payload: unknown) => void) => (listeners.set(event, callback), channel),
    subscribed: (callback: () => void) => ((onSubscribed = callback), channel),
    error: () => channel,
  }
  const echo = {
    private: vi.fn(() => channel),
    disconnect: vi.fn(),
    connector: { onConnectionChange: (callback: (status: string) => void) => ((onStatus = callback), () => {}) },
  }
  return { echo, listeners, subscribe: () => onSubscribed(), status: (value: string) => onStatus(value) }
}

const server = { id: 1, name: 'reaquila', status: 'online', last_seen_at: null } as ServerSummary

describe('useEvents', () => {
  it('escucha el canal privado de su empresa con los nombres de broadcastAs y aplica los eventos', () => {
    const fake = fakeEcho()
    vi.mocked(createEcho).mockReturnValue(fake.echo as unknown as ReturnType<typeof createEcho>)
    const client = new QueryClient()
    client.setQueryData(keys.servers, [server])
    client.setQueryData(currentUserKey, { company: { id: 3 } } as CurrentUser)
    const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
    const { result, unmount } = renderHook(() => useEvents(), { wrapper })

    expect(fake.echo.private).toHaveBeenCalledWith('monitoring.3')
    expect([...fake.listeners.keys()]).toEqual(['.server.status', '.metrics', '.snapshot', '.alert'])
    expect(result.current).toBe('connecting')

    act(() => fake.subscribe())
    expect(result.current).toBe('open')

    act(() => fake.listeners.get('.server.status')?.({ server_id: 1, status: 'down', last_seen_at: null }))
    expect(client.getQueryData<ServerSummary[]>(keys.servers)?.[0]?.status).toBe('down')

    act(() => fake.status('connecting'))
    expect(result.current).toBe('retrying')

    unmount()
    expect(fake.echo.disconnect).toHaveBeenCalled()
  })

  it('sin Reverb configurado queda en reconectando', () => {
    vi.mocked(isRealtimeConfigured).mockReturnValue(false)
    vi.mocked(createEcho).mockReturnValue(null)
    const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={new QueryClient()}>{children}</QueryClientProvider>
    const { result } = renderHook(() => useEvents(), { wrapper })
    expect(result.current).toBe('retrying')
  })
})
