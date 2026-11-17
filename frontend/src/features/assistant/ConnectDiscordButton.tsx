import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { Button } from '@/components/ui/Button'
import { CopyButton } from '@/components/ui/CopyButton'
import { Dialog } from '@/components/ui/Dialog'
import { createDiscordLinkCode } from './assistantApi'

/**
 * RF-14.08: "Conectar Discord" gives a one-time code to type as /newdesk vincular in the company's server.
 * The code is asked for only when the person opens the dialog, and is not kept in the cache.
 */
export function ConnectDiscordButton() {
  const [open, setOpen] = useState(false)
  const code = useMutation({ mutationFn: createDiscordLinkCode, gcTime: 0 })

  function start() {
    setOpen(true)
    code.mutate()
  }

  return (
    <>
      <Button variant="secondary" onClick={start}>
        Conectar Discord
      </Button>
      {open && (
        <Dialog
          title="Conectar Discord"
          description="Para pedirle cosas al asistente desde el servidor de Discord de la empresa."
          onClose={() => setOpen(false)}
        >
          {code.data ? (
            <div className="space-y-4">
              <p className="text-sm">En cualquier canal del servidor, escribí este comando:</p>
              <div className="flex items-center gap-2 rounded-md border border-line bg-field px-3 py-2">
                <code className="min-w-0 flex-1 break-all text-sm">{code.data.command}</code>
                <CopyButton value={code.data.command} label="Copiar comando" />
              </div>
              <p className="text-[13px] text-muted">
                El código sirve una sola vez y vence en {code.data.expires_in_minutes} minutos. Después usá /newdesk pedir y escribí tu pedido;
                las respuestas solo las ves vos.
              </p>
            </div>
          ) : (
            <p role="status" className="text-sm text-muted">
              {code.isError ? 'No se pudo generar el código. Cerrá y probá de nuevo.' : 'Generando el código…'}
            </p>
          )}
        </Dialog>
      )}
    </>
  )
}
