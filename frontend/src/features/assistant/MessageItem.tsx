import { useEffect, useState } from 'react'
import { Button } from '@/components/ui/Button'
import { MarkdownView } from '@/components/ui/Markdown'
import { FileLinks } from './FileLinks'
import { ProposalCard } from './ProposalCard'
import { isInProgress, type AssistantMessage } from './types'

interface MessageItemProps {
  message: AssistantMessage
  /** A failed answer offers to ask the same thing again. */
  onRetry?: () => void
  retrying?: boolean
}

/** One line of the thread: my question in a box on the right; the answer as plain text of the log. */
export function MessageItem({ message, onRetry, retrying = false }: MessageItemProps) {
  const slow = useSlowAnswer(message)

  if (message.role === 'user') {
    return (
      <div className="flex justify-end">
        <p className="max-w-[85%] whitespace-pre-wrap rounded-[10px] border border-line bg-surface px-3.5 py-2.5 text-[15px] leading-relaxed">
          {message.content}
        </p>
      </div>
    )
  }

  if (message.status === 'pending' || message.status === 'running') {
    return (
      <p role="status" className="flex items-center gap-2 text-sm text-muted">
        <span aria-hidden="true" className="size-2 animate-pulse rounded-full bg-brand motion-reduce:animate-none" />
        {slow ? 'Pensando… tarda más de lo normal, puede haber otros pedidos antes que el tuyo.' : 'Pensando…'}
      </p>
    )
  }

  // A failed answer may still have left proposals or files before it broke: they are shown too.
  return (
    <div className="space-y-4">
      {message.status === 'failed' ? (
        <div className="space-y-1.5">
          <p className="text-sm text-danger">{message.error ?? 'No pude responder.'}</p>
          {onRetry && (
            <Button variant="link" loading={retrying} onClick={onRetry}>
              Volver a preguntar
            </Button>
          )}
        </div>
      ) : (
        message.content && <MarkdownView source={message.content} breaks />
      )}
      {message.proposals.map((proposal) => (
        <ProposalCard key={proposal.id} proposal={proposal} conversationId={message.conversation_id} />
      ))}
      <FileLinks files={message.files} />
    </div>
  )
}

/** Past this, "pensando…" says why it may be taking long (one turn at a time for the whole company). */
const SLOW_ANSWER_MS = 90_000

/** Flips once the answer in progress has taken longer than SLOW_ANSWER_MS (a timer, not a clock read in render). */
function useSlowAnswer(message: AssistantMessage): boolean {
  const [slow, setSlow] = useState(false)
  const inProgress = isInProgress(message)

  useEffect(() => {
    if (!inProgress) return
    const remaining = Math.max(0, SLOW_ANSWER_MS - (Date.now() - Date.parse(message.created_at)))
    const timer = setTimeout(() => setSlow(true), remaining)
    return () => clearTimeout(timer)
  }, [inProgress, message.created_at])

  return inProgress && slow
}
