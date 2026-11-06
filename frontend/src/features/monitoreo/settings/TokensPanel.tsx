import { useState } from 'react'
import { ApiError } from '@/lib/api'
import { errorMessage } from '../api/monitoringApi'
import { CopyButton } from '../components/CopyButton'
import { DataTable } from '../components/DataTable'
import { Icon } from '../components/Icon'
import { ErrorNote, Panel, Spinner } from '../components/Panel'
import { formatDateTime, formatRelative } from '../lib/format'
import { ConfirmButton } from './ConfirmButton'
import { useTokenMutations, useTokens, type PersonalToken, type PersonalTokenCreated } from './useTokens'

// El de monitoreo (TokensPanel), con los tokens personales de newDesk: el mismo token sirve para el MCP de monitoreo.
// https://<dominio>/mcp/monitoreo en prod; en local el proxy de Vite manda /mcp al back.
const mcpUrl = () => new URL('/mcp/monitoreo', window.location.origin).href

const expiries = [
  { days: null, label: 'Sin vencimiento' },
  { days: 30, label: '30 días' },
  { days: 90, label: '90 días' },
  { days: 365, label: '365 días' },
] as const

export function TokensPanel() {
  const tokens = useTokens()
  const { create, revoke } = useTokenMutations()
  const [name, setName] = useState('')
  const [expiresInDays, setExpiresInDays] = useState<number | null>(null)
  const [created, setCreated] = useState<PersonalTokenCreated | null>(null)
  const createError = create.error instanceof ApiError ? (create.error.field('name') ?? create.error.field('expires_in_days') ?? create.error.message) : null

  if (tokens.isError) return <ErrorNote onRetry={() => void tokens.refetch()}>{errorMessage(tokens.error)}</ErrorNote>

  return (
    <div className="space-y-5">
      <Panel title="Crear token">
        <p className="mb-4 text-sm text-muted">Para conectar Claude por MCP. Cada token es personal: lo que haga Claude queda a tu nombre.</p>
        <form
          className="flex flex-wrap items-end gap-3"
          onSubmit={(event) => {
            event.preventDefault()
            create.mutate({ name: name.trim(), expires_in_days: expiresInDays }, { onSuccess: (token) => { setCreated(token); setName('') } })
          }}
        >
          <label className="min-w-[240px] flex-1">
            <span className="label mb-1.5 block">Nombre</span>
            <input className="input" autoComplete="off" maxLength={80} value={name} onChange={(e) => setName(e.target.value)} placeholder="Claude de Iña" aria-invalid={createError ? 'true' : 'false'} />
          </label>
          <label>
            <span className="label mb-1.5 block">Vence</span>
            <select className="input" value={expiresInDays ?? ''} onChange={(e) => setExpiresInDays(e.target.value === '' ? null : Number(e.target.value))}>
              {expiries.map((expiry) => (
                <option key={expiry.label} value={expiry.days ?? ''}>{expiry.label}</option>
              ))}
            </select>
          </label>
          <button type="submit" className="btn btn-primary h-10" disabled={!name.trim() || create.isPending}>
            {create.isPending && <Spinner />}
            Crear token
          </button>
        </form>
        {createError && <div className="mt-3"><ErrorNote>{createError}</ErrorNote></div>}
        {created && <NewToken token={created} />}
      </Panel>

      <Panel title={tokens.data ? `Tokens de API · ${tokens.data.length}` : 'Tokens de API'} bodyClassName="">
        <DataTable<PersonalToken>
          rows={tokens.data ?? []}
          loading={tokens.isPending}
          rowKey={(row) => String(row.id)}
          empty="Todavía no creaste tokens."
          noun="tokens"
          columns={[
            { header: 'Nombre', cell: (row) => <span className="font-medium">{row.name}</span> },
            { header: 'Creado', cell: (row) => <span className="num text-xs text-muted" title={formatDateTime(row.created_at)}>{formatRelative(row.created_at)}</span> },
            { header: 'Último uso', cell: (row) => <span className="num text-xs text-muted" title={formatDateTime(row.last_used_at)}>{formatRelative(row.last_used_at)}</span> },
            { header: 'Vence', cell: (row) => <span className="num text-xs text-muted">{row.expires_at ? formatDateTime(row.expires_at) : 'Nunca'}</span> },
            {
              header: '',
              align: 'right',
              cell: (row) => (
                <ConfirmButton
                  label="Revocar"
                  confirmLabel="¿Seguro? Claude deja de tener acceso"
                  onConfirm={() => revoke.mutate(row.id, { onSuccess: () => setCreated((shown) => (shown?.id === row.id ? null : shown)) })}
                />
              ),
            },
          ]}
        />
      </Panel>
    </div>
  )
}

// El token en texto plano llega una sola vez: al recargar o salir de la pestaña se pierde.
function NewToken({ token }: { token: PersonalTokenCreated }) {
  const command = `claude mcp add --transport http monitoreo ${mcpUrl()} --header "Authorization: Bearer ${token.token}"`
  return (
    <div className="fade-in mt-4 space-y-3 rounded-xl border border-line-strong bg-surface-2 p-4" aria-label={`Token ${token.name}`} role="region">
      <p className="flex items-center gap-1.5 text-sm font-medium text-warn">
        <Icon name="alert" size={14} />
        Copialo ahora: no se vuelve a mostrar.
      </p>
      <CopyRow label="Token" text={token.token} />
      <CopyRow label="Comando para Claude Code" text={command} />
    </div>
  )
}

function CopyRow({ label, text }: { label: string; text: string }) {
  return (
    <div>
      <span className="label mb-1.5 block">{label}</span>
      <div className="flex items-start gap-2">
        <code className="min-w-0 flex-1 rounded-lg bg-surface px-3 py-2 font-mono text-xs break-all">{text}</code>
        <CopyButton text={text} title={`Copiar ${label.toLowerCase()}`} />
      </div>
    </div>
  )
}
