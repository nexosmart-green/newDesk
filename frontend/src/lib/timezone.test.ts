import { todayIn, zonedPartsOf } from './timezone'

describe('zonedPartsOf', () => {
  it('reads an UTC instant in the given timezone, not the browser one', () => {
    expect(zonedPartsOf('2026-11-10T12:00:00Z', 'America/Argentina/Buenos_Aires')).toEqual({ date: '2026-11-10', time: '09:00' })
    expect(zonedPartsOf('2026-11-10T02:30:00Z', 'America/Argentina/Buenos_Aires')).toEqual({ date: '2026-11-09', time: '23:30' })
    expect(zonedPartsOf('2026-11-10T23:30:00Z', 'Europe/Madrid')).toEqual({ date: '2026-11-11', time: '00:30' })
  })

  it('gives today in the timezone', () => {
    expect(todayIn('America/Argentina/Buenos_Aires', new Date('2026-11-07T01:00:00Z'))).toBe('2026-11-06')
  })
})
