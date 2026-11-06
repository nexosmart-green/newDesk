import type { Cron, CronEntry, Timer } from '../../api/contract'
import { DataTable } from '../../components/DataTable'
import { Panel } from '../../components/Panel'
import { Badge } from '../../components/Status'
import { formatDateTime, formatRelative } from '../../lib/format'

export function CronTab({ data }: { data: Cron }) {
  const timers = [...(data.timers ?? [])].sort((a, b) => Number(b.custom) - Number(a.custom) || a.unit.localeCompare(b.unit))
  return (
    <div className="space-y-5">
      <Panel title={`Crons · ${data.entries?.length ?? 0}`} bodyClassName="">
        <DataTable<CronEntry>
          noun="crons"
          rows={data.entries ?? []}
          rowKey={(row, index) => `${row.source}-${index}`}
          empty="No hay crons configurados."
          columns={[
            { header: 'Origen', cell: (row) => <span className="font-mono text-xs whitespace-nowrap text-muted">{row.source}</span> },
            { header: 'Cuándo', cell: (row) => <code className="text-xs font-medium whitespace-nowrap">{row.schedule}</code> },
            { header: 'Comando', cell: (row) => <code className="block max-w-[640px] text-xs break-all">{row.command}</code> },
          ]}
        />
      </Panel>
      <Panel title={`Timers de systemd · ${timers.length}`} bodyClassName="">
        <DataTable<Timer>
          noun="timers"
          rows={timers}
          rowKey={(row) => row.unit}
          empty="Sin timers."
          rowClassName={(row) => (row.result && row.result !== 'success' ? 'bg-crit/5' : '')}
          columns={[
            {
              header: 'Timer',
              cell: (row) => (
                <span className="num">
                  {row.unit}
                  {row.custom && <span className="tag ml-2">propio</span>}
                </span>
              ),
            },
            {
              header: 'Último resultado',
              cell: (row) =>
                row.result ? (
                  <Badge tone={row.result === 'success' ? 'ok' : 'crit'}>{row.result === 'success' ? 'OK' : `${row.result} (${row.exit_status})`}</Badge>
                ) : (
                  <span className="text-faint">—</span>
                ),
            },
            { header: 'Última', cell: (row) => <span className="num text-xs text-muted" title={formatDateTime(row.last)}>{row.last ? formatRelative(row.last) : 'nunca'}</span> },
            { header: 'Próxima', cell: (row) => <span className="num text-xs text-muted" title={formatDateTime(row.next)}>{row.next ? formatRelative(row.next) : '—'}</span> },
            { header: 'Activo', cell: (row) => <Badge tone={row.active ? 'ok' : 'idle'}>{row.active ? 'Sí' : 'No'}</Badge> },
          ]}
        />
      </Panel>
    </div>
  )
}
