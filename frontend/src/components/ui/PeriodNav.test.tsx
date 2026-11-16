import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { PeriodNav } from './PeriodNav'

function renderNav(isToday = false) {
  const handlers = { onPrevious: vi.fn(), onToday: vi.fn(), onNext: vi.fn() }
  render(
    <PeriodNav
      label="Elegir semana"
      current="9 nov – 15 nov 2026"
      previousLabel="Semana anterior"
      todayLabel="Esta semana"
      nextLabel="Semana siguiente"
      isToday={isToday}
      {...handlers}
    />,
  )
  return handlers
}

describe('PeriodNav', () => {
  it('names the period first, then chevrons with names and "Hoy"', async () => {
    const handlers = renderNav()
    const nav = screen.getByRole('navigation', { name: 'Elegir semana' })

    expect(nav.firstElementChild).toHaveTextContent('9 nov – 15 nov 2026')
    expect(nav).not.toHaveTextContent(/[←→]/)
    await userEvent.click(screen.getByRole('button', { name: 'Semana anterior' }))
    await userEvent.click(screen.getByRole('button', { name: 'Hoy' }))
    await userEvent.click(screen.getByRole('button', { name: 'Semana siguiente' }))

    expect(handlers.onPrevious).toHaveBeenCalledOnce()
    expect(handlers.onToday).toHaveBeenCalledOnce()
    expect(handlers.onNext).toHaveBeenCalledOnce()
  })

  it('"Hoy" is disabled when already on the current period', () => {
    renderNav(true)

    expect(screen.getByRole('button', { name: 'Hoy' })).toBeDisabled()
  })
})
