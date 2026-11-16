import { screen, within } from '@testing-library/react'
import * as notificationsApi from '@/features/notifications/notificationsApi'
import * as timerApi from '@/features/timer/timerApi'
import { currentUserKey } from '@/lib/queryKeys'
import { buildTimeEntry, buildTimerState } from '@/test/fixtures'
import { buildUser, createTestQueryClient, renderAt } from '@/test/renderWithProviders'
import { Header } from './Header'

function renderHeader() {
  const client = createTestQueryClient()
  client.setQueryData(currentUserKey, buildUser())
  return renderAt(<Header theme="dark" onToggleTheme={() => {}} onOpenMenu={() => {}} showTimer />, '/tareas', client)
}

describe('Header', () => {
  beforeEach(() => {
    vi.spyOn(notificationsApi, 'fetchNotifications').mockResolvedValue({
      data: [],
      meta: { unread_count: 0, current_page: 1, last_page: 1, total: 0 },
    })
  })

  it('does not repeat the page title: menu, timer, bell, theme and account only', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState())
    renderHeader()

    const header = screen.getByRole('banner')
    expect(within(header).queryByRole('heading')).not.toBeInTheDocument()
    expect(header).not.toHaveTextContent('Tareas')
    expect(within(header).getByRole('button', { name: 'Abrir menú' })).toBeInTheDocument()
    expect(await within(header).findByRole('button', { name: /Notificaciones/ })).toBeInTheDocument()
    expect(within(header).getByRole('button', { name: 'Cuenta de Ana García' })).toBeInTheDocument()
  })

  it('shows the running timer in pink with the task, linked, and a stop that is never "danger"', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(buildTimerState({ active: buildTimeEntry({ elapsed_seconds: 65 }) }))
    renderHeader()

    const timer = await screen.findByRole('group', { name: 'Cronómetro en curso' })
    expect(timer).toHaveClass('bg-live')
    expect(within(timer).getByRole('timer')).toHaveTextContent('00:01:05')
    expect(within(timer).getByRole('link', { name: 'Arreglar el login' })).toHaveAttribute('href', '/tareas/bhn-60')
    const stop = within(timer).getByRole('button', { name: 'Detener cronómetro de bhn-60' })
    expect(stop.className).not.toMatch(/danger/)
  })
})
