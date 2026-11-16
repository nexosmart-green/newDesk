import { useEffect, useRef } from 'react'
import { PageState } from '@/components/ui/PageState'
import { MessageItem } from './MessageItem'
import type { AssistantMessage } from './types'
import { useConversationMessages } from './useAssistant'

interface ThreadProps {
  conversationId: number
  onRetry: (text: string) => void
  retrying: boolean
}

/** The conversation, oldest first, kept scrolled to the latest line; answers are announced as they arrive. */
export function Thread({ conversationId, onRetry, retrying }: ThreadProps) {
  const messages = useConversationMessages(conversationId)
  const endRef = useRef<HTMLDivElement>(null)
  const lastState = messages.data?.map((message) => `${message.id}:${message.status}`).join(',') ?? ''

  useEffect(() => {
    endRef.current?.scrollIntoView?.({ block: 'end' })
  }, [lastState])

  if (!messages.data) {
    return <PageState isPending={messages.isPending} error={messages.error} onRetry={() => void messages.refetch()} />
  }

  return (
    <div role="log" aria-label="Conversación con el asistente" className="space-y-6">
      {messages.data.map((message, index) => (
        <MessageItem
          key={message.id}
          message={message}
          retrying={retrying}
          onRetry={message.status === 'failed' && index === messages.data.length - 1 ? () => onRetry(questionBefore(messages.data, index)) : undefined}
        />
      ))}
      <div ref={endRef} />
    </div>
  )
}

function questionBefore(messages: AssistantMessage[], index: number): string {
  return messages.slice(0, index).reverse().find((message) => message.role === 'user')?.content ?? ''
}
