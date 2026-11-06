import { shiftWeek, weekLabel, weekOf } from './week'

describe('week helpers', () => {
  it('maps a Sunday to the Monday that starts its week', () => {
    expect(weekOf(new Date(2026, 10, 8, 23, 30))).toEqual({ from: '2026-11-02', to: '2026-11-08' })
  })

  it('keeps the local day late at night (no UTC shift)', () => {
    expect(weekOf(new Date(2026, 10, 2, 23, 59))).toEqual({ from: '2026-11-02', to: '2026-11-08' })
    expect(weekOf(new Date(2026, 10, 2, 0, 1))).toEqual({ from: '2026-11-02', to: '2026-11-08' })
  })

  it('shifts whole weeks across month boundaries', () => {
    expect(shiftWeek('2026-11-02', -1)).toEqual({ from: '2026-10-26', to: '2026-11-01' })
    expect(shiftWeek('2026-11-30', 1)).toEqual({ from: '2026-12-07', to: '2026-12-13' })
  })

  it('labels the week', () => {
    expect(weekLabel({ from: '2026-11-02', to: '2026-11-08' })).toMatch(/^2 nov – 8 nov 2026$/)
  })
})
