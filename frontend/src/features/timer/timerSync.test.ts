import { broadcastTimerChanged, subscribeTimerChanged } from './timerSync'

describe('timerSync', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('notifies other tabs through BroadcastChannel (RNF-12)', async () => {
    const onChange = vi.fn()
    const unsubscribe = subscribeTimerChanged(onChange)

    broadcastTimerChanged()

    await vi.waitFor(() => expect(onChange).toHaveBeenCalledTimes(1))
    unsubscribe()
  })

  it('falls back to the storage event without BroadcastChannel', () => {
    vi.stubGlobal('BroadcastChannel', undefined)
    const onChange = vi.fn()
    const unsubscribe = subscribeTimerChanged(onChange)

    window.dispatchEvent(new StorageEvent('storage', { key: 'newdesk:timer-changed', newValue: '1' }))
    window.dispatchEvent(new StorageEvent('storage', { key: 'otra-clave', newValue: '1' }))

    expect(onChange).toHaveBeenCalledTimes(1)
    unsubscribe()
    expect(() => broadcastTimerChanged()).not.toThrow()
  })
})
