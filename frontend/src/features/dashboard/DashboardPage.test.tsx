import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { currentUserKey } from '@/lib/queryKeys'
import { ApiError } from '@/lib/api'
import * as timerApi from '@/features/timer/timerApi'
import * as hoursApi from '@/features/hours/hoursApi'
import { buildTask, buildTimeEntry, buildTimerState } from '@/test/fixtures'
import { buildUser, createTestQueryClient, renderAt } from '@/test/renderWithProviders'
import * as dashboardApi from './dashboardApi'
import { DashboardPage } from './DashboardPage'
import { dueText } from './dueText'
import type { Dashboard } from './types'

const days = ['09', '10', '11', '12', '13', '14', '15'].map((day) => ({ date: `2026-11-${day}`, seconds: 0 }))

function buildDashboard(overrides: Partial<Dashboard> = {}): Dashboard {
  return {
    tasks: {
      open: 54,
      overdue: 38,
      due_today: 2,
      due_soon: [buildTask({ id: 1, code: 'bhn-1', title: 'Cerrar sprint', due_on: '2026-11-10' })],
    },
    projects: { active: 19 },
    week: {
      from: '2026-11-09',
      to: '2026-11-15',
      total_seconds: 34200,
      days: days.map((day) => (day.date === '2026-11-09' ? { ...day, seconds: 28800 } : day.date === '2026-11-10' ? { ...day, seconds: 5400 } : day)),
    },
    bags_running_out: [{ id: 4, name: 'Bahía Web', code_prefix: 'bhn', allocated_seconds: 36000, consumed_seconds: 32400 }],
    ...overrides,
  }
}

const emptyHistory = { data: [], meta: { current_page: 1, last_page: 1, per_page: 50, total: 0, total_seconds: 0, editable_since: null } }

function renderPanel() {
  const client = createTestQueryClient()
  client.setQueryData(currentUserKey, buildUser())
  return renderAt(<DashboardPage />, '/', client)
}

