import { PageState } from '@/components/ui/PageState'
import { TokenList } from './TokenList'
import { useCompanyAiTokens } from './useAiConnections'

/** "Configuración → Tokens de IA" (RF-12.05, `mcp.manage_tokens`): everyone's tokens, revocable. */
export function CompanyTokensTab() {
  const tokens = useCompanyAiTokens()

  if (!tokens.data) return <PageState isPending={tokens.isPending} error={tokens.error} onRetry={() => void tokens.refetch()} />
  return (
    <div className="space-y-3">
      <p className="text-sm text-muted">Tokens personales de MCP de toda la empresa. Revocar uno corta su acceso al instante.</p>
      <TokenList tokens={tokens.data} label="Tokens de la empresa" />
    </div>
  )
}
