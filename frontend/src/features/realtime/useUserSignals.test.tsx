import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, renderHook } from '@testing-library/react'
import type { ReactNode } from 'react'
import type { CurrentUser } from '@/features/auth/types'
import { currentUserKey } from '@/features/auth/useAuth'
import { createEcho } from '@/lib/echo'
import { hoursKey, notificationsKey, timerKey } from '@/lib/queryKeys'
import { SAFETY_NET_POLL_MS, usePollInterval } from './realtimeStatus'
import { useUserSignals } from './useUserSignals'

vi.mock('@/lib/echo', () => ({ createEcho: vi.fn() }))

/** Echo double: keeps the listeners so the test can fire them. */
function fakeEcho() {
  const listeners = new Map<string, () => void>()
  let onSubscribed = () => {}
  let onStatus: (status: string) => void = () => {}
  const channel = {
    listen: (event: string, callback: () => void) => (listeners.set(event, callback), channel),
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

function renderSignals(echo: ReturnType<typeof fakeEcho>['echo'] | null) {
  vi.mocked(createEcho).mockReturnValue(echo as unknown as ReturnType<typeof createEcho>)
  const client = new QueryClient()
  client.setQueryData(currentUserKey, { id: 7 } as CurrentUser)
  for (const key of [timerKey, [...hoursKey, 'entries'], notificationsKey]) client.setQueryData(key, {})
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
  const view = renderHook(
    () => {
      useUserSignals()
      return usePollInterval(30_000)
    },
    { wrapper },
  )
  const invalidated = (key: readonly unknown[]) => client.getQueryState(key)?.isInvalidated
  return { ...view, invalidated }
}

describe('useUserSignals', () => {
  it('listens to the person channel and refreshes what each signal names', () => {
    const fake = fakeEcho()
    const { invalidated } = renderSignals(fake.echo)

    expect(fake.echo.private).toHaveBeenCalledWith('user.7')
    expect([...fake.listeners.keys()]).toEqual(['.timer', '.notifications'])

    act(() => fake.listeners.get('.notifications')?.())
    expect(invalidated(notificationsKey)).toBe(true)
    expect(invalidated(timerKey)).toBe(false)

    act(() => fake.listeners.get('.timer')?.())
    expect(invalidated(timerKey)).toBe(true)
    expect(invalidated([...hoursKey, 'entries'])).toBe(true)
  })

  it('slows polling to a safety net while subscribed, back to normal after a drop, and catches up on reconnect', () => {
    const fake = fakeEcho()
    const { result, invalidated, unmount } = renderSignals(fake.echo)
    expect(result.current).toBe(30_000)

    act(() => fake.status('connecting'))
    act(() => fake.subscribe())
    expect(result.current).toBe(SAFETY_NET_POLL_MS)
    expect(invalidated(timerKey)).toBe(false)

    act(() => fake.status('unavailable'))
    expect(result.current).toBe(30_000)

    act(() => fake.subscribe())
    expect(result.current).toBe(SAFETY_NET_POLL_MS)
    expect(invalidated(timerKey)).toBe(true)
    expect(invalidated(notificationsKey)).toBe(true)

    unmount()
    expect(fake.echo.disconnect).toHaveBeenCalled()
  })

  it('without Reverb keeps the usual polling', () => {
    const { result } = renderSignals(null)
    expect(result.current).toBe(30_000)
  })
})
