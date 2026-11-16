import { Link } from 'react-router'
import { useCurrentUser } from '@/features/auth/useAuth'
import { formatInstant } from '@/lib/dates'
import type { AssistantConversation } from './types'

interface ConversationListProps {
  conversations: AssistantConversation[]
  selectedId: number | null
}

/** My conversations, the most recent first. Each one is a link (`?c=`), so it can be reopened or shared with myself. */
export function ConversationList({ conversations, selectedId }: ConversationListProps) {
  const timeZone = useCurrentUser().data?.timezone ?? 'UTC'

  if (conversations.length === 0) {
    return <p className="px-3 py-2 text-sm text-muted">Todavía no hay conversaciones.</p>
  }

  return (
    <ul className="space-y-0.5">
      {conversations.map((conversation) => {
        const current = conversation.id === selectedId
        return (
          <li key={conversation.id}>
            <Link
              to={{ search: `?c=${conversation.id}` }}
              aria-current={current ? 'page' : undefined}
              className={`block rounded-md px-3 py-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand ${current ? 'bg-surface' : 'hover:bg-surface'}`}
            >
              <span className={`line-clamp-2 text-sm ${current ? 'font-medium' : ''}`}>{conversation.title ?? 'Conversación'}</span>
              {conversation.last_message_at && (
                <span className="tabular mt-0.5 block text-xs text-muted">{formatInstant(conversation.last_message_at, timeZone)}</span>
              )}
            </Link>
          </li>
        )
      })}
    </ul>
  )
}
