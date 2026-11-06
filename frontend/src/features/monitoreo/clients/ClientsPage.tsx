import { useState } from 'react'
import { Link } from 'react-router'
import { useCan } from '@/features/auth/useAuth'
import { ApiError } from '@/lib/api'
import { errorMessage } from '../api/monitoringApi'
import { useClients } from '../api/usePortalAdmin'
import { useClientMutations } from '../api/useProjectMutations'
import type { ClientRow } from '../api/types'
import { DataTable } from '../components/DataTable'
import { ErrorNote, Panel, Spinner } from '../components/Panel'
import { Badge } from '../components/Status'
import { formatDateTime } from '../lib/format'

// Clientes del portal: cada uno con sus usuarios (entran con mail + código) y sus proyectos.
export function ClientsPage() {
  const clients = useClients()
  const isAdmin = useCan('monitoring.manage')
  const { create } = useClientMutations()
  const [name, setName] = useState('')
  const createError = create.error instanceof ApiError ? (create.error.field('name') ?? create.error.message) : null

  return (
    <div className="rise space-y-5">
      <h1 className="title">Clientes</h1>
      <p className="max-w-2xl text-sm text-muted">
        Cada cliente ve en el portal el estado de sus proyectos, con su logo y sus colores. Nunca ve servidores, IPs ni datos internos.
      </p>

      {isAdmin && (
        <Panel title="Nuevo cliente">
          <form
            className="flex flex-wrap items-end gap-3"
            onSubmit={(event) => {
              event.preventDefault()
              create.mutate(name.trim(), { onSuccess: () => setName('') })
            }}
          >
            <label className="min-w-[240px] flex-1">
              <span className="label mb-1.5 block">Nombre</span>
              <input className="input" value={name} onChange={(e) => setName(e.target.value)} placeholder="Playinn" aria-invalid={createError ? 'true' : 'false'} />
            </label>
            <button type="submit" className="btn btn-primary h-10" disabled={!name.trim() || create.isPending}>
              {create.isPending && <Spinner />}
              Crear cliente
            </button>
          </form>
          {createError && <div className="mt-3"><ErrorNote>{createError}</ErrorNote></div>}
        </Panel>
      )}

      {clients.isError ? (
        <ErrorNote onRetry={() => void clients.refetch()}>{errorMessage(clients.error)}</ErrorNote>
      ) : (
        <Panel title={clients.data ? `Clientes · ${clients.data.length}` : 'Clientes'} bodyClassName="">
          <DataTable<ClientRow>
            noun="clientes"
            rows={clients.data ?? []}
            loading={clients.isPending}
            rowKey={(row) => String(row.id)}
            rowClassName={(row) => (row.active ? '' : 'opacity-60')}
            empty="Todavía no hay clientes."
            columns={[
              { header: 'Cliente', cell: (row) => <Link to={`/monitoreo/clientes/${row.id}`} className="link font-medium">{row.name}</Link> },
              { header: 'Estado', cell: (row) => <Badge tone={row.active ? 'ok' : 'idle'}>{row.active ? 'Activo' : 'Inactivo'}</Badge> },
              { header: 'Usuarios', align: 'right', cell: (row) => <span className="num">{row.users}</span> },
              { header: 'Proyectos', align: 'right', cell: (row) => <span className="num">{row.projects}</span> },
              { header: 'Alta', cell: (row) => <span className="num text-xs text-muted">{formatDateTime(row.created_at)}</span> },
            ]}
          />
        </Panel>
      )}
    </div>
  )
}
