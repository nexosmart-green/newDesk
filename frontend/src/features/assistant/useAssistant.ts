import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import { assistantConversationsKey, assistantMessagesKey, assistantUsageKey } from '@/lib/queryKeys'
import { askAssistant, fetchAssistantUsage, fetchConversations, fetchMessages, resolveProposal } from './assistantApi'
import { isInProgress, type AskInput, type AssistantProposal } from './types'

/** While an answer is being written the thread asks again every 2 s; otherwise it stays still. */
export const THINKING_POLL_MS = 2000

export function useConversations(enabled: boolean) {
  return useQuery({ queryKey: assistantConversationsKey, queryFn: fetchConversations, enabled })
}

export function useConversationMessages(conversationId: number | null) {
  return useQuery({
    queryKey: assistantMessagesKey(conversationId ?? 0),
    queryFn: () => fetchMessages(conversationId ?? 0),
    enabled: conversationId !== null,
    // Stops on errors; the server closes a turn that never ends (assistant:housekeeping), which also stops it.
    refetchInterval: (query) => (query.state.status !== 'error' && query.state.data?.some(isInProgress) ? THINKING_POLL_MS : false),
  })
}

export function useAskAssistant() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: AskInput) => askAssistant(input),
    onSuccess: (answer) =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: assistantMessagesKey(answer.conversation_id) }),
        queryClient.invalidateQueries({ queryKey: assistantConversationsKey }),
      ]),
  })
}

/** Confirm or cancel a proposal; the toast is the backend's own words. A refusal toasts by default. */
export function useResolveProposal(conversationId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ proposal, action }: { proposal: AssistantProposal; action: 'confirm' | 'cancel' }) => resolveProposal(proposal, action),
    onSuccess: (envelope) => {
      if (envelope.message) toast.success(envelope.message)
    },
    // Confirmed, cancelled or refused: the card shows how it ended.
    onSettled: () => queryClient.invalidateQueries({ queryKey: assistantMessagesKey(conversationId) }),
  })
}

export function useAssistantUsage(enabled: boolean) {
  return useQuery({ queryKey: assistantUsageKey, queryFn: fetchAssistantUsage, enabled })
}
