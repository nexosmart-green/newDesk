import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog } from '@/components/ui/Dialog'
import { ApiError } from '@/lib/api'
import { CommentDialog } from './CommentDialog'
import { StopTimerDialog } from './StopTimerDialog'
import { TimerBlockedNotice } from './TimerBlockedNotice'
import { timerRule } from './types'
import { useStartTimer, useTimerState } from './useTimer'

interface StartTimerButtonProps {
  task: { code: string; title: string }
  projectId: number | undefined
  /** Compact icon-only rendering for cards and rows. */
  compact?: boolean
}

const sameCode = (left: string, right: string) => left.toLowerCase() === right.toLowerCase()
const asksForComment = (error: ApiError) =>
  error.code === timerRule.previousCommentRequired || error.code === timerRule.commentRequired || error.hasFieldErrors()

/**
 * RF-05.01 ▶/■ on a task assigned to me. Switching from another running timer asks what was done
 * there first (RF-05.16); a block (no hours, closed task…) explains why and how to get out (RF-05.05/08).
 */
export function StartTimerButton({ task, projectId, compact = false }: StartTimerButtonProps) {
  const timer = useTimerState()
  const start = useStartTimer()
  const [askingPrevious, setAskingPrevious] = useState(false)
  const [blocked, setBlocked] = useState<ApiError | null>(null)
  const [stoppingId, setStoppingId] = useState<number | null>(null)
  const active = timer.data?.active ?? null
  const runningHere = active !== null && sameCode(active.task.code, task.code)

  function begin(previousComment?: string) {
    start.mutate(
      { taskCode: task.code, previousComment },
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

  return (
    <>
      <Button
        variant={runningHere ? 'danger' : 'secondary'}
        aria-label={label}
        title={label}
        disabled={start.isPending}
        onClick={handleClick}
        className={compact ? 'px-2 py-1' : undefined}
      >
        <span aria-hidden="true">{runningHere ? '■' : '▶'}</span>
        {!compact && <span className="ml-1">{runningHere ? 'Detener' : 'Iniciar'}</span>}
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
            {active ? <span className="font-mono uppercase">{active.task.code}</span> : 'otra tarea'}. Contá qué
            hiciste ahí para detenerlo y arrancar <span className="font-mono uppercase">{task.code}</span>.
          </p>
        </CommentDialog>
      )}
      {blocked && (
        <Dialog title="No se pudo iniciar el cronómetro" onClose={() => setBlocked(null)}>
          <TimerBlockedNotice code={blocked.code} message={blocked.message} projectId={projectId} />
          <div className="flex justify-end">
            <Button variant="secondary" onClick={() => setBlocked(null)}>
              Cerrar
            </Button>
          </div>
        </Dialog>
      )}
      {active && stoppingId === active.id && (
        <StopTimerDialog entry={active} elapsedSeconds={null} onClose={() => setStoppingId(null)} />
      )}
    </>
  )
}
