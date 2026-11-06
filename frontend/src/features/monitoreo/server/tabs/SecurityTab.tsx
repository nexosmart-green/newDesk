import type { CSSProperties } from 'react'
import type { AuthCounts, AuthEvent, Counter, Finding, Security, Severity } from '../../api/contract'
import { failedTotal } from '../../api/contract'
import { DataTable, Pagination } from '../../components/DataTable'
import { Empty, Panel } from '../../components/Panel'
import { Badge } from '../../components/Status'
import type { Tone } from '../../lib/status'
import { formatDateTime, formatNumber, formatRelative } from '../../lib/format'
import { usePage } from '../../lib/paginate'

const severityMeta: Record<Severity, { label: string; tone: Tone; order: number }> = {
  critical: { label: 'Crítico', tone: 'crit', order: 0 },
  high: { label: 'Alto', tone: 'crit', order: 1 },
  medium: { label: 'Medio', tone: 'warn', order: 2 },
  low: { label: 'Bajo', tone: 'info', order: 3 },
  info: { label: 'Info', tone: 'idle', order: 4 },
}

const countLabels: [keyof AuthCounts, string][] = [
  ['invalid_user', 'Usuario inexistente'],
  ['failed_password', 'Contraseña incorrecta'],
  ['preauth_closed', 'Cortadas antes de auth'],
  ['banner_exchange', 'Handshake inválido'],
  ['max_auth_attempts', 'Máximo de intentos'],
  ['phpmyadmin_denied', 'phpMyAdmin denegado'],
]

const kindLabels: Record<string, string> = {
  invalid_user: 'usuario inexistente', failed_password: 'contraseña incorrecta', preauth_closed: 'cortada pre-auth',
  banner_exchange: 'handshake inválido', phpmyadmin_denied: 'phpMyAdmin denegado', max_auth_attempts: 'máximo de intentos',
  accepted: 'ingreso OK',
}

