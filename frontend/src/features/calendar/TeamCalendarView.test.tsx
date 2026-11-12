import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import { ApiError } from '@/lib/api'
import { buildTeamCalendar, buildTeamPerson, buildTeamShift } from '@/test/schedulingFixtures'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import * as calendarApi from './calendarApi'
import { MyCalendarPage } from './MyCalendarPage'
import * as teamCalendarApi from './teamCalendarApi'

const people = [
  buildTeamPerson({ id: 1, full_name: 'Ana García', job_title: 'Diseñadora' }),
  buildTeamPerson({ id: 2, full_name: 'Beto Díaz' }),
  buildTeamPerson({ id: 3, full_name: 'Caro Ruiz' }),
  buildTeamPerson({ id: 4, full_name: 'Dani Sosa', job_title: 'Soporte' }),
]

const shiftsByDate = {
  '2026-11-12': [
    buildTeamShift({ id: 10, user_id: 1, starts_at: '09:00', ends_at: '18:00', mode: 'remote' }),
    buildTeamShift({ id: 11, user_id: 2, starts_at: '14:00', ends_at: '20:00', template_name: 'Tarde', color: '#f6beb6' }),
    buildTeamShift({ id: 12, user_id: 3, starts_at: '06:00', ends_at: '09:00', template_name: 'Temprano' }),
  ],
  '2026-11-10': [buildTeamShift({ id: 13, user_id: 4, mode: 'hybrid' })],
}

function renderTeam({ permissions = ['shifts.manage'], path = '/calendario?vista=equipo', forbidden = false } = {}) {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ permissions }))
  vi.spyOn(calendarApi, 'fetchCalendarFeedStatus').mockResolvedValue(false)
  vi.spyOn(calendarApi, 'fetchMyCalendar').mockResolvedValue({ timezone: 'America/Argentina/Buenos_Aires', shifts: [], tasks_due: [], holidays: [] })
  const fetchTeam = vi.spyOn(teamCalendarApi, 'fetchTeamCalendar').mockImplementation(async (from, to) => {
    if (forbidden) throw new ApiError(403, 'El calendario de equipo es para quien gestiona turnos o tiene gente a cargo.')
    return buildTeamCalendar(from, to, people, shiftsByDate, { '2026-11-13': 'Día del equipo' })
  })
  renderRoutes([{ path: '/calendario', element: <MyCalendarPage /> }], path)
  return fetchTeam
}

describe('Team calendar (RF-06.05)', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    // 12:30 UTC = 09:30 in Buenos Aires.
    vi.setSystemTime(new Date('2026-11-12T12:30:00Z'))
  })
  afterEach(() => vi.useRealTimers())

  it('shows who is working now, later, already finished and off today', async () => {
    renderTeam()

    const now = await screen.findByRole('region', { name: 'Trabajando ahora: 1' })
    expect(within(now).getByText('Ana García')).toBeInTheDocument()
    expect(within(now).getByText('hasta las 18:00')).toBeInTheDocument()
    expect(within(now).getByText('Remoto')).toBeInTheDocument()
    expect(within(screen.getByRole('region', { name: 'Más tarde: 1' })).getByText('entra a las 14:00')).toBeInTheDocument()
    expect(within(screen.getByRole('region', { name: 'Ya terminó: 1' })).getByText('Caro Ruiz')).toBeInTheDocument()
    const off = screen.getByRole('region', { name: 'Sin turno: 1' })
    expect(within(off).getByText('Dani Sosa')).toBeInTheDocument()
    expect(within(off).getByText('Soporte')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: /Hoy, jueves 12\/11/i })).toBeInTheDocument()
  })

  it('lays out the week people × days with each shift, and moves between weeks', async () => {
    const fetchTeam = renderTeam()

    const table = await screen.findByRole('table', { name: 'Turnos del equipo por persona y día' })
    expect(fetchTeam).toHaveBeenCalledWith('2026-11-09', '2026-11-15')
    const daniRow = within(table).getByRole('rowheader', { name: /Dani Sosa/ }).closest('tr') as HTMLElement
    expect(within(daniRow).getByText('Híbrido')).toBeInTheDocument()
    expect(within(table).getByRole('columnheader', { name: /jueves 12\/11/i })).toHaveAttribute('aria-current', 'date')
    expect(within(table).getByRole('columnheader', { name: /viernes 13\/11.*Feriado: Día del equipo/i })).toBeInTheDocument()
    // Phone layout: one list per day.
    expect(screen.getByRole('list', { name: 'Turnos del equipo por día' })).toBeInTheDocument()
    expect(screen.getByRole('listitem', { name: 'Beto Díaz: Tarde 14:00–20:00 (Oficina)' })).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /Semana siguiente/ }))
    await vi.waitFor(() => expect(fetchTeam).toHaveBeenCalledWith('2026-11-16', '2026-11-22'))
    expect(await screen.findByText('Nadie del equipo tiene turnos esta semana.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Armar turnos' })).toHaveAttribute('href', '/turnos')
  })

  it('offers the Equipo view to a leader whose team calendar loads', async () => {
    renderTeam({ permissions: [], path: '/calendario' })

    await userEvent.click(await screen.findByRole('button', { name: 'Equipo' }))

    expect(await screen.findByRole('heading', { name: 'Calendario de equipo' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Armar turnos' })).not.toBeInTheDocument()
  })

  it('hides the Equipo view when the backend answers 403', async () => {
    const fetchTeam = renderTeam({ permissions: [], path: '/calendario', forbidden: true })

    await vi.waitFor(() => expect(fetchTeam).toHaveBeenCalled())
    expect(await screen.findByRole('button', { name: 'Semana' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Equipo' })).not.toBeInTheDocument()
  })
})
