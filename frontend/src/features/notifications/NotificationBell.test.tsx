import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { LocationProbe } from '@/test/LocationProbe'
import { renderRoutes } from '@/test/renderWithProviders'
import { NotificationBell } from './NotificationBell'
import * as notificationsApi from './notificationsApi'
import type { AppNotification } from './types'

const assigned: AppNotification = {
  id: 'n-1',
  event: 'task_assigned',
  title: 'Te asignaron bhn-60',
  body: 'Arreglar el login',
  path: '/tareas/bhn-60',
  read_at: null,
  created_at: '2026-11-06T13:00:00+00:00',
}

function renderBell() {
  vi.spyOn(notificationsApi, 'fetchNotifications').mockResolvedValue({
    data: [assigned],
    meta: { unread_count: 1, current_page: 1, last_page: 1, total: 1 },
  })
  renderRoutes(
    [
      { path: '/', element: <NotificationBell /> },
      { path: '/tareas/:code', element: <LocationProbe /> },
    ],
    '/',
  )
}

describe('NotificationBell', () => {
  it('marks a notification as read and opens its path', async () => {
    const markRead = vi.spyOn(notificationsApi, 'markNotificationRead').mockResolvedValue({ unread_count: 0 })
    renderBell()

    await userEvent.click(await screen.findByRole('button', { name: 'Notificaciones (1 sin leer)' }))
    await userEvent.click(screen.getByRole('button', { name: /Te asignaron bhn-60/ }))

    expect(markRead).toHaveBeenCalledWith('n-1')
    expect(await screen.findByTestId('location')).toHaveTextContent('/tareas/bhn-60')
  })

  it('marks all as read', async () => {
    const markAll = vi.spyOn(notificationsApi, 'markAllNotificationsRead').mockResolvedValue({ unread_count: 0 })
    renderBell()

    await userEvent.click(await screen.findByRole('button', { name: 'Notificaciones (1 sin leer)' }))
    await userEvent.click(screen.getByRole('button', { name: 'Marcar todas como leídas' }))

    expect(markAll).toHaveBeenCalled()
  })

  it('closes with Escape and returns focus to the bell', async () => {
    renderBell()
    const bell = await screen.findByRole('button', { name: 'Notificaciones (1 sin leer)' })
    await userEvent.click(bell)
    expect(bell).toHaveAttribute('aria-expanded', 'true')

    await userEvent.keyboard('{Escape}')

    expect(bell).toHaveAttribute('aria-expanded', 'false')
    expect(bell).toHaveFocus()
  })
})
