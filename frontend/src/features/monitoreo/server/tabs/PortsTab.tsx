import type { Port, Ports } from '../../api/contract'
import { DataTable } from '../../components/DataTable'
import { Panel } from '../../components/Panel'
import { Badge } from '../../components/Status'

export function PortsTab({ data }: { data: Ports }) {
  const items = [...(data.items ?? [])].sort((a, b) => a.port - b.port || a.proto.localeCompare(b.proto))
  return (
    <Panel title={`Puertos en escucha · ${items.length}`} bodyClassName="">
      <DataTable<Port>
        noun="puertos"
        rows={items}
        rowKey={(row) => `${row.proto}-${row.address}-${row.port}`}
        columns={[
          { header: 'Puerto', cell: (row) => <span className="num font-semibold">{row.port}</span> },
          { header: 'Proto', cell: (row) => <span className="text-xs text-muted uppercase">{row.proto}</span> },
          { header: 'Dirección', cell: (row) => <span className="font-mono text-xs">{row.address}</span> },
          {
            header: 'Exposición',
            cell: (row) => (row.all_interfaces ? <Badge tone="warn">todas las interfaces</Badge> : <Badge tone="ok">local</Badge>),
          },
          { header: 'Proceso', cell: (row) => <span className="text-sm">{row.process ?? '—'}{row.pid ? <span className="text-faint"> ({row.pid})</span> : null}</span> },
          { header: 'Usuario', cell: (row) => <span className="num text-xs text-muted">{row.user ?? '—'}</span> },
        ]}
      />
    </Panel>
  )
}
