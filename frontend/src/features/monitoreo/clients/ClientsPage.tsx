import { Link } from 'react-router'
import { errorMessage } from '../api/monitoringApi'
import { useClients } from '../api/usePortalAdmin'
import type { ClientRow } from '../api/types'
import { DataTable } from '../components/DataTable'
import { ErrorNote, Panel } from '../components/Panel'
import { Badge } from '../components/Status'
import { formatDateTime } from '../lib/format'

// Clientes del portal: cada uno con sus usuarios (entran con mail + código) y sus proyectos.
export function ClientsPage() {
  const clients = useClients()

  return (
    <div className="rise space-y-5">
      <h1 className="title">Clientes</h1>
      <p className="max-w-2xl text-sm text-muted">
        Cada cliente ve en el portal el estado de sus proyectos, con su logo y sus colores. Nunca ve servidores, IPs ni datos internos.
        {' '}Los clientes se dan de alta y se editan en <Link to="/clientes" className="link">Clientes</Link>.
      </p>

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
