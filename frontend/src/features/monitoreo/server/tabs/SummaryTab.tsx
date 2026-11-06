import { useState } from 'react'
import { useMetricsSeries } from '../../api/useMonitoring'
import type { Metrics, Process, System } from '../../api/contract'
import type { MetricsRange, ServerDetail } from '../../api/types'
import { Meter } from '../../components/Meter'
import { Empty, LoadingStatus, Panel, Spinner, Stat, TableWrap } from '../../components/Panel'
import { Segmented } from '../../components/Tabs'
import { formatBytes, formatDuration, formatPercent, formatRate, formatRelative, sshPortsLabel } from '../../lib/format'
import { ChartSkeleton, MetricsChart, type SeriesDef } from '../MetricsChart'

const ranges: { value: MetricsRange; label: string }[] = [
  { value: '1h', label: '1 h' }, { value: '24h', label: '24 h' }, { value: '7d', label: '7 d' }, { value: '30d', label: '30 d' }, { value: '1y', label: '1 año' },
]
const sortOptions = [{ value: 'cpu', label: 'CPU' }, { value: 'memory', label: 'Memoria' }] as const
const cpuSeries: SeriesDef[] = [{ key: 'cpu', label: 'CPU' }, { key: 'cpu_max', label: 'CPU máx' }]
const memSeries: SeriesDef[] = [{ key: 'memory', label: 'RAM' }, { key: 'swap', label: 'Swap' }]
const diskSeries: SeriesDef[] = [{ key: 'disk', label: 'Disco' }]
const netSeries: SeriesDef[] = [{ key: 'rx_bps', label: 'Entrada' }, { key: 'tx_bps', label: 'Salida' }]

export function SummaryTab({ detail }: { detail: ServerDetail }) {
  const full = detail.sections.metrics?.data ?? undefined
  const summary = detail.server.metrics
  const system = detail.sections.system?.data

  return (
    <div className="space-y-5">
      <LivePanel full={full} fallback={summary} collectedAt={detail.sections.metrics?.collected_at ?? summary?.collected_at} />
      <ChartsPanel serverId={detail.server.id} />
      <div className="grid gap-5 xl:grid-cols-[1fr_1.4fr]">
        {system ? (
          <SystemPanel system={system} />
        ) : (
          <Panel title="Sistema">
            <Empty>Sin datos del sistema.</Empty>
          </Panel>
        )}
        <TopProcesses metrics={full} />
      </div>
    </div>
  )
}

function LivePanel({ full, fallback, collectedAt }: { full?: Metrics; fallback: ServerDetail['server']['metrics']; collectedAt?: string }) {
  const cpu = full?.cpu.usage_percent ?? fallback?.cpu_percent
  const mem = full?.memory.used_percent ?? fallback?.memory_percent
  const swap = full?.swap.used_percent ?? fallback?.swap_percent
  const net = full?.network ?? []
  return (
    <Panel title="En vivo" actions={<span className="num text-xs text-muted">{formatRelative(collectedAt)}</span>}>
      <div className="grid gap-8 lg:grid-cols-[1.3fr_1fr]">
        <div className="grid gap-x-6 gap-y-5 sm:grid-cols-3">
          <Meter label="CPU" percent={cpu} detail={full ? `${full.cpu.cores} núcleos` : undefined} />
          <Meter label="RAM" percent={mem} detail={full ? `${formatBytes(full.memory.used_bytes)} / ${formatBytes(full.memory.total_bytes)}` : undefined} />
          <Meter label="Swap" percent={swap} warn={60} crit={85} detail={full ? `${formatBytes(full.swap.used_bytes)} / ${formatBytes(full.swap.total_bytes)}` : undefined} />
          {(full?.disks ?? []).map((disk) => (
            <Meter
              key={disk.mount}
              label={`Disco ${disk.mount}`}
              percent={disk.used_percent}
              warn={85}
              crit={95}
              detail={`${formatBytes(disk.used_bytes)} / ${formatBytes(disk.total_bytes)} · inodos ${formatPercent(disk.inodes_used_percent)}`}
            />
          ))}
        </div>
        <div className="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-2">
          <Stat label="Load 1/5/15" value={full ? `${full.cpu.load1.toFixed(2)} ${full.cpu.load5.toFixed(2)} ${full.cpu.load15.toFixed(2)}` : (fallback?.load1.toFixed(2) ?? '—')} />
          <Stat label="Uptime" value={formatDuration(full?.uptime_seconds ?? fallback?.uptime_seconds)} />
          {net.map((iface) => (
            <Stat key={iface.name} label={`Red ${iface.name}`} value={`↓ ${formatRate(iface.rx_bytes_per_sec)}`} hint={`↑ ${formatRate(iface.tx_bytes_per_sec)}`} />
          ))}
        </div>
      </div>
    </Panel>
  )
}

