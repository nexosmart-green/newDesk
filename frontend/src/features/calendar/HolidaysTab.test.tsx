import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderAt } from '@/test/renderWithProviders'
import * as calendarApi from './calendarApi'
import { HolidaysTab } from './HolidaysTab'

describe('HolidaysTab', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 10, 6, 12))
  })
  afterEach(() => vi.useRealTimers())

  it('lists the holidays of the year from the holidays endpoint and moves by year (RF-06.06)', async () => {
    const fetch = vi.spyOn(calendarApi, 'fetchHolidays').mockImplementation(async (year) =>
      year === 2026 ? [{ id: 8, date: '2026-11-05', name: 'Día del equipo', scope: 'company' }] : [],
    )
    const fetchCalendar = vi.spyOn(calendarApi, 'fetchMyCalendar')
    renderAt(<HolidaysTab />)

    const list = await screen.findByRole('list', { name: 'Feriados 2026' })
    expect(fetch).toHaveBeenCalledWith(2026)
    expect(within(list).getByText('Día del equipo')).toBeInTheDocument()
    expect(within(list).getByText('De la empresa')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Año siguiente/ }))

    expect(await screen.findByText('No hay feriados cargados en 2027.')).toBeInTheDocument()
    expect(fetch).toHaveBeenCalledWith(2027)
    expect(fetchCalendar).not.toHaveBeenCalled()
  })

  it('imports the national holidays of the year shown, only for last, this and next year (P-26)', async () => {
    let imported = false
    vi.spyOn(calendarApi, 'fetchHolidays').mockImplementation(async (year) =>
      imported && year === 2027 ? [{ id: 9, date: '2027-01-01', name: 'Año nuevo', scope: 'national' }] : [],
    )
    const importHolidays = vi.spyOn(calendarApi, 'importHolidays').mockImplementation(async () => {
      imported = true
      return 'Se agregó 1 feriado nacional de 2027.'
    })
    renderAt(<HolidaysTab />)

    await userEvent.click(await screen.findByRole('button', { name: /Año siguiente/ }))
    await userEvent.click(await screen.findByRole('button', { name: 'Importar feriados nacionales de 2027' }))

    expect(importHolidays).toHaveBeenCalledWith(2027)
    expect(await screen.findByText('Se agregó 1 feriado nacional de 2027.')).toBeInTheDocument()
    expect(within(await screen.findByRole('list', { name: 'Feriados 2027' })).getByText('Año nuevo')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Año siguiente/ }))
    expect(await screen.findByText('No hay feriados cargados en 2028.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Importar feriados/ })).not.toBeInTheDocument()
  })
})