describe('DashboardPage', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2026-11-10T15:00:00Z'))
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
    vi.spyOn(hoursApi, 'fetchEntries').mockResolvedValue(emptyHistory)
  })
  afterEach(() => vi.useRealTimers())

  it('shows counters that open their list, the week, deadlines and bags', async () => {
    vi.spyOn(dashboardApi, 'fetchDashboard').mockResolvedValue(buildDashboard())
    renderPanel()

    const counters = await screen.findByRole('navigation', { name: 'Resumen de tareas' })
    expect(counters).toHaveTextContent('Tenés 54 tareas pendientes, 38 vencidas y 2 que vencen hoy. Hay 19 proyectos activos.')
    expect(within(counters).getByRole('link', { name: '38 vencidas' })).toHaveAttribute('href', '/mis-tareas?vencidas=1')
    expect(within(counters).getByRole('link', { name: '54 tareas pendientes' })).toHaveAttribute('href', '/mis-tareas')
    expect(within(counters).getByRole('link', { name: '19 proyectos activos' })).toHaveAttribute('href', '/proyectos')

    const week = screen.getByRole('region', { name: 'Mi semana' })
    expect(within(week).getByText('9h 30m')).toBeInTheDocument()
    expect(within(week).getByRole('listitem', { current: 'date' })).toHaveTextContent('1h 30m')

    const dueSoon = screen.getByRole('region', { name: 'Próximas a vencer' })
    expect(within(dueSoon).getByRole('link', { name: /Cerrar sprint/ })).toHaveAttribute('href', '/tareas/bhn-1')
    expect(within(dueSoon).getByText('Vence hoy')).toBeInTheDocument()

    const bags = screen.getByRole('region', { name: 'Bolsas por agotarse' })
    expect(within(bags).getByText('Quedan 1h 00m')).toBeInTheDocument()
  })

  it('leaves out the blocks the role cannot see (null)', async () => {
    vi.spyOn(dashboardApi, 'fetchDashboard').mockResolvedValue(buildDashboard({ week: null, bags_running_out: null }))
    renderPanel()

    expect(await screen.findByRole('region', { name: 'Próximas a vencer' })).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Mi semana' })).not.toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'Bolsas por agotarse' })).not.toBeInTheDocument()
  })

  it('offers "Reintentar" when it fails', async () => {
    const fetch = vi.spyOn(dashboardApi, 'fetchDashboard').mockRejectedValueOnce(new ApiError(500, 'Boom'))
    fetch.mockResolvedValue(buildDashboard())
    renderPanel()

    await userEvent.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('navigation', { name: 'Resumen de tareas' })).toBeInTheDocument()
  })

  it('words the counters as a sentence: one pending, none overdue, nothing due today', async () => {
    vi.spyOn(dashboardApi, 'fetchDashboard').mockResolvedValue(
      buildDashboard({ tasks: { open: 1, overdue: 0, due_today: 0, due_soon: [] }, projects: null }),
    )
    renderPanel()

    expect(await screen.findByRole('navigation', { name: 'Resumen de tareas' })).toHaveTextContent('Tenés 1 tarea pendiente y ninguna vencida.')
    expect(within(screen.getByRole('region', { name: 'Próximas a vencer' })).getByText('No tenés tareas abiertas con vencimiento.')).toBeInTheDocument()
  })

  it('"Ahora": the running timer, big, with Detener on pink', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState({ active: buildTimeEntry({ elapsed_seconds: 125 }) }))
    vi.spyOn(dashboardApi, 'fetchDashboard').mockResolvedValue(buildDashboard())
    renderPanel()

    const now = await screen.findByRole('region', { name: 'Ahora' })
    expect(within(now).getByRole('timer')).toHaveTextContent('00:02:05')
    expect(within(now).getByRole('link', { name: 'Arreglar el login' })).toHaveAttribute('href', '/tareas/bhn-60')
    expect(within(now).getByRole('button', { name: 'Detener' })).toHaveClass('bg-live')
  })

  it('"Ahora" without a timer: pick up the last task worked on with "Seguir"', async () => {
    vi.spyOn(hoursApi, 'fetchEntries').mockResolvedValue({
      ...emptyHistory,
      data: [
        buildTimeEntry({
          id: 5,
          is_running: false,
          task: { id: 12, code: 'bhn-12', title: 'Maquetar home' },
          started_at: '2026-11-09T12:00:00Z',
          ended_at: '2026-11-09T13:00:00Z',
          elapsed_seconds: 3600,
          comment: 'Header listo',
        }),
      ],
    })
    vi.spyOn(dashboardApi, 'fetchDashboard').mockResolvedValue(buildDashboard())
    renderPanel()

    const now = await screen.findByRole('region', { name: 'Ahora' })
    expect(await within(now).findByText(/Lo último que trabajaste fue/)).toHaveTextContent('Lo último que trabajaste fue Maquetar home, de Bahía Web.')
    expect(within(now).getByRole('button', { name: 'Iniciar cronómetro en bhn-12' })).toHaveTextContent('Seguir')
  })

  it('"Ahora" with nothing worked on lately: a quiet nudge to pick a task', async () => {
    vi.spyOn(dashboardApi, 'fetchDashboard').mockResolvedValue(buildDashboard())
    renderPanel()

    const now = await screen.findByRole('region', { name: 'Ahora' })
    expect(await within(now).findByRole('link', { name: 'Mis tareas' })).toHaveAttribute('href', '/mis-tareas')
  })

  it('words deadlines relative to today', () => {
    expect(dueText('2026-11-10', '2026-11-10')).toBe('Vence hoy')
    expect(dueText('2026-11-11', '2026-11-10')).toBe('Vence mañana')
    expect(dueText('2026-11-14', '2026-11-10')).toBe('Vence en 4 días')
    expect(dueText('2026-12-01', '2026-11-10')).toBe('Vence 01/12/2026')
  })
})
