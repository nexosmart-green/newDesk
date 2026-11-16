export const ASSISTANT_PERMISSION = 'assistant.use'

export type MessageStatus = 'pending' | 'running' | 'done' | 'failed'
export type ProposalStatus = 'pending' | 'confirmed' | 'cancelled' | 'failed' | 'expired'

/** RF-13.02: a change the assistant prepared that waits for the person who asked. */
export interface AssistantProposal {
  id: number
  summary: string
  status: ProposalStatus
  error: string | null
  expires_at: string
  resolved_at: string | null
}

/** RF-13.05: a report file attached to an answer. */
export interface AssistantFile {
  uuid: string
  name: string
  format: 'xlsx' | 'pdf'
  size: number
  url: string
  expires_at: string
}

export interface AssistantMessage {
  id: number
  conversation_id: number
  role: 'user' | 'assistant'
  content: string | null
  status: MessageStatus
  error: string | null
  created_at: string
  proposals: AssistantProposal[]
  files: AssistantFile[]
}

export interface AssistantConversation {
  id: number
  title: string | null
  channel: 'web' | 'discord'
  last_message_at: string | null
}

export interface ConversationList {
  conversations: AssistantConversation[]
  /** The Admin can turn the assistant off (Configuración → Asistente). */
  enabled: boolean
}

export interface AskInput {
  text: string
  conversation_id?: number
}

export interface AssistantUsage {
  month_spent_usd: number
  monthly_budget_usd: number
  models: { value: string; label: string }[]
}

export function isInProgress(message: AssistantMessage): boolean {
  return message.status === 'pending' || message.status === 'running'
}