function ChartsPanel({ serverId }: { serverId: number }) {
  const [range, setRange] = useState<MetricsRange>('24h')
  const series = useMetricsSeries(serverId, range)
  const points = series.data?.points ?? []

  let body
  if (series.isError) {
    body = <Empty>No se pudo cargar el historial.</Empty>
  } else if (series.isPending) {
    body = (
      <div className="delay-in grid gap-6 md:grid-cols-2">
        <LoadingStatus label="Cargando gráficos…" />
        {[0, 1, 2, 3].map((index) => (
          <ChartSkeleton key={index} />
        ))}
      </div>
    )
  } else if (points.length === 0) {
    body = <Empty>Sin datos en este rango.</Empty>
  } else {
    // Solo se atenúa al cambiar de rango; el refresco periódico no parpadea.
    body = (
      <div className={`grid gap-6 transition-opacity duration-[var(--dur-2)] md:grid-cols-2 ${series.isPlaceholderData ? 'opacity-50' : ''}`} aria-busy={series.isFetching || undefined}>
        <MetricsChart title="CPU" points={points} series={series.data?.resolution === 'hourly' ? cpuSeries : cpuSeries.slice(0, 1)} unit="percent" />
        <MetricsChart title="Memoria" points={points} series={memSeries} unit="percent" />
        <MetricsChart title="Disco (el más lleno)" points={points} series={diskSeries} unit="percent" />
        <MetricsChart title="Red" points={points} series={netSeries} unit="rate" />
      </div>
    )
  }

  return (
    <Panel
      title="Historial"
      actions={
        <>
          {series.isPlaceholderData && <Spinner className="text-muted" />}
          <Segmented label="Rango" options={ranges} value={range} onChange={setRange} />
        </>
      }
    >
      {body}
    </Panel>
  )
}

function SystemPanel({ system }: { system: System }) {
  const rows: [string, string, string?][] = [
    ['Hostname', system.hostname, 'font-mono text-xs'],
    ['Sistema', `${system.os.pretty_name}${system.os.eol ? ` — EOL ${system.os.eol_date ?? ''}` : ''}`, system.os.eol ? 'text-crit' : undefined],
    ['Kernel', system.kernel, 'font-mono text-xs'],
    ['CPU', `${system.cpu_model} · ${system.cpu_count} vCPU`],
    ['Memoria', formatBytes(system.memory_total_bytes)],
    ['Virtualización', system.virtualization || '—'],
    ['Init', `${system.init.name} ${system.init.version}`],
    ['Zona horaria', `${system.timezone}${system.ntp_synchronized === false ? ' (NTP sin sincronizar)' : ''}`],
    ['Puerto SSH', sshPortsLabel(system)],
    ['IPs', (system.ips ?? []).map((ip) => `${ip.address} (${ip.interface})`).join(', '), 'font-mono text-xs'],
  ]
  return (
    <Panel title="Sistema">
      <dl className="divide-y divide-line text-sm">
        {rows.map(([label, value, className]) => (
          <div key={label} className="grid grid-cols-[8.5rem_1fr] gap-4 py-2.5 first:pt-0 last:pb-0">
            <dt className="text-muted">{label}</dt>
            <dd className={`num min-w-0 break-words ${className ?? ''}`}>{value}</dd>
          </div>
        ))}
      </dl>
    </Panel>
  )
}

function TopProcesses({ metrics }: { metrics?: Metrics }) {
  const [by, setBy] = useState<'cpu' | 'memory'>('cpu')
  const list: Process[] = (by === 'cpu' ? metrics?.top_by_cpu : metrics?.top_by_memory) ?? []
  return (
    <Panel title="Procesos top" bodyClassName="" actions={<Segmented label="Ordenar por" options={sortOptions} value={by} onChange={setBy} />}>
      {list.length === 0 ? (
        <Empty>Sin datos de procesos.</Empty>
      ) : (
        <TableWrap>
          <table key={by} className="table fade-in">
            <thead>
              <tr>
                <th>PID</th>
                <th>Usuario</th>
                <th>Comando</th>
                <th className="text-right">CPU</th>
                <th className="text-right">Memoria</th>
              </tr>
            </thead>
            <tbody>
              {list.map((p) => (
                <tr key={p.pid}>
                  <td className="num text-muted">{p.pid}</td>
                  <td>{p.user}</td>
                  <td className="max-w-[360px] truncate font-mono text-xs" title={p.command}>
                    {p.command || p.name}
                  </td>
                  <td className="num text-right">{formatPercent(p.cpu_percent)}</td>
                  <td className="num text-right whitespace-nowrap">{formatBytes(p.memory_bytes)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </TableWrap>
      )}
    </Panel>
  )
}
