import type { CSSProperties, ReactNode } from 'react'
import { Icon, type IconName } from './Icon'

interface PanelProps {
  title?: ReactNode
  actions?: ReactNode
  children: ReactNode
  className?: string
  bodyClassName?: string
}

// Card de sección. bodyClassName="" deja el cuerpo al ras (tablas).
export function Panel({ title, actions, children, className = '', bodyClassName }: PanelProps) {
  const hasHeader = Boolean(title || actions)
  return (
    <section className={`panel overflow-hidden ${className}`}>
      {hasHeader && (
        <header className="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 px-5 pt-4 pb-3">
          {title && <h2 className="text-headline font-semibold tracking-[-0.01em]">{title}</h2>}
          {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
        </header>
      )}
      <div className={bodyClassName ?? (hasHeader ? 'px-5 pb-5' : 'p-5')}>{children}</div>
    </section>
  )
}

// Panel que arranca plegado: el título (con su conteo) queda a la vista y el cuerpo se abre con un clic.
// Nada interactivo en el título: un control dentro del <summary> abre y cierra el panel. Van en el cuerpo.
export function CollapsiblePanel({ title, children }: { title: ReactNode; children: ReactNode }) {
  return (
    <section className="panel overflow-hidden">
      <details className="group">
        <summary className="flex cursor-pointer list-none items-center gap-2 px-5 py-4 group-open:pb-3">
          <Icon name="chevron" size={12} className="text-muted transition-transform duration-[var(--dur-2)] group-open:rotate-90" />
          <h2 className="text-headline font-semibold tracking-[-0.01em]">{title}</h2>
        </summary>
        {children}
      </details>
    </section>
  )
}

export function Stat({ label, value, hint }: { label: string; value: ReactNode; hint?: ReactNode }) {
  return (
    <div className="min-w-0">
      <div className="label">{label}</div>
      <div className="num mt-1 truncate text-xl leading-tight font-semibold tracking-tight">{value}</div>
      {hint && <div className="num mt-0.5 truncate text-xs text-faint">{hint}</div>}
    </div>
  )
}

export function Empty({ children, icon }: { children: ReactNode; icon?: IconName }) {
  return (
    <div className="fade-in flex flex-col items-center gap-3 px-6 py-12 text-center">
      {icon && (
        <span className={`grid size-10 place-items-center rounded-full bg-surface-2 ${icon === 'check' ? 'text-ok' : 'text-muted'}`}>
          <Icon name={icon} size={18} />
        </span>
      )}
      <p className="max-w-sm text-sm text-muted">{children}</p>
    </div>
  )
}

export function ErrorNote({ children, onRetry }: { children: ReactNode; onRetry?: () => void }) {
  return (
    <div role="alert" className="fade-in flex items-start gap-2.5 rounded-xl bg-crit/10 px-3.5 py-3 text-sm text-crit">
      <Icon name="alert" className="mt-0.5 shrink-0" />
      <div className="min-w-0 flex-1">{children}</div>
      {onRetry && (
        <button type="button" className="btn btn-sm -my-1 shrink-0 bg-surface text-text" onClick={onRetry}>
          Reintentar
        </button>
      )}
    </div>
  )
}

const spokes = [0, 1, 2, 3, 4, 5, 6, 7]

// Sin label: ícono de actividad que hereda tamaño y color (botones, encabezados).
// Con label: estado de carga centrado que aparece recién a los 250 ms.
export function Spinner({ label, className = '' }: { label?: string; className?: string }) {
  const icon = (
    <span className={`spinner ${className}`} aria-hidden="true">
      {spokes.map((index) => (
        <i key={index} style={{ '--i': index } as CSSProperties} />
      ))}
    </span>
  )
  if (!label) return icon
  return (
    <div role="status" className="delay-in flex flex-col items-center gap-3 py-16 text-sm text-muted">
      <span className="text-2xl">{icon}</span>
      {label}
    </div>
  )
}

// Texto para lectores de pantalla mientras se ven los skeletons.
export function LoadingStatus({ label = 'Cargando…' }: { label?: string }) {
  return (
    <span role="status" className="sr-only">
      {label}
    </span>
  )
}

// Contenedor con scroll horizontal para tablas anchas en mobile.
export function TableWrap({ children }: { children: ReactNode }) {
  return <div className="overflow-x-auto">{children}</div>
}
