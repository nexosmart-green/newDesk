import { api, dataOf, envelopeOf, toApiError, type ApiEnvelope } from '@/lib/api'
import type { AskInput, AssistantConversation, DiscordLinkCode, AssistantMessage, AssistantProposal, AssistantUsage, ConversationList } from './types'

interface ConversationsResponse {
  data: AssistantConversation[]
  meta: { enabled: boolean; discord_linked: boolean; discord_available: boolean }
}

export async function fetchConversations(): Promise<ConversationList> {
  try {
    const response = await api.get<ConversationsResponse>('/assistant/conversations')
    const { enabled, discord_linked, discord_available } = response.data.meta
    return { conversations: response.data.data, enabled, discordLinked: discord_linked, discordAvailable: discord_available }
  } catch (error) {
    throw toApiError(error)
  }
}

export async function fetchMessages(conversationId: number): Promise<AssistantMessage[]> {
  return dataOf(api.get<ApiEnvelope<AssistantMessage[]>>(`/assistant/conversations/${conversationId}/messages`))
}

/** Queues the turn and returns the answer `pending`: the thread polls until it is done. */
export async function askAssistant(input: AskInput): Promise<AssistantMessage> {
  return dataOf(api.post<ApiEnvelope<AssistantMessage>>('/assistant/messages', input))
}

/** The whole envelope: the backend's message is the toast ("Listo, quedó hecho."). */
export async function resolveProposal(proposal: AssistantProposal, action: 'confirm' | 'cancel'): Promise<ApiEnvelope<AssistantProposal>> {
  return envelopeOf(api.post<ApiEnvelope<AssistantProposal>>(`/assistant/proposals/${proposal.id}/${action}`))
}

export async function fetchAssistantUsage(): Promise<AssistantUsage> {
  return dataOf(api.get<ApiEnvelope<AssistantUsage>>('/assistant/usage'))
}

/** RF-14.08: a one-time code to type in Discord as /newdesk vincular codigo:… */
export async function createDiscordLinkCode(): Promise<DiscordLinkCode> {
  return dataOf(api.post<ApiEnvelope<DiscordLinkCode>>('/assistant/discord-link'))
}
