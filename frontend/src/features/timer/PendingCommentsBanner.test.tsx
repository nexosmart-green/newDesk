import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { buildTimeEntry, buildTimerState } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import { PendingCommentsBanner } from './PendingCommentsBanner'
import * as timerApi from './timerApi'

const cutEntry = buildTimeEntry({
  id: 41,
  is_running: false,
  comment_pending: true,
  elapsed_seconds: 5400,
  stop_reason: 'no_hours',
  stop_reason_label: 'El proyecto se quedó sin horas',
})

describe('PendingCommentsBanner', () => {
  it('completes the comment of an automatically cut entry and disappears (RF-05.17)', async () => {
    vi.spyOn(timerApi, 'fetchTimer')
      .mockResolvedValueOnce(buildTimerState({ pending_comments: [cutEntry] }))
      .mockResolvedValue(buildTimerState())
    const complete = vi.spyOn(timerApi, 'completeEntryComment').mockResolvedValue({ ...cutEntry, comment_pending: false })
    renderAt(<PendingCommentsBanner />)

    expect(await screen.findByRole('status')).toHaveTextContent('Tenés 1 registro sin comentario')
    await userEvent.click(screen.getByRole('button', { name: 'Completar' }))
    const dialog = await screen.findByRole('dialog', { name: 'Completá el comentario' })
    expect(dialog).toHaveTextContent('1h 30m')
    expect(dialog).toHaveTextContent('El proyecto se quedó sin horas')
    await userEvent.type(within(dialog).getByLabelText('¿Qué hiciste?'), 'Revisé los reportes de abril')
    await userEvent.click(within(dialog).getByRole('button', { name: 'Guardar comentario' }))

    expect(complete).toHaveBeenCalledWith(41, 'Revisé los reportes de abril')
    await vi.waitFor(() => expect(screen.queryByRole('status')).not.toBeInTheDocument())
  })

  it('counts several pending entries', async () => {
    vi.spyOn(timerApi, 'fetchTimer').mockResolvedValue(
      buildTimerState({ pending_comments: [cutEntry, { ...cutEntry, id: 42 }] }),
    )
    renderAt(<PendingCommentsBanner />)

    expect(await screen.findByRole('status')).toHaveTextContent('Tenés 2 registros sin comentario')
  })
})
