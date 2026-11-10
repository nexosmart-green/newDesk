import { CircleAlert } from 'lucide-react'
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
      <p role="alert" className="flex items-start gap-2 rounded-lg border border-danger/40 bg-danger/10 px-3 py-2.5 text-sm text-danger">
        <CircleAlert aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
        <span>{message}</span>
      </p>
      {code === timerRule.noHoursAvailable && projectId !== undefined && <RequestExtensionButton projectId={projectId} />}
      {code === timerRule.commentPending && (
        <p className="text-sm text-muted">Usá el aviso de arriba para completar el comentario pendiente.</p>
      )}
    </div>
  )
}
