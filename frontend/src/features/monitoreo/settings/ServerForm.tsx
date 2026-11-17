import { useState, type FormEvent, type ReactNode } from 'react'
import { ApiError } from '@/lib/api'
import type { ServerInput, ServerSummary } from '../api/types'
import { ErrorNote, Spinner } from '../components/Panel'

interface ServerFormProps {
  initial?: ServerSummary
  pending: boolean
  error: unknown
  onSubmit: (input: ServerInput) => void
  onCancel: () => void
}

export function ServerForm({ initial, pending, error, onSubmit, onCancel }: ServerFormProps) {
  const [name, setName] = useState(initial?.name ?? '')
  const [ip, setIp] = useState(initial?.ip ?? '')
  const [sshPort, setSshPort] = useState(initial?.ssh_port ? String(initial.ssh_port) : '')
  const [agentPort, setAgentPort] = useState(String(initial?.agent_port ?? 9100))
  const [token, setToken] = useState('')
  const [fingerprint, setFingerprint] = useState('')
  const [enabled, setEnabled] = useState(initial?.enabled ?? true)
  const apiError = error instanceof ApiError ? error : null
  const editing = Boolean(initial)

  const submit = (event: FormEvent) => {
    event.preventDefault()
    onSubmit({
      name: name.trim(),
      ip: ip.trim(),
      ssh_port: sshPort ? Number(sshPort) : null,
      agent_port: Number(agentPort) || 9100,
      token: token.trim() || undefined,
      tls_fingerprint: fingerprint.trim() || undefined,
      enabled,
    })
  }

  return (
    <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2" noValidate>
      <Field label="Nombre" error={apiError?.fieldErrors.name?.[0]}>
        <input className="input" required value={name} onChange={(e) => setName(e.target.value)} placeholder="reaquila" />
      </Field>
      <Field label="IP" error={apiError?.fieldErrors.ip?.[0]}>
        <input className="input font-mono" required value={ip} onChange={(e) => setIp(e.target.value)} placeholder="51.222.205.245" inputMode="decimal" />
      </Field>
      <Field label="Puerto SSH (opcional, lo detecta el agente)" error={apiError?.fieldErrors.ssh_port?.[0]}>
        <input className="input num" value={sshPort} onChange={(e) => setSshPort(e.target.value.replace(/\D/g, ''))} placeholder="1080" inputMode="numeric" />
      </Field>
      <Field label="Puerto del agente" error={apiError?.fieldErrors.agent_port?.[0]}>
        <input className="input num" value={agentPort} onChange={(e) => setAgentPort(e.target.value.replace(/\D/g, ''))} inputMode="numeric" />
      </Field>
      <Field label={editing ? 'Token (vacío = no cambia; obligatorio si cambian la IP, el puerto o el fingerprint)' : 'Token del agente'} error={apiError?.fieldErrors.token?.[0]}>
        <input className="input font-mono" type="password" autoComplete="new-password" value={token} onChange={(e) => setToken(e.target.value)} placeholder="salida de monitor-agent gen-token" />
      </Field>
      <Field label={editing ? 'Fingerprint TLS (vacío = no cambia)' : 'Fingerprint TLS (SHA-256)'} error={apiError?.fieldErrors.tls_fingerprint?.[0]}>
        <input className="input font-mono text-xs" autoComplete="off" spellCheck={false} value={fingerprint} onChange={(e) => setFingerprint(e.target.value)} placeholder="e69b8a32…" />
      </Field>
      {editing && (
        <label className="flex items-center gap-2 text-sm sm:col-span-2">
          <input type="checkbox" className="size-4" checked={enabled} onChange={(e) => setEnabled(e.target.checked)} />
          Polling habilitado
        </label>
      )}
      {apiError && Object.keys(apiError.fieldErrors).length === 0 && <div className="sm:col-span-2"><ErrorNote>{apiError.message}</ErrorNote></div>}
      <div className="flex gap-2 sm:col-span-2">
        <button type="submit" className="btn btn-primary" disabled={pending || !name || !ip || (!editing && !token) || !fingerprint}>
          {pending ? (
            <>
              <Spinner /> Guardando…
            </>
          ) : editing ? (
            'Guardar cambios'
          ) : (
            'Agregar servidor'
          )}
        </button>
        <button type="button" className="btn" onClick={onCancel}>
          Cancelar
        </button>
      </div>
    </form>
  )
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return (
    <label className="block">
      <span className="label mb-1.5 block">{label}</span>
      {children}
      {error && <span className="fade-in mt-1.5 block text-xs text-crit">{error}</span>}
    </label>
  )
}
