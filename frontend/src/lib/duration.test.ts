import { formatClock, formatDuration } from './duration'

describe('formatDuration', () => {
  it('renders integer seconds as hours and minutes', () => {
    expect(formatDuration(0)).toBe('0h 00m')
    expect(formatDuration(3660)).toBe('1h 01m')
    expect(formatDuration(36_000)).toBe('10h 00m')
  })

  it('never renders negative durations', () => {
    expect(formatDuration(-30)).toBe('0h 00m')
  })
})

describe('formatClock', () => {
  it('renders HH:MM:SS', () => {
    expect(formatClock(669)).toBe('00:11:09')
  })
})
