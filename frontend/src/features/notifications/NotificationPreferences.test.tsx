import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderAt } from '@/test/renderWithProviders'
import { NotificationPreferences } from './NotificationPreferences'
import * as notificationsApi from './notificationsApi'
import type { NotificationPreference } from './types'

const preferences: NotificationPreference[] = [
  { event: 'task_assigned', label: 'Te asignaron una tarea', email: true, locked: false },
  { event: 'hours_threshold', label: 'Proyecto al 80% / 100% de horas', email: true, locked: true },
]

describe('NotificationPreferences', () => {
  it('disables locked events and saves the full list on toggle', async () => {
    vi.spyOn(notificationsApi, 'fetchNotificationPreferences').mockResolvedValue(preferences)
    const update = vi.spyOn(notificationsApi, 'updateNotificationPreferences').mockResolvedValue(preferences)
    renderAt(<NotificationPreferences />)

    const locked = await screen.findByLabelText('Recibir por e-mail: Proyecto al 80% / 100% de horas')
    expect(locked).toBeDisabled()
    expect(screen.getByText(/Obligatoria para administradores/)).toBeInTheDocument()

    await userEvent.click(screen.getByLabelText('Recibir por e-mail: Te asignaron una tarea'))

    expect(update).toHaveBeenCalledWith([
      { event: 'task_assigned', email: false },
      { event: 'hours_threshold', email: true },
    ])
    expect(await screen.findByText('Preferencias guardadas.')).toBeInTheDocument()
  })
})
