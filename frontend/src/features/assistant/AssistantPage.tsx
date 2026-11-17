import { MessagesSquare, PowerOff } from 'lucide-react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { Menu } from '@/components/ui/Menu'
import { PageHeader } from '@/components/ui/PageHeader'
import { PageState } from '@/components/ui/PageState'
import { useCan } from '@/features/auth/useAuth'
import { useSearchParamPatch } from '@/lib/useSearchParamPatch'
import { Composer } from './Composer'
import { ConnectDiscordButton } from './ConnectDiscordButton'
import { ConversationList } from './ConversationList'
import { Suggestions } from './Suggestions'
import { Thread } from './Thread'
import { ASSISTANT_PERMISSION, isInProgress } from './types'
import { useAskAssistant, useConversationMessages, useConversations } from './useAssistant'

const MOBILE_CONVERSATIONS = 15

/**
 * RF-14.01: the web channel of the assistant (`assistant.use`). Conversations on the left, the thread and the
 * composer on the right; on phones the conversations are a menu. The open one lives in `?c=`.
 */
export function AssistantPage() {
  const canUse = useCan(ASSISTANT_PERMISSION)
  const canConfigure = useCan('company.manage')
  const [searchParams, patch] = useSearchParamPatch()
  const conversationId = Number(searchParams.get('c')) || null
  const conversations = useConversations(canUse)
  const messages = useConversationMessages(conversationId)
  const ask = useAskAssistant()

  if (!canUse) return <p className="text-muted">Tu rol no tiene habilitado el asistente de IA.</p>
  if (!conversations.data) {
    return <PageState isPending={conversations.isPending} error={conversations.error} onRetry={() => void conversations.refetch()} />
  }

  const { conversations: list, enabled, discordLinked, discordAvailable } = conversations.data
  const busy = messages.data?.some(isInProgress) ?? false

  function send(text: string) {
    ask.mutate(
      { text, ...(conversationId !== null && { conversation_id: conversationId }) },
      { onSuccess: (answer) => patch({ c: String(answer.conversation_id) }) },
    )
  }

  return (
    <section className="flex h-[calc(100dvh-6.5rem)] min-h-[30rem] flex-col gap-4">
      <PageHeader
        title="Asistente"
        actions={
          <>
            {list.length > 0 && (
              <div className="lg:hidden">
                <Menu
                  label="Abrir una conversación"
                  trigger={
                    <>
                      <MessagesSquare aria-hidden="true" className="size-4" strokeWidth={1.75} />
                      Conversaciones
                    </>
                  }
                  triggerClassName="inline-flex min-h-9 items-center gap-2 rounded-md border border-line bg-surface px-3.5 text-sm coarse:min-h-11"
                  align="start"
                  items={list.slice(0, MOBILE_CONVERSATIONS).map((conversation) => ({
                    label: conversation.title ?? 'Conversación',
                    checked: conversation.id === conversationId,
                    onSelect: () => patch({ c: String(conversation.id) }),
                  }))}
                />
              </div>
            )}
            {enabled && discordAvailable && !discordLinked && <ConnectDiscordButton />}
            {conversationId !== null && (
              <Button variant="secondary" onClick={() => patch({ c: null })}>
                Nueva conversación
              </Button>
            )}
          </>
        }
      />

      {!enabled && (
        <EmptyState
          icon={PowerOff}
          action={
            canConfigure && (
              <Link to="/configuracion/asistente" className="text-sm font-medium text-accent hover:underline">
                Prender el asistente
              </Link>
            )
          }
        >
          El asistente está apagado. {canConfigure ? 'Prendelo en la configuración.' : 'Un administrador lo puede prender en la configuración.'}
        </EmptyState>
      )}

      <div className="grid min-h-0 flex-1 gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
        <nav aria-label="Conversaciones" className="hidden min-h-0 overflow-y-auto lg:block">
          <ConversationList conversations={list} selectedId={conversationId} />
        </nav>
        <div className="flex min-h-0 flex-col gap-4">
          <div className="min-h-0 flex-1 overflow-y-auto">
            <div className="mx-auto max-w-3xl pb-2">
              {conversationId === null ? (
                <Suggestions onPick={send} disabled={!enabled || ask.isPending} />
              ) : (
                <Thread conversationId={conversationId} onRetry={send} retrying={ask.isPending} />
              )}
            </div>
          </div>
          {enabled && (
            <div className="mx-auto w-full max-w-3xl">
              <Composer onSend={send} sending={ask.isPending} busy={busy} />
            </div>
          )}
        </div>
      </div>
    </section>
  )
}
