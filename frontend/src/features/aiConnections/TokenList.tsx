import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { formatDateTime } from '@/lib/dates'
import type { AiToken, CompanyAiToken } from './types'
import { useRevokeAiToken } from './useAiConnections'

const hasOwner = (token: AiToken): token is CompanyAiToken => 'owner' in token

/** Tokens with created / last used / expiry and a confirmed "Revocar" (RF-12.02/05). */
export function TokenList({ tokens, label }: { tokens: readonly AiToken[]; label: string }) {
  const revoke = useRevokeAiToken()
  const [revoking, setRevoking] = useState<AiToken | null>(null)

  function close() {
    revoke.reset()
    setRevoking(null)
  }

  if (tokens.length === 0) return <p className="text-sm text-muted">No hay tokens.</p>

  return (
    <>
      <ul aria-label={label} className="divide-y divide-border rounded-lg border border-border bg-surface">
        {tokens.map((token) => (
          <li key={token.id} className="flex flex-wrap items-center gap-3 p-3 text-sm">
            <div className="min-w-0 flex-1 space-y-0.5">
              <p className="font-medium">{token.name}</p>
              {hasOwner(token) && <p className="text-muted">{token.owner.full_name}</p>}
              <p className="flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-muted">
                {token.created_at && <span>Creado {formatDateTime(token.created_at)}</span>}
                <span>Último uso {token.last_used_at ? formatDateTime(token.last_used_at) : 'nunca'}</span>
                <span>{token.expires_at ? `Vence ${formatDateTime(token.expires_at)}` : 'Sin vencimiento'}</span>
              </p>
            </div>
            <Button variant="secondary" aria-label={`Revocar ${token.name}`} onClick={() => setRevoking(token)}>
              Revocar
            </Button>
          </li>
        ))}
      </ul>
      {revoking && (
        <ConfirmDialog
          title={`¿Revocar «${revoking.name}»?`}
          confirmLabel="Revocar"
          danger
          pending={revoke.isPending}
          error={revoke.error}
          onConfirm={() => revoke.mutate(revoking, { onSuccess: close })}
          onClose={close}
        >
          <p>Lo que use este token (Claude Code u otro cliente) deja de funcionar en el próximo pedido.</p>
        </ConfirmDialog>
      )}
    </>
  )
}