export function SecurityTab({ data }: { data: Security }) {
  const logins = data.failed_logins
  const findings = [...(data.findings ?? [])].sort((a, b) => severityMeta[a.severity].order - severityMeta[b.severity].order)

  return (
    <div className="space-y-5">
      <Panel title={`Hallazgos · ${findings.length}`} bodyClassName="">
        {findings.length === 0 ? <Empty>Sin hallazgos. Bien ahí.</Empty> : <FindingsList findings={findings} />}
      </Panel>

      <Panel title="Intentos de ingreso fallidos" actions={<span className="num text-xs text-muted">fuente: {logins.source}</span>}>
        <div className="grid gap-5 lg:grid-cols-[1.2fr_1fr_1fr]">
          <table className="table">
            <thead>
              <tr><th>Tipo</th><th className="text-right">24 h</th><th className="text-right">7 d</th></tr>
            </thead>
            <tbody>
              {countLabels.map(([key, label]) => (
                <tr key={key}>
                  <td className="text-sm">{label}</td>
                  <td className="num text-right">{formatNumber(logins.window_24h[key])}</td>
                  <td className="num text-right">{formatNumber(logins.window_7d[key])}</td>
                </tr>
              ))}
              <tr className="font-medium">
                <td className="text-sm">Total fallidos</td>
                <td className="num text-right text-crit">{formatNumber(failedTotal(logins.window_24h))}</td>
                <td className="num text-right text-crit">{formatNumber(failedTotal(logins.window_7d))}</td>
              </tr>
              <tr>
                <td className="text-sm text-muted">Ingresos correctos</td>
                <td className="num text-right text-ok">{logins.window_24h.accepted}</td>
                <td className="num text-right text-ok">{logins.window_7d.accepted}</td>
              </tr>
            </tbody>
          </table>
          <TopList title={`Top IPs · ${formatNumber(logins.distinct_ips_7d)} distintas en 7 d`} items={logins.top_ips ?? []} />
          <TopList title="Usuarios probados" items={logins.top_users ?? []} />
        </div>
        {/* La fuente es un archivo cuando journald no se puede leer (NV-12). */}
        {logins.source.startsWith('/') && (
          <p className="mt-4 text-xs text-muted">
            Cualquier usuario del server puede escribir en {logins.source}: estos números no se pueden verificar.
          </p>
        )}
      </Panel>

      <div className="grid gap-5 lg:grid-cols-2">
        <Panel title="Fail2ban" bodyClassName="">
          {!data.fail2ban ? (
            <Empty>Fail2ban no está instalado.</Empty>
          ) : (
            <DataTable
              noun="jails"
              rows={data.fail2ban.jails ?? []}
              rowKey={(row) => row.name}
              empty={data.fail2ban.active ? 'Sin jails.' : 'Fail2ban está inactivo.'}
              columns={[
                { header: 'Jail', cell: (row) => <span className="num">{row.name}</span> },
                { header: 'Baneadas ahora', align: 'right', cell: (row) => <span className="num text-crit">{row.currently_banned}</span> },
                { header: 'Baneos totales', align: 'right', cell: (row) => <span className="num">{formatNumber(row.total_banned)}</span> },
                { header: 'Fallos totales', align: 'right', cell: (row) => <span className="num">{formatNumber(row.total_failed)}</span> },
              ]}
            />
          )}
        </Panel>
        <Panel title="Firewall" actions={<Badge tone={data.firewall.active ? 'ok' : 'crit'}>{data.firewall.active ? 'Activo' : 'Inactivo'}</Badge>}>
          <div className="space-y-1 text-sm">
            <div>
              {data.firewall.tool} {data.firewall.default_incoming && <span className="text-muted">· entrada por defecto: {data.firewall.default_incoming}</span>}
            </div>
            {(data.firewall.rules ?? []).map((rule) => (
              <div key={rule} className="font-mono text-xs text-muted">{rule}</div>
            ))}
          </div>
        </Panel>
      </div>

      <Panel title="Últimos eventos" bodyClassName="">
        <DataTable<AuthEvent>
          noun="eventos"
          rows={logins.recent ?? []}
          rowKey={(row, index) => `${row.time}-${index}`}
          empty="Sin eventos recientes."
          columns={[
            { header: 'Cuándo', cell: (row) => <span className="num whitespace-nowrap text-xs" title={formatDateTime(row.time)}>{formatRelative(row.time)}</span> },
            {
              header: 'Tipo',
              cell: (row) => (
                <span className="inline-flex flex-wrap gap-1.5">
                  <Badge tone={row.kind === 'accepted' ? 'ok' : 'crit'}>{kindLabels[row.kind] ?? row.kind}</Badge>
                  {row.unverified && <Badge tone="idle">no verificado</Badge>}
                </span>
              ),
            },
            { header: 'Servicio', cell: (row) => <span className="font-mono text-xs">{row.service}</span> },
            { header: 'Usuario', cell: (row) => <span className="font-mono text-xs">{row.user ?? '—'}</span> },
            { header: 'IP', cell: (row) => <span className="font-mono text-xs">{row.ip ?? '—'}</span> },
          ]}
        />
      </Panel>
    </div>
  )
}

function FindingsList({ findings }: { findings: Finding[] }) {
  const page = usePage(findings)
  return (
    <>
    <ul key={page.page} className={`space-y-3 px-5 pb-5 ${page.pages > 1 ? 'fade-in' : ''}`}>
      {page.rows.map((finding) => {
        const meta = severityMeta[finding.severity]
        return (
          <li key={finding.id} className="rounded-xl bg-surface-2 p-4">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone={meta.tone}>{meta.label}</Badge>
              <span className="font-medium">{finding.title}</span>
            </div>
            <div className="mt-2 font-mono text-xs break-all text-muted">{finding.evidence}</div>
            <div className="mt-1.5 text-sm">
              <span className="mr-1.5 font-semibold text-text">Qué hacer</span>
              {finding.recommendation}
            </div>
          </li>
        )
      })}
    </ul>
    <Pagination page={page.page} pages={page.pages} total={page.total} noun="hallazgos" onChange={page.setPage} />
    </>
  )
}

function TopList({ title, items }: { title: string; items: Counter[] }) {
  const max = items[0]?.count ?? 0
  return (
    <div>
      <div className="label mb-2">{title}</div>
      {items.length === 0 ? (
        <p className="text-sm text-muted">—</p>
      ) : (
        <ol className="space-y-1.5">
          {items.map((item) => (
            <li key={item.key} className="text-sm">
              <div className="flex justify-between gap-2">
                <span className="truncate font-mono text-xs">{item.key}</span>
                <span className="num text-muted">{formatNumber(item.count)}</span>
              </div>
              <span className="mt-1 block h-1 overflow-hidden rounded-full bg-surface-3">
                <span className="bar block bg-crit/80" style={{ '--v': `${max ? (item.count / max) * 100 : 0}%` } as CSSProperties} />
              </span>
            </li>
          ))}
        </ol>
      )}
    </div>
  )
}
