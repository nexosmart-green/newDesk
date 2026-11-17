import type { VulnCounts, VulnSeverity } from '../api/contract'
import { errorMessage } from '../api/monitoringApi'
import { useProjectVulnerabilities } from '../api/usePortalAdmin'
import type { ProjectAdmin, VulnDirView } from '../api/types'
import { Empty, ErrorNote, Panel, Spinner } from '../components/Panel'
import { Badge } from '../components/Status'
import { formatDateTime, formatRelative } from '../lib/format'
import type { Tone } from '../lib/status'
import { vulnTotal } from '../lib/vulns'

const vulnSeverityMeta: Record<VulnSeverity, { label: string; tone: Tone }> = {
  critical: { label: 'Crítica', tone: 'crit' },
  high: { label: 'Alta', tone: 'crit' },
  moderate: { label: 'Media', tone: 'warn' },
  low: { label: 'Baja', tone: 'info' },
}

const SEVERITIES: VulnSeverity[] = ['critical', 'high', 'moderate', 'low']

// Chips con la cantidad por severidad; las que están en cero no se muestran.
export function VulnCountBadges({ counts }: { counts: VulnCounts }) {
  return (
    <span className="flex flex-wrap gap-1.5">
      {SEVERITIES.filter((severity) => counts[severity] > 0).map((severity) => (
        <Badge key={severity} tone={vulnSeverityMeta[severity].tone}>
          {counts[severity]} {vulnSeverityMeta[severity].label.toLowerCase()}
        </Badge>
      ))}
    </span>
  )
}

// ---------- vulnerabilidades de dependencias (D-053) ----------

// Lo mismo que manda por mail el security-scan.sh de cada server, por carpeta del proyecto.
export function VulnerabilitiesPanel({ project }: { project: ProjectAdmin }) {
  const vulns = useProjectVulnerabilities(project.id)

  if (vulns.isPending) return <Panel title="Vulnerabilidades de dependencias"><Spinner label="Cargando…" /></Panel>
  if (vulns.isError) {
    return (
      <Panel title="Vulnerabilidades de dependencias">
        <ErrorNote onRetry={() => void vulns.refetch()}>{errorMessage(vulns.error)}</ErrorNote>
      </Panel>
    )
  }
  const { counts, servers, dirs } = vulns.data
  const silent = servers.filter((server) => !server.has_scan)
  const lastScan = servers.map((server) => server.scanned_at).filter(Boolean).sort().at(-1) ?? null

  return (
    <Panel
      title="Vulnerabilidades de dependencias"
      actions={
        <>
          {vulnTotal(counts) > 0 && <VulnCountBadges counts={counts} />}
          {lastScan && <span className="text-xs text-muted" title={formatDateTime(lastScan)}>scan {formatRelative(lastScan)}</span>}
        </>
      }
      bodyClassName=""
    >
      {servers.length === 0 ? (
        <Empty>Vinculá las carpetas del proyecto para ver sus vulnerabilidades.</Empty>
      ) : (
        <>
          {silent.length > 0 && (
            <p className="px-5 pb-3 text-sm text-muted">
              {silent.map((server) => server.server_name).join(', ')}: este server no manda resultados de security-scan.sh.
            </p>
          )}
          {dirs.length === 0 ? (
            silent.length < servers.length && <Empty icon="check">El último scan no encontró vulnerabilidades en las carpetas del proyecto.</Empty>
          ) : (
            <ul className="divide-y divide-line border-t border-line">
              {dirs.map((dir) => <VulnDirRow key={`${dir.server_id}:${dir.path}:${dir.manager}`} dir={dir} />)}
            </ul>
          )}
        </>
      )}
    </Panel>
  )
}

// El link del advisory llega del audit de npm o composer en el server: solo https va como link (H18).
function httpsUrl(url: string | undefined): boolean {
  try {
    return url !== undefined && new URL(url).protocol === 'https:'
  } catch {
    return false
  }
}

function VulnDirRow({ dir }: { dir: VulnDirView }) {
  const header = (
    <span className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
      <span className="truncate font-mono text-xs">{dir.path}</span>
      <span className="tag">{dir.manager}</span>
      {dir.report_only && <span className="tag">solo reporte</span>}
      <span className="text-xs text-muted">{dir.server_name}</span>
    </span>
  )
  if (dir.error) {
    return (
      <li className="space-y-1 px-5 py-3">
        <div className="flex items-center gap-2">{header}<Badge tone="warn">la auditoría falló</Badge></div>
        <p className="font-mono text-xs text-muted">{dir.error}</p>
      </li>
    )
  }
  if (dir.findings.length === 0) {
    return (
      <li className="flex items-center gap-2 px-5 py-3">{header}<Badge tone="ok">limpia</Badge></li>
    )
  }
  return (
    <li>
      <details className="group">
        <summary className="flex cursor-pointer list-none items-center gap-2 px-5 py-3 hover:bg-surface-2">
          {header}
          <VulnCountBadges counts={dir.counts} />
        </summary>
        <table className="table mb-3">
          <thead>
            <tr><th>Paquete</th><th>Severidad</th><th>Problema</th><th>Fix</th></tr>
          </thead>
          <tbody>
            {dir.findings.map((finding, index) => (
              <tr key={`${finding.package}:${index}`}>
                <td className="font-mono text-xs">{finding.package}</td>
                <td><Badge tone={vulnSeverityMeta[finding.severity].tone}>{vulnSeverityMeta[finding.severity].label}</Badge></td>
                <td className="text-sm">
                  {httpsUrl(finding.url) ? <a href={finding.url} target="_blank" rel="noreferrer" className="link">{finding.title}</a> : finding.title}
                  {finding.cve && <span className="ml-1.5 font-mono text-xs text-muted">{finding.cve}</span>}
                </td>
                <td className="text-sm text-muted">{dir.manager === 'npm' ? (finding.fix_available ? 'sí' : 'no') : '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </details>
    </li>
  )
}
