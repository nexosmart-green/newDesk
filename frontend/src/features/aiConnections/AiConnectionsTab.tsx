import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { PageState } from '@/components/ui/PageState'
import { useCan } from '@/features/auth/useAuth'
import { CreateTokenDialog } from './CreateTokenDialog'
import { IssuedTokenDialog } from './IssuedTokenDialog'
import { TokenList } from './TokenList'
import { MCP_USE_PERMISSION, type IssuedToken } from './types'
import { useMyAiTokens } from './useAiConnections'

/** "Mi perfil → Conexiones de IA" (RF-12.02/04): personal tokens to use newDesk from Claude Code. */
export function AiConnectionsTab() {
  const canUseMcp = useCan(MCP_USE_PERMISSION)
  const tokens = useMyAiTokens()
  const [creating, setCreating] = useState(false)
  // Lives only while its dialog is open: once closed, the secret is gone for good.
  const [issued, setIssued] = useState<IssuedToken | null>(null)

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted">
        Con un token personal usás newDesk desde Claude Code (u otro cliente MCP) con los mismos permisos de tu rol.
        {tokens.data && (
          <>
            {' '}
            Endpoint: <code className="font-mono">{tokens.data.endpoint}</code>
          </>
        )}
      </p>
      {canUseMcp ? (
        <Button onClick={() => setCreating(true)}>Generar token</Button>
      ) : (
        <p className="rounded border border-border bg-surface px-3 py-2 text-sm">
          Tu rol no tiene habilitado el uso de MCP. Si lo necesitás, pedíselo a quien administra los roles.
        </p>
      )}
      {!tokens.data ? (
        <PageState isPending={tokens.isPending} error={tokens.error} onRetry={() => void tokens.refetch()} />
      ) : (
        <TokenList tokens={tokens.data.tokens} label="Mis tokens" />
      )}
      {creating && (
        <CreateTokenDialog
          onClose={() => setCreating(false)}
          onIssued={(result) => {
            setCreating(false)
            setIssued(result)
          }}
        />
      )}
      {issued && <IssuedTokenDialog issued={issued} onClose={() => setIssued(null)} />}
    </div>
  )
}
