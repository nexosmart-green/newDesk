import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderRoutes } from '@/test/renderWithProviders'
import * as calendarApi from './calendarApi'
import { MyCalendarPage } from './MyCalendarPage'
import type { MyCalendar } from './types'

const calendar: MyCalendar = {
  timezone: 'America/Argentina/Buenos_Aires',
  shifts: [
    {
      id: 70,
      date: '2026-11-02',
      name: 'Completo',
      color: '#4f8cff',
      starts_at: '09:00',
      ends_at: '18:00',
      mode: 'remote',
      mode_label: 'Remoto',
      start_utc: '2026-11-02T12:00:00+00:00',
      end_utc: '2026-11-02T21:00:00+00:00',
    },
  ],
  tasks_due: [{ code: 'bhn-60', title: 'Arreglar el login', due_on: '2026-11-04' }],
  holidays: [{ id: 8, date: '2026-11-05', name: 'Día del equipo', scope: 'company' }],
}

function renderCalendar(feedActive = false) {
  vi.spyOn(calendarApi, 'fetchCalendarFeedStatus').mockResolvedValue(feedActive)
  const fetch = vi.spyOn(calendarApi, 'fetchMyCalendar').mockResolvedValue(calendar)
  renderRoutes([{ path: '/calendario', element: <MyCalendarPage /> }], '/calendario')
  return fetch
}

describe('MyCalendarPage', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 10, 6, 12))
  })
  afterEach(() => vi.useRealTimers())

  it('lists my shifts, my tasks due and holidays for the week (RF-06.04)', async () => {
    const fetch = renderCalendar()

    const monday = await screen.findByRole('region', { name: /lunes 2\/11/i })
    expect(fetch).toHaveBeenCalledWith('2026-11-02', '2026-11-08')
    expect(within(monday).getByText('Completo')).toBeInTheDocument()
    expect(within(monday).getByText(/09:00–18:00/)).toBeInTheDocument()
    expect(within(monday).getByText(/Remoto/)).toBeInTheDocument()
    const wednesday = screen.getByRole('region', { name: /miércoles 4\/11/i })
    expect(within(wednesday).getByRole('link', { name: 'bhn-60' })).toHaveAttribute('href', '/tareas/bhn-60')
    expect(within(screen.getByRole('region', { name: /jueves 5\/11/i })).getByText('Feriado: Día del equipo')).toBeInTheDocument()
    expect(within(screen.getByRole('region', { name: /martes 3\/11/i })).getByText('Nada agendado')).toBeInTheDocument()
  })

  it('switches to the month view fetching whole weeks', async () => {
    const fetch = renderCalendar()
    await screen.findByRole('region', { name: /lunes 2\/11/i })

    await userEvent.click(screen.getByRole('button', { name: 'Mes' }))

    await vi.waitFor(() => expect(fetch).toHaveBeenCalledWith('2026-10-26', '2026-12-06'))
    expect(screen.getByText('noviembre de 2026')).toBeInTheDocument()
  })

  it('shows the iCal URL once and lets me revoke it (RF-06.07)', async () => {
    renderCalendar()
    const status = vi.mocked(calendarApi.fetchCalendarFeedStatus)
    vi.spyOn(calendarApi, 'issueCalendarFeed').mockImplementation(async () => {
      status.mockResolvedValue(true)
      return 'https://desk.test/calendar/abc123.ics'
    })
    const revoke = vi.spyOn(calendarApi, 'revokeCalendarFeed').mockImplementation(async () => {
      status.mockResolvedValue(false)
    })

    await userEvent.click(await screen.findByRole('button', { name: 'Suscribirme (iCal)' }))
    expect(await screen.findByText('https://desk.test/calendar/abc123.ics')).toBeInTheDocument()
    expect(screen.getByText(/Si generás otra, esta deja de funcionar/)).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Ocultar' }))
    expect(screen.queryByText('https://desk.test/calendar/abc123.ics')).not.toBeInTheDocument()

    await userEvent.click(await screen.findByRole('button', { name: 'Revocar suscripción' }))
    expect(revoke).not.toHaveBeenCalled()
    await userEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Revocar' }))
    expect(revoke).toHaveBeenCalled()
    expect(await screen.findByText(/Suscripción revocada/)).toBeInTheDocument()
    await vi.waitFor(() => expect(screen.queryByRole('button', { name: 'Revocar suscripción' })).not.toBeInTheDocument())
  })

  it('only offers revoking when I have an active feed (RF-06.07)', async () => {
    renderCalendar(false)

    expect(await screen.findByRole('button', { name: 'Suscribirme (iCal)' })).toBeInTheDocument()
    const status = vi.mocked(calendarApi.fetchCalendarFeedStatus)
    await vi.waitFor(() => expect(status).toHaveBeenCalled())
    await status.mock.results[0]?.value
    await new Promise((resolve) => setTimeout(resolve, 0))
    expect(screen.queryByRole('button', { name: 'Revocar suscripción' })).not.toBeInTheDocument()
  })

  it('offers revoking an active feed without generating a new URL', async () => {
    renderCalendar(true)

    expect(await screen.findByRole('button', { name: 'Revocar suscripción' })).toBeInTheDocument()
  })
})
