import { Link, useNavigate, useParams } from 'react-router'
import { errorMessage } from '../api/monitoringApi'
import { useServer } from '../api/useMonitoring'
import type { ServerDetail } from '../api/types'
import { CopyButton } from '../components/CopyButton'
import { Icon } from '../components/Icon'
import { ErrorNote, LoadingStatus } from '../components/Panel'
import { Badge, ServerStatusBadge } from '../components/Status'
import { Tabs } from '../components/Tabs'
import { formatRelative, hostPort } from '../lib/format'
import { useLiveMode } from '../realtime/useLiveMode'
import { renderTab, tabDefs, type TabId } from './tabs'

function tabBadges(detail: ServerDetail): Partial<Record<TabId, number>> {
  const { sections } = detail
  const pm2Bad = (sections.pm2?.data?.daemons ?? []).flatMap((d) => d.processes ?? []).filter((p) => p.status !== 'online').length
  const supervisorBad = (sections.supervisor?.data?.programs ?? []).filter((p) => !p.healthy).length
  const findings = (sections.security?.data?.findings ?? []).filter((f) => f.severity === 'critical' || f.severity === 'high').length
  return {
    procesos: pm2Bad + supervisorBad,
    servicios: (sections.services?.data?.items ?? []).filter((s) => !s.healthy).length,
    sitios: (sections.sites?.data?.items ?? []).filter((s) => !s.root_exists).length,
    seguridad: findings,
  }
}

export function ServerPage() {
  const params = useParams()
  const navigate = useNavigate()
  const id = Number(params.id)
  const tab = (tabDefs.some((t) => t.id === params.tab) ? params.tab : 'resumen') as TabId
  const detail = useServer(id)
  useLiveMode(id)
  const badges = detail.data ? tabBadges(detail.data) : {}

  if (!Number.isFinite(id)) return <ErrorNote>Servidor inválido.</ErrorNote>

  return (
    <div className="rise space-y-5">
      <Link to="/monitoreo" className="inline-flex items-center gap-1 text-footnote text-muted transition-colors hover:text-text">
        <Icon name="chevron" size={12} className="rotate-180" /> Servidores
      </Link>
      {detail.data && <ServerHeader detail={detail.data} />}
      {detail.isPending && <HeaderSkeleton />}
      {detail.isError && <ErrorNote onRetry={() => void detail.refetch()}>{errorMessage(detail.error)}</ErrorNote>}

      <div className="glass sticky top-13 z-10 -mx-5 border-b border-line px-5 py-2 sm:-mx-8 sm:px-8 lg:top-0 lg:-mx-10 lg:px-10">
        <Tabs
          label="Secciones del servidor"
          tabs={tabDefs.map((t) => ({ ...t, badge: badges[t.id] }))}
          active={tab}
          onChange={(next) => navigate(`/monitoreo/servers/${id}/${next === 'resumen' ? '' : next}`)}
        />
      </div>
      {detail.data && (
        <div role="tabpanel" aria-labelledby={`tab-${tab}`} key={`${id}-${tab}`} className="fade-in">
          {renderTab(tab, detail.data)}
        </div>
      )}
      {detail.isPending && <BodySkeleton />}
    </div>
  )
}

function ServerHeader({ detail }: { detail: ServerDetail }) {
  const { server } = detail
  const system = detail.sections.system?.data
  return (
    <header className="fade-in flex flex-wrap items-start justify-between gap-4">
      <div className="min-w-0">
        <div className="flex flex-wrap items-center gap-3">
          <h1 className="title">{server.name}</h1>
          <ServerStatusBadge status={server.status} />
        </div>
        <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-footnote text-muted">
          <CopyButton variant="chip" text={hostPort(server.ip, server.ssh_port ?? system?.ssh_port)} title="Copiar IP:puerto SSH" />
          {server.hostname && <span className="font-mono text-xs">{server.hostname}</span>}
          {server.os && <span>{server.os}</span>}
          {system?.os.eol && <Badge tone="crit">SO sin soporte</Badge>}
          <span className="font-mono text-xs text-faint">
            agente :{server.agent_port}
            {server.agent_version ? ` · ${server.agent_version}` : ''}
          </span>
        </div>
      </div>
      <div className="sm:text-right">
        <div className="label">Último dato</div>
        <div className="num mt-0.5 text-sm">{formatRelative(server.last_seen_at)}</div>
      </div>
    </header>
  )
}

function HeaderSkeleton() {
  return (
    <div className="delay-in flex flex-wrap items-start justify-between gap-4">
      <LoadingStatus label="Cargando servidor…" />
      <div className="space-y-3" aria-hidden="true">
        <div className="flex items-center gap-3">
          <span className="skeleton h-8 w-48" />
          <span className="skeleton h-5 w-20 rounded-full" />
        </div>
        <span className="skeleton h-6 w-72 rounded-md" />
      </div>
      <div className="space-y-2" aria-hidden="true">
        <span className="skeleton h-3 w-16" />
        <span className="skeleton h-4 w-20" />
      </div>
    </div>
  )
}

function BodySkeleton() {
  return (
    <div className="delay-in space-y-5" aria-hidden="true">
      <div className="panel space-y-5 p-5">
        <span className="skeleton h-4 w-16" />
        <div className="grid gap-6 sm:grid-cols-3">
          {[0, 1, 2].map((index) => (
            <div key={index} className="space-y-2">
              <span className="skeleton h-3.5 w-20" />
              <span className="skeleton h-1.5 rounded-full" />
            </div>
          ))}
        </div>
      </div>
      <div className="panel space-y-4 p-5">
        <span className="skeleton h-4 w-20" />
        <span className="skeleton h-[200px] rounded-xl" />
      </div>
    </div>
  )
}
