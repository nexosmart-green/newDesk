import type { Service, Services } from '../../api/contract'
import { DataTable } from '../../components/DataTable'
import { Panel } from '../../components/Panel'
import { Badge } from '../../components/Status'
import { formatRelative } from '../../lib/format'

export function ServicesTab({ data }: { data: Services }) {
  const items = [...(data.items ?? [])].sort((a, b) => Number(a.healthy) - Number(b.healthy) || Number(b.custom) - Number(a.custom) || a.name.localeCompare(b.name))
  return (
    <Panel title={`Servicios · ${items.length}`} bodyClassName="">
      <DataTable<Service>
        noun="servicios"
        rows={items}
        rowKey={(row) => row.name}
        rowClassName={(row) => (row.healthy ? '' : 'bg-crit/5')}
        columns={[
          {
            header: 'Servicio',
            cell: (row) => (
              <span className="num">
                {row.name}
                {row.custom && <span className="tag ml-2">propio</span>}
              </span>
            ),
          },
          { header: 'Salud', cell: (row) => <Badge tone={row.healthy ? 'ok' : 'crit'}>{row.healthy ? 'OK' : 'Falla'}</Badge> },
          { header: 'Estado', cell: (row) => <span className="num text-xs">{row.active} ({row.sub_state})</span> },
          { header: 'Arranque', cell: (row) => <span className="num text-xs text-muted">{row.enabled}</span> },
          { header: 'Tipo', cell: (row) => <span className="num text-xs text-muted">{row.type}</span> },
          { header: 'Desde', cell: (row) => <span className="num text-xs text-muted">{row.since ? formatRelative(row.since) : '—'}</span> },
          { header: 'Detalle', cell: (row) => <span className="text-xs text-muted">{row.detail ?? ''}</span> },
        ]}
      />
    </Panel>
  )
}
