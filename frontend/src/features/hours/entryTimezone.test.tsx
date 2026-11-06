import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { currentUserKey } from '@/features/auth/useAuth'
import { ManualTimeForm } from '@/features/tasks/ManualTimeForm'
import * as timerApi from '@/features/timer/timerApi'
import { buildTimeEntry } from '@/test/fixtures'
import { buildUser, createTestQueryClient, renderRoutes } from '@/test/renderWithProviders'
import type { ReactElement } from 'react'
import { EditEntryDialog } from './EditEntryDialog'

const closedEntry = { ...buildTimeEntry({ is_running: false }), started_at: '2026-11-10T12:00:00Z', ended_at: '2026-11-10T13:30:00Z' }

/** RequireAuth guarantees /me is loaded before any form mounts; mirror that. */
function renderWithProfileTimeZone(element: ReactElement, timezone: string) {
  const client = createTestQueryClient()
  client.setQueryData(currentUserKey, buildUser({ timezone }))
  return renderRoutes([{ path: '/', element }], '/', client)
}

describe('entry times use the profile timezone, not the browser one', () => {
  it('prefills the correction form converting UTC to the profile timezone', () => {
    renderWithProfileTimeZone(<EditEntryDialog entry={closedEntry} onClose={() => {}} />, 'America/Argentina/Buenos_Aires')

    expect(screen.getByLabelText('Fecha')).toHaveValue('2026-11-10')
    expect(screen.getByLabelText('Desde')).toHaveValue('09:00')
    expect(screen.getByLabelText('Hasta')).toHaveValue('10:30')
  })

  it('uses whatever timezone the profile says (Tokyo crosses midnight)', () => {
    renderWithProfileTimeZone(<EditEntryDialog entry={closedEntry} onClose={() => {}} />, 'Asia/Tokyo')

    expect(screen.getByLabelText('Fecha')).toHaveValue('2026-11-10')
    expect(screen.getByLabelText('Desde')).toHaveValue('21:00')
    expect(screen.getByLabelText('Hasta')).toHaveValue('22:30')
  })

  it('submits the edited wall-clock times as typed (the backend reads them in the profile timezone)', async () => {
    const correct = vi.spyOn(timerApi, 'correctTimeEntry').mockResolvedValue(closedEntry)
    renderWithProfileTimeZone(
      <EditEntryDialog entry={{ ...closedEntry, comment: 'Revisé el formulario de login' }} onClose={() => {}} />,
      'America/Argentina/Buenos_Aires',
    )

    await userEvent.clear(screen.getByLabelText('Desde'))
    await userEvent.type(screen.getByLabelText('Desde'), '08:00')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(correct).toHaveBeenCalledWith(300, { date: '2026-11-10', from: '08:00', to: '10:30', comment: 'Revisé el formulario de login' })
  })

  describe('manual time', () => {
    beforeEach(() => {
      vi.useFakeTimers({ toFake: ['Date'] })
      // 01:00 UTC on the 7th is still the 6th in Buenos Aires.
      vi.setSystemTime(new Date('2026-11-07T01:00:00Z'))
    })
    afterEach(() => vi.useRealTimers())

    it('defaults the date to today in the profile timezone', () => {
      renderWithProfileTimeZone(<ManualTimeForm taskCode="bhn-60" projectId={9} />, 'America/Argentina/Buenos_Aires')

      expect(screen.getByLabelText('Fecha')).toHaveValue('2026-11-06')
      expect(screen.getByText(/America\/Argentina\/Buenos_Aires/)).toBeInTheDocument()
    })
  })
})
