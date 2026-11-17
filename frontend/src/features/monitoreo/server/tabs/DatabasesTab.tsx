import type { CSSProperties } from 'react'
import type { Databases } from '../../api/contract'
import { Empty, ErrorNote, Panel } from '../../components/Panel'
import { Badge } from '../../components/Status'
import { formatBytes } from '../../lib/format'

export function DatabasesTab({ data }: { data: Databases }) {
  const items = data.items ?? []
  if (items.length === 0) return <Empty>No se detectaron motores de base de datos.</Empty>

  return (
    <div className="grid gap-5 lg:grid-cols-2">
      {items.map((db) => {
        const schemas = [...(db.schemas ?? [])].sort((a, b) => b.size_bytes - a.size_bytes)
        const total = schemas.reduce((sum, schema) => sum + schema.size_bytes, 0)
        const largest = schemas[0]?.size_bytes ?? 0
        return (
          <Panel
            key={`${db.engine}-${db.listen?.join(',')}`}
            title={<span>{db.engine} <span className="font-normal text-muted">{db.version}</span></span>}
            actions={<Badge tone={db.running ? 'ok' : 'crit'}>{db.running ? 'Corriendo' : 'Caído'}</Badge>}
          >
            <div className="space-y-4">
              <div className="font-mono text-xs text-muted">{(db.listen ?? []).join(' · ')}</div>
              {db.error && <ErrorNote>{db.error}</ErrorNote>}
              {schemas.length > 0 && (
                <div>
                  <div className="mb-2 flex justify-between">
                    <span className="label">Bases · {schemas.length}</span>
                    <span className="num text-xs text-muted">total {formatBytes(total)}</span>
                  </div>
                  <ul className="space-y-1.5">
                    {schemas.map((schema) => (
                      <li key={schema.name} className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 text-sm">
                        <span className="truncate">{schema.name}</span>
                        <span className="num text-right text-xs text-muted">{formatBytes(schema.size_bytes)}</span>
                        <span className="col-span-2 h-1 overflow-hidden rounded-full bg-surface-3">
                          <span className="bar block bg-accent" style={{ '--v': `${largest ? (schema.size_bytes / largest) * 100 : 0}%` } as CSSProperties} />
                        </span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
              {db.info && Object.keys(db.info).length > 0 && (
                <dl className="grid grid-cols-2 gap-x-4 gap-y-1 border-t border-line pt-3 text-xs">
                  {Object.entries(db.info).map(([key, value]) => (
                    <div key={key} className="flex justify-between gap-2">
                      <dt className="text-muted">{key}</dt>
                      <dd className="num">{value}</dd>
                    </div>
                  ))}
                </dl>
              )}
            </div>
          </Panel>
        )
      })}
    </div>
  )
}
