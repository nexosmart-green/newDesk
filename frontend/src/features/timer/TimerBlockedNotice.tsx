import { RequestExtensionButton } from './RequestExtensionButton'
import { timerRule } from './types'

interface TimerBlockedNoticeProps {
  code: string | null
  message: string
  projectId: number | undefined
}

/** Why the timer didn't start, plus the way out when it's the hour bag (RF-05.05/08). */
export function TimerBlockedNotice({ code, message, projectId }: TimerBlockedNoticeProps) {
  return (
    <div className="space-y-3">
      <p role="alert" className="rounded border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        {message}
      </p>
      {code === timerRule.noHoursAvailable && projectId !== undefined && <RequestExtensionButton projectId={projectId} />}
      {code === timerRule.commentPending && (
        <p className="text-sm text-muted">Usá el aviso de arriba para completar el comentario pendiente.</p>
      )}
    </div>
  )
}
