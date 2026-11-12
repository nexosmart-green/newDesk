import { useQuery } from '@tanstack/react-query'
import { Play, Square } from 'lucide-react'
import { useId, useState } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { MODULES, useHasModule } from '@/features/auth/modules'
import { ApiError } from '@/lib/api'
import { errorTextOf } from '@/lib/errorText'
import { timerKey } from '@/lib/queryKeys'
import { CommentDialog } from './CommentDialog'
import { PendingCommentDialog } from './PendingCommentDialog'
import { toPendingSummary } from './pendingEntry'
import { RequestExtensionButton } from './RequestExtensionButton'
import { StopTimerDialog } from './StopTimerDialog'
import { fetchTimer } from './timerApi'
import { isProvisional } from './timerOptimistic'
import { TimerBlockedNotice } from './TimerBlockedNotice'
import { timerRule, type TimerState } from './types'
import { useStartTimer, useTimerState } from './useTimer'

interface StartTimerButtonProps {
  task: { code: string; title: string; project?: { id: number; name?: string; hours_exhausted: boolean } }
  /** Compact icon-only rendering for cards and rows. */
  compact?: boolean
}

const sameCode = (left: string, right: string) => left.toLowerCase() === right.toLowerCase()

/** Only what ▶/■ needs: the poll every 30 s changes the elapsed time, which must not re-render every row. */
const runningOf = ({ active }: TimerState) =>
  active ? { id: active.id, code: active.task.code, provisional: isProvisional(active) } : null

/**
 * Reads the timer the header polls (no poll of its own: one per row would multiply the requests), through
 * `select`: structural sharing keeps the result identical between polls, so the row doesn't re-render.
 */
function useRunningTimer() {
  const enabled = useHasModule(MODULES.time)
  return useQuery({ enabled, queryKey: timerKey, queryFn: fetchTimer, select: runningOf }).data ?? null
}

/** The full entry (with its ticking time) only while the stop dialog is open. */
function StopRunningDialog({ onClose }: { onClose: () => void }) {
  const active = useTimerState().data?.active
  return active ? <StopTimerDialog entry={active} onClose={onClose} /> : null
}

/** A start blocked by an automatically cut entry: complete its comment right here (RF-05.17). */
function CompletePendingDialog({ onClose }: { onClose: () => void }) {
  const pending = useTimerState().data?.pending_comments ?? []
  const current = pending[0]
  if (!current) return null
  return (
    <PendingCommentDialog
      entry={toPendingSummary(current)}
      position={pending.length > 1 ? `Registro 1 de ${pending.length}` : undefined}
      onDone={() => pending.length <= 1 && onClose()}
      onClose={onClose}
    />
  )
}
const asksForComment = (error: ApiError) =>
  error.code === timerRule.previousCommentRequired || error.code === timerRule.commentRequired || error.hasFieldErrors()

/**
 * RF-05.01 ▶/■ on a task assigned to me. Switching from another running timer asks what was done
 * there first (RF-05.16); a block (no hours, closed task…) explains why and how to get out (RF-05.05/08).
 * A project already out of hours says so before the click, with the extension request at hand.
 */
export function StartTimerButton({ task, compact = false }: StartTimerButtonProps) {
  const active = useRunningTimer()
  const start = useStartTimer()
  const noHoursId = useId()
  const [askingPrevious, setAskingPrevious] = useState(false)
  const [blocked, setBlocked] = useState<ApiError | null>(null)
  const [stoppingId, setStoppingId] = useState<number | null>(null)
  const [completingPending, setCompletingPending] = useState(false)
  const runningHere = active !== null && sameCode(active.code, task.code)
  const projectId = task.project?.id

  function begin(previousComment?: string) {
    start.mutate(
      { task, previousComment },
      {
        onSuccess: () => setAskingPrevious(false),
        onError: (error) => {
          const apiError = error instanceof ApiError ? error : new ApiError(0, error.message)
          if (apiError.code === timerRule.previousCommentRequired && !previousComment) return setAskingPrevious(true)
          if (previousComment && asksForComment(apiError)) return // shown inside the comment dialog
          setAskingPrevious(false)
          setBlocked(apiError)
        },
      },
    )
  }

  function handleClick() {
    if (runningHere && active) return setStoppingId(active.id)
    start.reset()
    if (active) return setAskingPrevious(true)
    begin()
  }

  const label = runningHere ? `Detener cronómetro de ${task.code}` : `Iniciar cronómetro en ${task.code}`

  if (!runningHere && task.project?.hours_exhausted) {
    return (
      <div className="flex flex-wrap items-start justify-end gap-2">
        <Button variant="secondary" aria-label={label} aria-describedby={noHoursId} disabled className={compact ? 'min-w-11 px-0' : undefined}>
          <Play aria-hidden="true" className="size-4 fill-current" />
          {!compact && <span>Iniciar</span>}
        </Button>
        <p id={noHoursId} className="self-center text-xs text-danger">
          Sin horas disponibles — pedir ampliación
        </p>
        <RequestExtensionButton projectId={task.project.id} />
      </div>
    )
  }

  return (
    <>
      <Button
        variant={runningHere ? 'danger' : 'secondary'}
        aria-label={label}
        title={label}
        loading={start.isPending}
        // A just-started (optimistic) entry can't be stopped until the server confirms it.
        disabled={runningHere && active.provisional}
        onClick={handleClick}
        className={compact ? 'min-w-11 px-0' : undefined}
      >
        {runningHere ? <Square aria-hidden="true" className="size-3.5 fill-current" /> : <Play aria-hidden="true" className="size-4 fill-current" />}
        {!compact && <span>{runningHere ? 'Detener' : 'Iniciar'}</span>}
      </Button>
      {askingPrevious && (
        <CommentDialog
          title="Antes de cambiar de tarea"
          submitLabel="Detener y empezar"
          pending={start.isPending}
          error={start.error}
          onSubmit={(comment) => begin(comment)}
          onClose={() => setAskingPrevious(false)}
        >
          <p>
            Tenés el cronómetro corriendo en{' '}
            {active ? <span className="font-mono uppercase">{active.code}</span> : 'otra tarea'}. Contá qué
            hiciste ahí para detenerlo y arrancar <span className="font-mono uppercase">{task.code}</span>.
          </p>
        </CommentDialog>
      )}
      {blocked && (
        <Dialog title="No se pudo iniciar el cronómetro" size="sm" onClose={() => setBlocked(null)}>
          <TimerBlockedNotice code={blocked.code} message={errorTextOf(blocked).message} projectId={projectId} />
          <DialogFooter>
            <Button variant="secondary" onClick={() => setBlocked(null)}>
              Cerrar
            </Button>
            {blocked.code === timerRule.commentPending && (
              <Button
                onClick={() => {
                  setBlocked(null)
                  setCompletingPending(true)
                }}
              >
                Completar comentario
              </Button>
            )}
          </DialogFooter>
        </Dialog>
      )}
      {completingPending && <CompletePendingDialog onClose={() => setCompletingPending(false)} />}
      {active && stoppingId === active.id && <StopRunningDialog onClose={() => setStoppingId(null)} />}
    </>
  )
}
