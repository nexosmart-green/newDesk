import { useState, type ComponentProps, type FormEvent, type ReactNode } from 'react'
import { ApiError } from '@/lib/api'
import type { ComponentAdmin, ComponentInput, ComponentKind, PortalPart, ServerTargets } from '../api/types'
import { kindLabels } from './kinds'
import { Icon } from '../components/Icon'
import { ErrorNote, Spinner } from '../components/Panel'
import { partFromUrl, partLabels, PORTAL_PARTS } from '../lib/parts'

const targetLabels: Record<'pm2' | 'supervisor' | 'timer', string> = {
  pm2: 'App de PM2',
  supervisor: 'Programa o grupo',
  timer: 'Timer',
}

const isWeb = (kind: ComponentKind) => kind === 'http' || kind === 'health'

// Lo que se puede mirar en un servidor. owner solo en PM2: el usuario del daemon, porque dos usuarios
// pueden tener apps con el mismo nombre (NV-10). Sin owner = guardado antes de elegirlo.
interface TargetOption { name: string; owner: string | null }
const optionKey = (option: TargetOption) => JSON.stringify([option.name, option.owner])
const optionLabel = (option: TargetOption) => (option.owner ? `${option.name} (${option.owner})` : option.name)

function targetsOf(server: ServerTargets | undefined, kind: ComponentKind): TargetOption[] {
  if (!server) return []
  if (kind === 'pm2') return server.pm2.map(({ name, owner }) => ({ name, owner: owner || null }))
  const names = kind === 'supervisor' ? server.supervisor : kind === 'timer' ? server.timers : []
  return names.map((name) => ({ name, owner: null }))
}

// Lo que se tilda solo hasta que el equipo toca las casillas (D-045): una URL web, la parte que nombra
// su host; la API y los procesos, las partes que ya tiene el proyecto (la web si no tiene ninguna).
function suggestedParts(kind: ComponentKind, url: string, projectParts: readonly PortalPart[]): PortalPart[] {
  if (kind === 'timer') return []
  if (kind === 'http') return [partFromUrl(url)]
  return projectParts.length > 0 ? [...projectParts] : ['web']
}

interface ComponentFormProps {
  initial?: ComponentAdmin
  // Las partes que ya cubren los otros componentes del proyecto.
  projectParts?: readonly PortalPart[]
  targets: readonly ServerTargets[]
  pending: boolean
  error: unknown
  onSubmit: (input: ComponentInput) => void
  onCancel: () => void
}

