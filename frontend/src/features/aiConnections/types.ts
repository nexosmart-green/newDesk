export const MCP_USE_PERMISSION = 'mcp.use'
export const MCP_MANAGE_TOKENS_PERMISSION = 'mcp.manage_tokens'

/** A personal access token as listed (the secret itself is never returned again). */
export interface AiToken {
  id: number
  name: string
  last_used_at: string | null
  expires_at: string | null
  created_at: string | null
}

export interface CompanyAiToken extends AiToken {
  owner: { id: number; full_name: string }
}

export interface MyAiTokens {
  /** The MCP endpoint, e.g. `https://desk.nexosmart.com/mcp`. */
  endpoint: string
  tokens: AiToken[]
}

/** RF-12.02: 30 / 90 / 365 days or no expiry. */
export type TokenExpiry = 30 | 90 | 365 | null

export const expiryOptions: readonly { value: TokenExpiry; label: string }[] = [
  { value: 30, label: '30 días' },
  { value: 90, label: '90 días' },
  { value: 365, label: '365 días' },
  { value: null, label: 'Sin vencimiento' },
]

export interface CreateTokenInput {
  name: string
  expires_in_days: TokenExpiry
}

export interface ProjectMcpJson {
  mcpServers: Record<string, { type: string; url: string; headers: Record<string, string> }>
}

/** `POST /me/ai-tokens`: `plain_text_token` and the command embedding it exist ONLY in this response. */
export interface IssuedToken {
  token: AiToken
  plain_text_token: string
  claude_code_command: string
  project_mcp_json: ProjectMcpJson
}
