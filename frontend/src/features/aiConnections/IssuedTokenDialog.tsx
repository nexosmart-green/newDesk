import { Button } from '@/components/ui/Button'
import { CopyableValue } from '@/components/ui/CopyButton'
import { Dialog } from '@/components/ui/Dialog'
import type { IssuedToken } from './types'

/**
 * RF-12.02/04: the only time the token is visible. Closing drops it from memory; the list only keeps
 * name/dates. The `.mcp.json` reads `${NEWDESK_TOKEN}` so the secret never lands in a repo.
 */
export function IssuedTokenDialog({ issued, onClose }: { issued: IssuedToken; onClose: () => void }) {
  return (
    <Dialog title={`Token «${issued.token.name}» generado`} onClose={onClose}>
      <div className="space-y-4 text-sm">
        <p role="alert" className="rounded border border-warning/40 bg-warning/10 px-3 py-2 text-warning">
          Copialo ahora: no se vuelve a mostrar. Nunca commitees el token.
        </p>
        <div className="space-y-1">
          <h3 className="font-medium">Token</h3>
          <CopyableValue value={issued.plain_text_token} label="Copiar token" />
        </div>
        <div className="space-y-1">
          <h3 className="font-medium">Conectar Claude Code</h3>
          <p className="text-xs text-muted">Pegalo en la terminal:</p>
          <CopyableValue value={issued.claude_code_command} label="Copiar comando de Claude Code" />
        </div>
        {issued.monitoreo_claude_code_command && (
          <div className="space-y-1">
            <h3 className="font-medium">Conectar el MCP de monitoreo</h3>
            <p className="text-xs text-muted">El mismo token sirve para monitoreo. Pegalo también en la terminal:</p>
            <CopyableValue value={issued.monitoreo_claude_code_command} label="Copiar comando de monitoreo" />
          </div>
        )}
        <div className="space-y-1">
          <h3 className="font-medium">O en el proyecto: .mcp.json</h3>
          <p className="text-xs text-muted">
            Usa la variable de entorno <code className="font-mono">NEWDESK_TOKEN</code>: definila en tu máquina (no en el
            repo) con el token de arriba.
          </p>
          <CopyableValue value={JSON.stringify(issued.project_mcp_json, null, 2)} label="Copiar .mcp.json" />
        </div>
        <div className="flex justify-end">
          <Button onClick={onClose}>Ya lo guardé</Button>
        </div>
      </div>
    </Dialog>
  )
}