// Qué mira cada componente: una URL (sitio web o /health de la API) o algo de un servidor
// (app de PM2, programa de Supervisor, timer de backup), elegido de lo que reporta su agente.
export function ComponentForm({ initial, projectParts = [], targets, pending, error, onSubmit, onCancel }: ComponentFormProps) {
  const [kind, setKind] = useState<ComponentKind>(initial?.kind ?? 'http')
  // null = sin tocar: se sigue lo sugerido. Al editar se parte de lo guardado (un backup no tiene partes).
  const [chosenParts, setChosenParts] = useState<PortalPart[] | null>(initial && initial.kind !== 'timer' ? initial.parts : null)
  const [label, setLabel] = useState(initial?.label ?? '')
  const [url, setUrl] = useState(initial?.url ?? '')
  const [serverId, setServerId] = useState(initial?.server_id ? String(initial.server_id) : '')
  const [target, setTarget] = useState<TargetOption | null>(initial?.target ? { name: initial.target, owner: initial.target_owner ?? null } : null)
  const [slowMs, setSlowMs] = useState(initial?.slow_ms ? String(initial.slow_ms) : '3000')
  const [maxAge, setMaxAge] = useState(initial?.max_age_hours ? String(initial.max_age_hours) : '26')
  const [position, setPosition] = useState(String(initial?.position ?? 0))
  const apiError = error instanceof ApiError ? error : null
  const server = targets.find((each) => String(each.server_id) === serverId)
  // El objetivo guardado puede no estar en el último snapshot (app borrada, server caído): igual se ofrece.
  const options = [...new Map([...targetsOf(server, kind), ...(target ? [target] : [])].map((option) => [optionKey(option), option])).values()]
  const parts = kind === 'timer' ? [] : (chosenParts ?? suggestedParts(kind, url, projectParts))
  const togglePart = (part: PortalPart) =>
    setChosenParts(parts.includes(part) ? parts.filter((each) => each !== part) : PORTAL_PARTS.filter((each) => each === part || parts.includes(each)))

  const submit = (event: FormEvent) => {
    event.preventDefault()
    const base: ComponentInput = { kind, label: label.trim(), parts, position: Number(position) || 0 }
    onSubmit(
      isWeb(kind)
        ? { ...base, url: url.trim(), slow_ms: Number(slowMs) || undefined }
        : {
            ...base, server_id: Number(serverId) || undefined, target: target?.name ?? '',
            ...(kind === 'pm2' ? { target_owner: target?.owner ?? null } : {}),
            ...(kind === 'timer' ? { max_age_hours: Number(maxAge) || undefined } : {}),
          },
    )
  }

  return (
    <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2" noValidate aria-label={initial ? `Editar ${initial.label}` : 'Nuevo componente'}>
      <Field label="Tipo" error={apiError?.field('kind')}>
        <Select value={kind} onChange={(e) => { setKind(e.target.value as ComponentKind); setTarget(null) }}>
          {(Object.keys(kindLabels) as ComponentKind[]).map((value) => (
            <option key={value} value={value}>{kindLabels[value]}</option>
          ))}
        </Select>
      </Field>
      <Field label="Nombre interno" error={apiError?.field('label')}>
        <input className="input" value={label} onChange={(e) => setLabel(e.target.value)} placeholder={kind === 'http' ? 'Web' : kind === 'health' ? 'API' : kind === 'timer' ? 'Backups' : 'Workers'} />
      </Field>

      <fieldset className="min-w-0 sm:col-span-2">
        <legend className="label mb-1.5 block">Afecta a</legend>
        {kind === 'timer' ? (
          <p className="text-sm text-muted">A ninguna parte: el cliente ve los backups en su propia tarjeta.</p>
        ) : (
          <>
            <div className="flex flex-wrap gap-x-5 gap-y-2">
              {PORTAL_PARTS.map((part) => (
                <label key={part} className="flex items-center gap-2 text-sm">
                  <input type="checkbox" className="size-4" checked={parts.includes(part)} onChange={() => togglePart(part)} />
                  {partLabels[part]}
                </label>
              ))}
            </div>
            <p className="mt-1.5 text-xs text-muted">
              {parts.length > 0
                ? 'El cliente ve su proyecto como Web, App y CMS: si esto falla, esas partes se ven con problemas.'
                : 'Sin partes tildadas el cliente no lo ve: queda solo para el equipo.'}
            </p>
          </>
        )}
        {apiError?.field('parts') && <span className="fade-in mt-1.5 block text-xs text-crit">{apiError.field('parts')}</span>}
      </fieldset>

      {isWeb(kind) ? (
        <>
          <Field label={kind === 'health' ? 'URL del /health' : 'URL'} error={apiError?.field('url')}>
            <input className="input font-mono text-xs" type="url" value={url} onChange={(e) => setUrl(e.target.value)} placeholder={kind === 'health' ? 'https://api.ejemplo.com/health' : 'https://ejemplo.com'} />
          </Field>
          <Field label="Lento a partir de (ms)" error={apiError?.field('slow_ms')}>
            <input className="input num" inputMode="numeric" value={slowMs} onChange={(e) => setSlowMs(e.target.value.replace(/\D/g, ''))} />
          </Field>
        </>
      ) : (
        <>
          <Field label="Servidor" error={apiError?.field('server_id')}>
            <Select value={serverId} onChange={(e) => { setServerId(e.target.value); setTarget(null) }}>
              <option value="">Elegí un servidor</option>
              {targets.map((each) => (
                <option key={each.server_id} value={each.server_id}>{each.server_name}</option>
              ))}
            </Select>
          </Field>
          <Field label={targetLabels[kind as keyof typeof targetLabels]} error={apiError?.field('target')}>
            <Select
              value={target ? optionKey(target) : ''}
              onChange={(e) => setTarget(options.find((option) => optionKey(option) === e.target.value) ?? null)}
              disabled={!serverId}
            >
              <option value="">{serverId ? (options.length > 0 ? 'Elegí uno' : 'El agente no reporta ninguno') : 'Primero elegí el servidor'}</option>
              {options.map((option) => (
                <option key={optionKey(option)} value={optionKey(option)}>{optionLabel(option)}</option>
              ))}
            </Select>
          </Field>
          {kind === 'timer' && (
            <Field label="Atrasado después de (horas)" error={apiError?.field('max_age_hours')}>
              <input className="input num" inputMode="numeric" value={maxAge} onChange={(e) => setMaxAge(e.target.value.replace(/\D/g, ''))} />
            </Field>
          )}
        </>
      )}
      <Field label="Orden" error={apiError?.field('position')}>
        <input className="input num" inputMode="numeric" value={position} onChange={(e) => setPosition(e.target.value.replace(/\D/g, ''))} />
      </Field>

      {apiError && Object.keys(apiError.fieldErrors).length === 0 && <div className="sm:col-span-2"><ErrorNote>{apiError.message}</ErrorNote></div>}
      <div className="flex gap-2 sm:col-span-2">
        <button type="submit" className="btn btn-primary" disabled={pending || !label.trim()}>
          {pending && <Spinner />}
          {initial ? 'Guardar componente' : 'Agregar componente'}
        </button>
        <button type="button" className="btn" onClick={onCancel}>Cancelar</button>
      </div>
    </form>
  )
}

export function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
  return (
    <label className="block">
      <span className="label mb-1.5 block">{label}</span>
      {children}
      {error && <span className="fade-in mt-1.5 block text-xs text-crit">{error}</span>}
    </label>
  )
}

// select.input no trae flecha (appearance: none): se agrega la del resto del panel.
export function Select({ className = '', ...props }: ComponentProps<'select'>) {
  return (
    <span className="relative block">
      <select className={`input ${className}`} {...props} />
      <Icon name="chevron" size={12} className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 rotate-90 text-muted" />
    </span>
  )
}
