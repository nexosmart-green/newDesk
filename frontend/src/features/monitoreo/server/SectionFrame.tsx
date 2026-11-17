import { useState, type ReactNode } from 'react'
import type { Section } from '../api/contract'
import { useRefresh } from '../api/useMonitoring'
import type { SectionSnapshot } from '../api/types'
import { Icon } from '../components/Icon'
import { Empty, Spinner } from '../components/Panel'
import { formatDateTime, formatRelative } from '../lib/format'

interface SectionFrameProps<T> {
  serverId: number
  section: Section
  snapshot: SectionSnapshot<T> | null | undefined
  children: (data: T) => ReactNode
}

// Envoltorio común de cada pestaña: antigüedad del dato, warnings, error y refrescar.
export function SectionFrame<T>({ serverId, section, snapshot, children }: SectionFrameProps<T>) {
  const refresh = useRefresh(serverId)
  const [requested, setRequested] = useState(false)
  const warnings = snapshot?.warnings ?? []
  const data = snapshot?.data ?? null
  const collectedAt = snapshot?.collected_at ?? null
  const error = snapshot?.error ?? null

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-muted">
        <span className="num" title={collectedAt ? `Recolectado ${formatDateTime(collectedAt)}` : undefined}>
          {collectedAt ? `Dato ${formatRelative(collectedAt)}` : 'Sin datos'}
        </span>
        {snapshot?.agent_version && <span className="font-mono text-faint">agente {snapshot.agent_version}</span>}
        <div className="flex-1" />
        {requested && !refresh.isPending && <span className="fade-in text-faint">Pedido enviado; se actualiza al llegar el dato.</span>}
        <button
          type="button"
          className="btn btn-ghost btn-sm"
          disabled={refresh.isPending}
          onClick={() => {
            setRequested(true)
            refresh.mutate(section)
          }}
        >
          {refresh.isPending ? <Spinner /> : <Icon name="refresh" size={12} />}
          Refrescar
        </button>
      </div>

      {error && data !== null && (
        <div role="alert" className="fade-in flex items-start gap-2.5 rounded-xl bg-warn/10 px-3.5 py-3 text-sm text-warn">
          <Icon name="alert" className="mt-0.5 shrink-0" />
          <span>Último intento falló: {error}. Se muestra el dato anterior.</span>
        </div>
      )}
      {warnings.length > 0 && (
        <details className="group rounded-xl bg-surface-2 px-4 py-3 text-sm">
          <summary className="flex items-center gap-2 text-warn">
            <Icon name="chevron" size={12} className="transition-transform duration-[var(--dur-2)] group-open:rotate-90" />
            {warnings.length} {warnings.length === 1 ? 'advertencia' : 'advertencias'} del agente
          </summary>
          <ul className="mt-2 space-y-1 pl-5 font-mono text-xs text-muted">
            {warnings.map((warning) => (
              <li key={warning}>{warning}</li>
            ))}
          </ul>
        </details>
      )}

      {data !== null ? (
        children(data)
      ) : error ? (
        <Empty icon="alert">
          Todavía no hay datos: el último intento falló ({error}). El panel reintenta solo en unos minutos; también podés tocar Refrescar.
        </Empty>
      ) : (
        <Empty icon="refresh">El agente todavía no reportó esta sección.</Empty>
      )}
    </div>
  )
}
