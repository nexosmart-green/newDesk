import type { ReactNode } from 'react'
import { apiUrl } from '../api/monitoringApi'
import type { PortalState, PortalStatus } from '../api/types'
import { Icon } from '../components/Icon'
import { brandStyle } from '../lib/brand'
import { formatRelative } from '../lib/format'
import { portalStateMeta, toneColor, type Tone } from '../lib/status'
import { PageSpeedPanel } from './PageSpeedPanel'
import { IncidentsPanel, ResponseTimesPanel } from './PortalHistory'
import { BackupCard, ComponentList, DomainCard, LastUpdateCard, SslCard, UptimeBars } from './StatusCards'

// Página de estado de un proyecto: la ve el cliente y, igual, el equipo en la vista previa.
// La marca (color y logo) pisa el acento; los colores de estado son siempre los mismos.
// En la vista previa del equipo el nombre va como h2: la página ya tiene su h1. `actions` va al lado del
// nombre (el cliente ve ahí "Reportar un problema"; la vista previa no lleva nada). `responseTimesPath` es la
// base del proyecto para el gráfico de tiempos: la del cliente o la del equipo; sin ella no hay gráfico.
interface PortalStatusViewProps {
  status: PortalStatus
  headingLevel?: 1 | 2
  actions?: ReactNode
  responseTimesPath?: string
}

export function PortalStatusView({ status, headingLevel = 1, actions, responseTimesPath }: PortalStatusViewProps) {
  const cards = [
    status.last_update && <LastUpdateCard key="update" update={status.last_update} />,
    status.ssl && <SslCard key="ssl" ssl={status.ssl} />,
    status.domain && <DomainCard key="domain" domain={status.domain} />,
    status.backup && <BackupCard key="backup" backup={status.backup} />,
  ].filter(Boolean)

  return (
    <div data-testid="portal-brand" style={brandStyle(status.primary_color)} className="space-y-6">
      <ProjectHeader name={status.name} clientName={status.client_name} logoUrl={status.logo_url} headingLevel={headingLevel} actions={actions} />
      <StatusLight state={status.status} phrase={status.phrase} checkedAt={status.checked_at} />
      {status.incident && (
        <p role="status" className="flex items-start gap-3 rounded-2xl border border-warn/30 bg-warn/10 px-5 py-4 text-sm font-medium">
          <Icon name="alert" size={18} className="mt-px shrink-0 text-warn" />
          Estamos al tanto del problema y ya lo estamos resolviendo. Lo vas a ver normalizado acá apenas esté.
        </p>
      )}
      {cards.length > 0 && <div className={`grid gap-4 ${cards.length > 1 ? 'sm:grid-cols-2' : ''}`}>{cards}</div>}
      <UptimeBars uptime={status.uptime} />
      <ComponentList components={status.components} />
      {responseTimesPath && <ResponseTimesPanel path={responseTimesPath} />}
      <IncidentsPanel incidents={status.incidents ?? []} />
      <PageSpeedPanel summaries={status.pagespeed ?? []} />
    </div>
  )
}

export function ProjectLogo({ name, logoUrl, className = 'size-12 rounded-[14px] text-xl' }: { name: string; logoUrl: string | null; className?: string }) {
  if (logoUrl) return <img src={apiUrl(logoUrl)} alt={`Logo de ${name}`} className={`shrink-0 object-contain ${className}`} />
  return (
    <span aria-hidden="true" className={`grid shrink-0 place-items-center bg-accent font-semibold text-accent-ink ${className}`}>
      {name.trim().charAt(0).toUpperCase()}
    </span>
  )
}

function ProjectHeader({ name, clientName, logoUrl, headingLevel, actions }: { name: string; clientName: string; logoUrl: string | null; headingLevel: 1 | 2; actions?: ReactNode }) {
  const Heading = headingLevel === 1 ? 'h1' : 'h2'
  return (
    <div className="flex flex-wrap items-center gap-4">
      <ProjectLogo name={name} logoUrl={logoUrl} />
      <div className="min-w-0 flex-1">
        <Heading className="title truncate">{name}</Heading>
        {clientName !== name && <p className="text-sm text-muted">{clientName}</p>}
      </div>
      {actions && <div className="flex w-full flex-wrap gap-2 sm:w-auto">{actions}</div>}
    </div>
  )
}

const lights: { state: PortalState; tone: Tone }[] = [
  { state: 'down', tone: 'crit' },
  { state: 'degraded', tone: 'warn' },
  { state: 'operational', tone: 'ok' },
]

// Semáforo: se prende solo la luz del estado; "Sin datos" las deja todas apagadas. El texto dice lo mismo.
function StatusLight({ state, phrase, checkedAt }: { state: PortalState; phrase: string; checkedAt: string | null }) {
  return (
    <section role="status" className="panel flex items-center gap-5 p-6 sm:gap-7 sm:p-8">
      <div aria-hidden="true" className="flex shrink-0 flex-col gap-2 rounded-full bg-surface-2 p-2.5">
        {lights.map((light) => {
          const color = toneColor(light.tone)
          const on = light.state === state
          return (
            <span
              key={light.state}
              data-on={on || undefined}
              className="size-5 rounded-full transition-[background-color,box-shadow] duration-[var(--dur-3)] sm:size-6"
              style={on ? { background: color, boxShadow: `0 0 18px color-mix(in oklab, ${color} 55%, transparent)` } : { background: 'var(--surface-3)' }}
            />
          )
        })}
      </div>
      <div className="min-w-0">
        <p className="text-[1.375rem] leading-tight font-semibold tracking-tight text-balance sm:text-[1.75rem]">{phrase}</p>
        <p className="num mt-1.5 text-sm text-muted">
          {portalStateMeta[state].label} · Actualizado {formatRelative(checkedAt)}
        </p>
      </div>
    </section>
  )
}
