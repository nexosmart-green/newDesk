import { renderHook } from '@testing-library/react'
import { api } from '@/lib/api'
import { useLiveMode } from './useLiveMode'

describe('useLiveMode', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  it('late al montar y cada 20 s, y se apaga al desmontar', () => {
    const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: null } })
    const { unmount } = renderHook(() => useLiveMode(7))
    expect(post).toHaveBeenCalledWith('/monitoring/servers/7/live', {})

    vi.advanceTimersByTime(20_000)
    expect(post).toHaveBeenCalledTimes(2)

    unmount()
    vi.advanceTimersByTime(60_000)
    expect(post).toHaveBeenCalledTimes(2)
  })

  it('no late con la pestaña oculta', () => {
    const post = vi.spyOn(api, 'post').mockResolvedValue({ data: { data: null } })
    vi.spyOn(document, 'hidden', 'get').mockReturnValue(true)
    renderHook(() => useLiveMode(7))
    vi.advanceTimersByTime(40_000)
    expect(post).not.toHaveBeenCalled()
  })
})
