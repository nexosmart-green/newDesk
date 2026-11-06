import type { PortalComponent, PortalStatus, UptimeDay } from '../api/types'
import { Panel } from '../components/Panel'
import { Badge } from '../components/Status'
import { formatDateTime, formatDuration, formatRelative, sinceDays } from '../lib/format'
import { portalStateMeta, toneColor, type Tone } from '../lib/status'

// Las tarjetas de la página de estado: disponibilidad por día, componentes y vencimientos.

const dayFormat = new Intl.DateTimeFormat('es-AR', { day: 'numeric', month: 'short', timeZone: 'UTC' })
const percentFormat = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 2 })

const dayColor: Record<UptimeDay['status'], string> = {
  operational: toneColor('ok'),
  degraded: toneColor('warn'),
  down: toneColor('crit'),
  nodata: 'var(--surface-3)',
}

function minutes(value: number): string {
  return formatDuration(value * 60)
}

function dayText(day: UptimeDay): string {
  const date = dayFormat.format(new Date(`${day.date}T00:00:00Z`))
  if (day.status === 'nodata') return `${date}: sin datos`
  const parts = [day.down_minutes > 0 && `${minutes(day.down_minutes)} caído`, day.degraded_minutes > 0 && `${minutes(day.degraded_minutes)} degradado`].filter(Boolean)
  return `${date}: ${parts.length > 0 ? parts.join(', ') : 'sin problemas'}`
}

function uptimeText(value: number | null): string {
  return value === null ? '—' : `${percentFormat.format(value)} %`
}

// 90 barras, una por día. En el celular se ven los últimos 30 para que no queden de 1 px.
export function UptimeBars({ uptime }: { uptime: PortalStatus['uptime'] }) {
  const hidden = Math.max(uptime.days.length - 30, 0)
  return (
    <Panel
      title="Disponibilidad"
      actions={
        <span className="num text-xs text-muted">
          {uptimeText(uptime.month_pct)} este mes · {uptimeText(uptime.range_pct)} en 90 días
        </span>
      }
    >
      <ol aria-label="Disponibilidad por día" className="flex h-10 items-stretch gap-[3px]">
        {uptime.days.map((day, index) => (
          <li
            key={day.date}
            title={dayText(day)}
            className={`flex-1 rounded-[3px] transition-opacity hover:opacity-70 ${index < hidden ? 'max-sm:hidden' : ''}`}
            style={{ background: dayColor[day.status] }}
          >
            <span className="sr-only">{dayText(day)}</span>
          </li>
        ))}
      </ol>
      <div className="mt-2 flex justify-between text-xs text-faint">
        <span className="sm:hidden">Hace 30 días</span>
        <span className="max-sm:hidden">Hace 90 días</span>
        <span>Hoy</span>
      </div>
      <ul className="mt-4 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-muted" aria-label="Referencias">
        {(['operational', 'degraded', 'down', 'nodata'] as const).map((status) => (
          <li key={status} className="flex items-center gap-1.5">
            <span className="size-2.5 rounded-[3px]" style={{ background: dayColor[status] }} />
            {status === 'nodata' ? 'Sin datos' : portalStateMeta[status].label}
          </li>
        ))}
      </ul>
      <p className="mt-3 text-xs text-faint">Solo los minutos en estado Caído bajan el porcentaje.</p>
    </Panel>
  )
}

export function ComponentList({ components }: { components: PortalComponent[] }) {
  return (
    <Panel title="Componentes" bodyClassName="">
      <ul className="divide-y divide-line border-t border-line">
        {components.map((item) => {
          const meta = portalStateMeta[item.status]
          return (
            <li key={item.id} className="flex items-start justify-between gap-4 px-5 py-3.5">
              <div className="min-w-0">
                <div className="font-medium">{item.label}</div>
                <div className="text-sm text-muted">{item.phrase}</div>
                {(item.since || item.uptime_month_pct !== null) && (
                  <div className="num mt-0.5 text-xs text-faint">
                    {[item.since && `Desde ${formatRelative(item.since)}`, item.uptime_month_pct !== null && `${uptimeText(item.uptime_month_pct)} este mes`].filter(Boolean).join(' · ')}
                  </div>
                )}
              </div>
              <Badge tone={meta.tone}>{meta.label}</Badge>
            </li>
          )
        })}
        {components.length === 0 && <li className="px-5 py-6 text-sm text-muted">Todavía no hay componentes para mostrar.</li>}
      </ul>
    </Panel>
  )
}

function expiryTone(days: number | null): Tone {
  if (days === null) return 'idle'
  if (days <= 7) return 'crit'
  if (days <= 30) return 'warn'
  return 'ok'
}

function expiryText(days: number | null): string {
  if (days === null) return 'Sin fecha de vencimiento'
  if (days < 0) return `Venció hace ${-days} ${-days === 1 ? 'día' : 'días'}`
  if (days === 0) return 'Vence hoy'
  return `Vence en ${days} ${days === 1 ? 'día' : 'días'}`
}

const dateFormat = new Intl.DateTimeFormat('es-AR', { day: 'numeric', month: 'long', year: 'numeric' })

function InfoCard({ title, tone, value, hint, valueTitle }: { title: string; tone: Tone; value: string; hint?: string; valueTitle?: string }) {
  return (
    <section className="panel p-5">
      <h2 className="label">{title}</h2>
      <p className="mt-2 flex items-center gap-2 font-semibold" title={valueTitle}>
        <span className="size-2 shrink-0 rounded-full" style={{ background: toneColor(tone) }} />
        {value}
      </p>
      {hint && <p className="num mt-1 text-xs text-faint">{hint}</p>}
    </section>
  )
}

export function SslCard({ ssl }: { ssl: NonNullable<PortalStatus['ssl']> }) {
  return <InfoCard title="Certificado de seguridad (SSL)" tone={expiryTone(ssl.days_left)} value={expiryText(ssl.days_left)} hint={dateFormat.format(new Date(ssl.expires_at))} />
}

export function DomainCard({ domain }: { domain: NonNullable<PortalStatus['domain']> }) {
  return (
    <InfoCard
      title={`Dominio ${domain.name}`}
      tone={expiryTone(domain.days_left)}
      value={expiryText(domain.days_left)}
      hint={domain.expires_at ? dateFormat.format(new Date(domain.expires_at)) : undefined}
    />
  )
}

// Solo la fecha del último despliegue de sus repos (D-042), sin notas de versión.
export function LastUpdateCard({ update }: { update: NonNullable<PortalStatus['last_update']> }) {
  const when = sinceDays(update.at)
  return (
    <InfoCard
      title="Última actualización"
      tone="info"
      value={when.charAt(0).toUpperCase() + when.slice(1)}
      valueTitle={formatDateTime(update.at)}
      hint={dateFormat.format(new Date(update.at))}
    />
  )
}

export function BackupCard({ backup }: { backup: NonNullable<PortalStatus['backup']> }) {
  return (
    <InfoCard
      title="Backups"
      tone={portalStateMeta[backup.status].tone}
      value={backup.last_ok_at ? `Último backup OK ${formatRelative(backup.last_ok_at)}` : 'Todavía no hay un backup correcto'}
      hint={backup.status === 'degraded' ? 'El último intento falló o está atrasado.' : undefined}
    />
  )
}
