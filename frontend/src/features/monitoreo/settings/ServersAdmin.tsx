import { useState } from 'react'
import { errorMessage } from '../api/monitoringApi'
import { useServerMutations, useServers } from '../api/useMonitoring'
import type { ServerInput, ServerSummary, TestResult } from '../api/types'
import { DataTable } from '../components/DataTable'
import { Icon } from '../components/Icon'
import { ErrorNote, Panel, Spinner } from '../components/Panel'
import { Badge, ServerStatusBadge } from '../components/Status'
import { hostPort } from '../lib/format'
import { ConfirmButton } from './ConfirmButton'
import { ServerForm } from './ServerForm'

type Editing = { mode: 'create' } | { mode: 'edit'; server: ServerSummary } | null

export function ServersAdmin() {
  const servers = useServers()
  const { create, update, remove, test } = useServerMutations()
  const [editing, setEditing] = useState<Editing>(null)
  const [results, setResults] = useState<Record<number, TestResult | 'pending'>>({})

  const save = (input: ServerInput) => {
    const done = { onSuccess: () => setEditing(null) }
    if (editing?.mode === 'edit') update.mutate({ id: editing.server.id, input }, done)
    else create.mutate(input, done)
  }
  const runTest = (id: number) => {
    setResults((current) => ({ ...current, [id]: 'pending' }))
    test.mutate(id, {
      onSuccess: (result) => setResults((current) => ({ ...current, [id]: result })),
      onError: (error) => setResults((current) => ({ ...current, [id]: { ok: false, error: errorMessage(error) } })),
    })
  }

  if (servers.isError) return <ErrorNote onRetry={() => void servers.refetch()}>{errorMessage(servers.error)}</ErrorNote>

  return (
    <div className="space-y-5">
      {editing ? (
        <Panel className="rise" title={editing.mode === 'edit' ? `Editar ${editing.server.name}` : 'Nuevo servidor'}>
          <p className="mb-4 text-sm text-muted">
            El token lo imprime <code className="text-xs text-text">monitor-agent gen-token</code> y el fingerprint <code className="text-xs text-text">monitor-agent fingerprint</code>, al instalar el agente. Acordate de permitir la IP del panel en la whitelist y en UFW.
          </p>
          <ServerForm
            key={editing.mode === 'edit' ? editing.server.id : 'new'}
            initial={editing.mode === 'edit' ? editing.server : undefined}
            pending={create.isPending || update.isPending}
            error={editing.mode === 'edit' ? update.error : create.error}
            onSubmit={save}
            onCancel={() => setEditing(null)}
          />
        </Panel>
      ) : (
        <button type="button" className="btn btn-primary" onClick={() => setEditing({ mode: 'create' })}>
          <Icon name="plus" size={14} /> Agregar servidor
        </button>
      )}

      <Panel title={servers.data ? `Servidores · ${servers.data.length}` : 'Servidores'} bodyClassName="">
        <DataTable<ServerSummary>
          noun="servidores"
          rows={servers.data ?? []}
          loading={servers.isPending}
          rowKey={(row) => String(row.id)}
          empty="Sin servidores."
          columns={[
            { header: 'Nombre', cell: (row) => <span className="font-medium">{row.name}</span> },
            { header: 'IP:SSH', cell: (row) => <span className="font-mono text-xs">{hostPort(row.ip, row.ssh_port)}</span> },
            { header: 'Agente', cell: (row) => <span className="font-mono text-xs text-muted">:{row.agent_port} {row.agent_version ?? ''}</span> },
            { header: 'Estado', cell: (row) => <ServerStatusBadge status={row.status} /> },
            { header: 'Conexión', cell: (row) => <TestCell result={results[row.id]} /> },
            {
              header: 'Acciones',
              cell: (row) => (
                <div className="flex flex-wrap gap-1.5">
                  <button type="button" className="btn btn-sm" onClick={() => runTest(row.id)} disabled={results[row.id] === 'pending'}>
                    Probar conexión
                  </button>
                  <button type="button" className="btn btn-sm" onClick={() => setEditing({ mode: 'edit', server: row })}>
                    Editar
                  </button>
                  <ConfirmButton label="Eliminar" confirmLabel="Confirmar" onConfirm={() => remove.mutate(row.id)} />
                </div>
              ),
            },
          ]}
        />
      </Panel>
    </div>
  )
}

function TestCell({ result }: { result: TestResult | 'pending' | undefined }) {
  if (!result) return <span className="text-faint">—</span>
  if (result === 'pending') {
    return (
      <span className="inline-flex items-center gap-1.5 text-xs text-muted">
        <Spinner /> Probando…
      </span>
    )
  }
  return result.ok ? (
    <Badge tone="ok">OK {result.latency_ms} ms</Badge>
  ) : (
    <span className="fade-in text-xs text-crit">{result.error ?? 'falló'}</span>
  )
}
