import { render, screen, within } from '@testing-library/react'
import { DayList } from './DayList'

// Monday 9 to Sunday 15 November 2026.
const week = ['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14', '2026-11-15']

function renderWeek(busy: string[], today?: string) {
  render(
    <DayList
      label="Semana"
      dates={week}
      today={today}
      emptyText="sin registros"
      isEmpty={(date) => !busy.includes(date)}
      renderTotal={() => <span>2h 00m</span>}
      renderDay={(date) => <p>Trabajo del {date}</p>}
    />,
  )
}

describe('DayList', () => {
  it('is one list with a row per busy day and one short row per run of empty days', () => {
    renderWeek(['2026-11-09', '2026-11-10'])

    const list = screen.getByRole('list', { name: 'Semana' })
    expect(within(list).getAllByRole('listitem')).toHaveLength(3)
    expect(within(list).getByRole('region', { name: /lunes 9\/11/i })).toHaveTextContent('Trabajo del 2026-11-09')
    expect(within(list).getByText('Mié 11 a dom 15: sin registros')).toBeInTheDocument()
  })

  it('never folds today into a run, so it can always be found', () => {
    renderWeek(['2026-11-09'], '2026-11-12')

    expect(screen.getByText('Mar 10 a mié 11: sin registros')).toBeInTheDocument()
    expect(screen.getByText('Jue 12 (hoy): sin registros')).toHaveAttribute('aria-current', 'date')
    expect(screen.getByText('Vie 13 a dom 15: sin registros')).toBeInTheDocument()
  })

  it('a single empty day names only that day, and weekend rows sit on a tint', () => {
    renderWeek(['2026-11-09', '2026-11-11', '2026-11-12', '2026-11-13', '2026-11-15'])

    expect(screen.getByText('Mar 10: sin registros')).toBeInTheDocument()
    expect(screen.getByText('Sáb 14: sin registros').className).toContain('bg-surface-2')
  })
})
