import type { Site, Sites } from '../../api/contract'
import { useSiteChecks } from '../../api/useMonitoring'
import type { SiteCheck } from '../../api/types'
import { DataTable } from '../../components/DataTable'
import { Panel } from '../../components/Panel'
import { Badge, YesNo } from '../../components/Status'
import { formatRelative } from '../../lib/format'
import { SiteProxies } from './SiteProxies'

export function SitesTab({ serverId, data }: { serverId: number; data: Sites }) {
  const checks = useSiteChecks(serverId)
  const sites = [...(data.items ?? [])].sort((a, b) => Number(a.root_exists) - Number(b.root_exists) || downUpstreams(b) - downUpstreams(a))
  const broken = sites.filter((site) => !site.root_exists).length
  const downTotal = sites.reduce((sum, site) => sum + downUpstreams(site), 0)

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center gap-2">
        <span className="flex h-8 items-center gap-2 rounded-full bg-surface px-3 text-footnote shadow-[var(--shadow)]">
          <span className="text-muted">Servidor web</span>
          <span className="font-medium">{data.web_server.name} {data.web_server.version}</span>
        </span>
        {data.web_server.config_ok !== undefined && (
          <span className="flex h-8 items-center gap-2 rounded-full bg-surface px-3 text-footnote shadow-[var(--shadow)]">
            <span className="text-muted">Config</span>
            <YesNo value={data.web_server.config_ok} yes="OK" no="Con errores" />
          </span>
        )}
        {broken > 0 && <Badge tone="crit">{broken} con root inexistente</Badge>}
        {downTotal > 0 && <Badge tone="crit">{downTotal} upstreams sin proceso (502)</Badge>}
      </div>

      <Panel title="Chequeo público (desde el panel)" bodyClassName="">
        <DataTable<SiteCheck>
          noun="chequeos"
          rows={checks.data ?? []}
          rowKey={(row) => row.url}
          loading={checks.isPending}
          empty="Todavía no hay chequeos HTTP."
          columns={[
            { header: 'URL', cell: (row) => <a href={row.url} target="_blank" rel="noreferrer" className="font-mono text-xs hover:underline">{row.url.replace('https://', '')}</a> },
            { header: 'HTTP', cell: (row) => <Badge tone={row.ok ? 'ok' : 'crit'}>{row.status_code ?? row.error ?? 'error'}</Badge> },
            { header: 'Latencia', align: 'right', cell: (row) => <span className="num">{row.latency_ms !== null ? `${row.latency_ms} ms` : '—'}</span> },
            { header: 'SSL', cell: (row) => <SslDays days={row.ssl_days_left} /> },
            { header: 'Chequeado', cell: (row) => <span className="num text-xs text-muted">{formatRelative(row.checked_at)}</span> },
          ]}
        />
      </Panel>

      <Panel title={`Vhosts · ${sites.length}`} bodyClassName="">
        <DataTable<Site>
          noun="vhosts"
          rows={sites}
          rowKey={(row, index) => `${row.file}-${index}`}
          rowClassName={(row) => (row.root_exists && downUpstreams(row) === 0 ? '' : 'bg-crit/5')}
          columns={[
            {
              header: 'Dominios',
              cell: (row) => (
                <div className="min-w-[200px] space-y-1">
                  <div className="num">{(row.server_names ?? []).join(', ')}</div>
                  {row.redirects && row.redirects.length > 0 && <div className="font-mono text-xs text-faint">301 ← {row.redirects.join(', ')}</div>}
                  {downUpstreams(row) > 0 && <Badge tone="crit">{downUpstreams(row)} de {row.proxies?.length} caídos</Badge>}
                </div>
              ),
            },
            { header: 'Escucha', cell: (row) => <span className="font-mono text-xs">{(row.listen ?? []).join(' ')}</span> },
            {
              header: 'Destino',
              cell: (row) => (
                <div className="min-w-[240px] max-w-[420px] space-y-1 font-mono text-xs">
                  {row.root && (
                    <div className={row.root_exists ? '' : 'text-crit'}>
                      root {row.root} {!row.root_exists && <Badge tone="crit">no existe</Badge>}
                    </div>
                  )}
                  {row.proxies && row.proxies.length > 0 && <SiteProxies proxies={row.proxies} />}
                  {row.fastcgi && <div className="text-faint">php → {row.fastcgi}</div>}
                  {(row.missing_paths ?? []).map((path) => (
                    <div key={path} className="text-crit">falta {path}</div>
                  ))}
                </div>
              ),
            },
            { header: 'SSL origen', cell: (row) => <span className="num text-xs text-muted">{row.ssl_certificate ? formatRelative(row.ssl_expires_at) : 'no (Cloudflare)'}</span> },
          ]}
        />
      </Panel>
    </div>
  )
}

function SslDays({ days }: { days: number | null }) {
  if (days === null) return <span className="text-faint">—</span>
  const tone = days < 7 ? 'crit' : days < 14 ? 'warn' : 'ok'
  return <Badge tone={tone}>{days} días</Badge>
}

// Upstreams locales sin proceso escuchando: nginx responde 502 en esas locations.
function downUpstreams(site: Site): number {
  return (site.proxies ?? []).filter((proxy) => proxy.listening === false).length
}
