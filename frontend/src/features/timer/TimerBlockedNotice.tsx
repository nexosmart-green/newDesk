import { CircleAlert } from 'lucide-react'
import { SplitDayButton } from '@/features/dayClock/DayClockButton'
import { RequestExtensionButton } from './RequestExtensionButton'
import { timerRule } from './types'

interface TimerBlockedNoticeProps {
  code: string | null
  message: string
  projectId: number | undefined
}

/** RF-05.19: the day clock and the ▶ never run together; said in the app's words, not the server's. */
const dayClockTexts: Partial<Record<string, string>> = {
  [timerRule.dayClockRunning]: 'Frená el reloj del día antes de usar el ▶',
  [timerRule.daySplitPending]: 'Repartí el día pendiente antes de seguir',
}

/** Why the timer didn't start, plus the way out: the hour bag (RF-05.05/08) or the day to split (RF-05.19). */
export function TimerBlockedNotice({ code, message, projectId }: TimerBlockedNoticeProps) {
  return (
    <div className="space-y-3">
      <p role="alert" className="flex items-start gap-2 rounded-lg border border-danger/40 bg-danger/10 px-3 py-2.5 text-sm text-danger">
        <CircleAlert aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
        <span>{(code && dayClockTexts[code]) ?? message}</span>
      </p>
      {code === timerRule.noHoursAvailable && projectId !== undefined && <RequestExtensionButton projectId={projectId} />}
      {code === timerRule.daySplitPending && <SplitDayButton />}
      {code === timerRule.commentPending && (
        <p className="text-sm text-muted">Usá el aviso de arriba para completar el comentario pendiente.</p>
      )}
    </div>
  )
}
