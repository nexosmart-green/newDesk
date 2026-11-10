import { broadcastTimerChanged, subscribeTimerChanged } from './timerSync'

describe('timerSync', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('reacts to another tab through BroadcastChannel (RNF-12)', async () => {
    const onChange = vi.fn()
    const unsubscribe = subscribeTimerChanged(onChange)
    const otherTab = new BroadcastChannel('newdesk-timer')

    otherTab.postMessage('another-tab-id')

    await vi.waitFor(() => expect(onChange).toHaveBeenCalledTimes(1))
    otherTab.close()
    unsubscribe()
  })

  it('ignores its own broadcast: this tab already refreshed (no double fetch)', async () => {
    const onChange = vi.fn()
    const unsubscribe = subscribeTimerChanged(onChange)
    const probe = vi.fn()
    const listener = new BroadcastChannel('newdesk-timer')
    listener.onmessage = probe

    broadcastTimerChanged()

    await vi.waitFor(() => expect(probe).toHaveBeenCalledTimes(1))
    expect(onChange).not.toHaveBeenCalled()
    listener.close()
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
