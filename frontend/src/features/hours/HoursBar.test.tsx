import { render, screen } from '@testing-library/react'
import { buildHourBag } from '@/test/fixtures'
import { hoursToSeconds } from './durationInput'
import { HoursBar } from './HoursBar'
import { bagLevelOf } from './types'

describe('bagLevelOf (RF-03.05)', () => {
  it.each([
    [null, 'ok'],
    [0, 'ok'],
    [79, 'ok'],
    [80, 'warning'],
    [99, 'warning'],
    [100, 'exhausted'],
    [130, 'exhausted'],
  ] as const)('%s%% → %s', (percent, level) => {
    expect(bagLevelOf(percent)).toBe(level)
  })
})

describe('HoursBar', () => {
  it.each([
    [79, 'ok', 'bg-success'],
    [80, 'warning', 'bg-warning'],
    [100, 'exhausted', 'bg-danger'],
  ] as const)('paints %s%% as %s', (percent, level, fill) => {
    render(<HoursBar bag={buildHourBag({ percent_consumed: percent, is_exhausted: percent >= 100 })} />)

    const meter = screen.getByRole('meter', { name: 'Horas consumidas' })
    expect(meter).toHaveAttribute('data-level', level)
    expect(meter.firstElementChild).toHaveClass(fill)
    expect(screen.getByText(`${percent}% consumido`)).toBeInTheDocument()
  })

  it('says it in words when the bag is exhausted, not only in red', () => {
    render(<HoursBar bag={buildHourBag({ percent_consumed: 100, available_seconds: 0, is_exhausted: true })} />)

    expect(screen.getByText('Sin horas disponibles')).toBeInTheDocument()
  })

  it('shows Bolsa / Consumidas / Disponibles in h m', () => {
    render(<HoursBar bag={buildHourBag({ allocated_seconds: 36000, consumed_seconds: 7200, available_seconds: 28800 })} />)

    expect(screen.getByText('10h 00m')).toBeInTheDocument()
    expect(screen.getByText('2h 00m')).toBeInTheDocument()
    expect(screen.getByText('8h 00m')).toBeInTheDocument()
  })

  it('internal projects have no cap (RF-05.14)', () => {
    render(<HoursBar bag={buildHourBag({ has_cap: false, allocated_seconds: null, available_seconds: null, percent_consumed: null, consumed_seconds: 5400 })} />)

    expect(screen.queryByRole('meter')).not.toBeInTheDocument()
    expect(screen.getByText('Interno: sin tope · Consumidas 1h 30m')).toBeInTheDocument()
  })
})

describe('hoursToSeconds', () => {
  it('reads decimal hours with comma or dot as integer seconds', () => {
    expect(hoursToSeconds('2,5')).toBe(9000)
    expect(hoursToSeconds('2.5')).toBe(9000)
    expect(hoursToSeconds(' 0,75 ')).toBe(2700)
    expect(hoursToSeconds('-1,5')).toBe(-5400)
    expect(hoursToSeconds('3')).toBe(10800)
  })

  it('rejects what is not a number', () => {
    expect(hoursToSeconds('')).toBeNull()
    expect(hoursToSeconds('abc')).toBeNull()
    expect(hoursToSeconds('2h')).toBeNull()
    expect(hoursToSeconds('1,2,3')).toBeNull()
  })
})
