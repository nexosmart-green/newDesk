import { useState } from 'react'
import { errorMessage } from '../api/monitoringApi'
import { useSuggestions } from '../api/usePortalAdmin'
import { useProjectMutations } from '../api/useProjectMutations'
import type { ComponentInput, PortalPart, ProjectAdmin, Suggestion } from '../api/types'
import { ErrorNote, Panel, Spinner } from '../components/Panel'
import { Badge } from '../components/Status'
import { partLabels, PORTAL_PARTS } from '../lib/parts'
import { kindLabels } from './kinds'

// ---------- sugerencias de componentes ----------

const suggestionKey = (s: Suggestion) => `${s.kind}|${s.url ?? ''}|${s.server_id ?? ''}|${s.target ?? ''}@${s.target_owner ?? ''}`

function watched(s: Suggestion): string {
  if (s.kind === 'http' || s.kind === 'health') return s.url ?? '—'
  return `${s.server_name ?? 'servidor'} · ${s.target ?? '—'}${s.target_owner ? ` (${s.target_owner})` : ''}`
}

function toInput(s: Suggestion, parts: PortalPart[], position: number): ComponentInput {
  if (s.kind === 'http' || s.kind === 'health') return { kind: s.kind, label: s.label, parts, position, url: s.url ?? '' }
  const owner = s.kind === 'pm2' ? { target_owner: s.target_owner ?? null } : {}
  return { kind: s.kind, label: s.label, parts, position, server_id: s.server_id ?? undefined, target: s.target ?? '', ...owner }
}

// Lo que el panel propone a partir de las carpetas tildadas; el equipo elige qué agregar.
export function SuggestionsPanel({ project }: { project: ProjectAdmin }) {
  const suggestions = useSuggestions(project.id)
  const { addSuggestions } = useProjectMutations(project.id)
  // Lo que el admin destildó; todo lo nuevo viene tildado. Las partes vienen pre-tildadas del back (D-045).
  const [skipped, setSkipped] = useState<Set<string>>(new Set())
  const [changedParts, setChangedParts] = useState<Map<string, PortalPart[]>>(new Map())
  const partsOf = (s: Suggestion) => changedParts.get(suggestionKey(s)) ?? s.parts
  const fresh = (suggestions.data ?? []).filter((s) => !s.exists)
  const chosen = fresh.filter((s) => !skipped.has(suggestionKey(s)))
  const nextPosition = Math.max(-1, ...project.components.map((item) => item.position)) + 1

  const toggle = (s: Suggestion) => {
    const next = new Set(skipped)
    if (next.has(suggestionKey(s))) next.delete(suggestionKey(s))
    else next.add(suggestionKey(s))
    setSkipped(next)
  }

  const togglePart = (s: Suggestion, part: PortalPart) => {
    const current = partsOf(s)
    const next = current.includes(part) ? current.filter((each) => each !== part) : PORTAL_PARTS.filter((each) => each === part || current.includes(each))
    setChangedParts(new Map(changedParts).set(suggestionKey(s), next))
  }

  return (
    <Panel title="Sugerencias de componentes" bodyClassName="">
      <p className="px-5 pb-3 text-sm text-muted">Sale de las carpetas vinculadas: los dominios que sirven, los procesos que corren ahí y los backups con su nombre. Revisá y agregá lo que el cliente tiene que ver.</p>
      {suggestions.isPending && <div className="px-5 pb-5"><Spinner label="Buscando sugerencias…" /></div>}
      {suggestions.isError && <div className="px-5 pb-5"><ErrorNote onRetry={() => void suggestions.refetch()}>{errorMessage(suggestions.error)}</ErrorNote></div>}
      {suggestions.data && suggestions.data.length === 0 && (
        <p className="border-t border-line px-5 py-4 text-sm text-muted">Sin sugerencias: tildá las carpetas del cliente para que el panel proponga qué chequear.</p>
      )}
      {suggestions.data && suggestions.data.length > 0 && (
        <ul className="divide-y divide-line border-t border-line" aria-label="Sugerencias">
          {suggestions.data.map((s) => {
            const key = suggestionKey(s)
            const id = `suggestion-${key}`
            return (
              <li key={key} className={`flex items-start gap-3 px-5 py-2.5 ${s.exists ? 'opacity-60' : ''}`}>
                <input
                  id={id}
                  type="checkbox"
                  className="mt-1 size-4 shrink-0"
                  checked={!s.exists && !skipped.has(key)}
                  disabled={s.exists || addSuggestions.isPending}
                  onChange={() => toggle(s)}
                />
                <div className="min-w-0 flex-1">
                  <label htmlFor={id} className="flex flex-wrap items-center gap-x-2 font-medium">
                    {s.label}
                    <span className="text-xs font-normal text-muted">{kindLabels[s.kind]}</span>
                    {s.exists && <Badge tone="idle">ya agregado</Badge>}
                  </label>
                  <div className="truncate font-mono text-xs text-muted" title={watched(s)}>{watched(s)}</div>
                  <div className="text-xs text-faint">{s.reason}</div>
                  <SuggestionParts
                    suggestion={s}
                    parts={partsOf(s)}
                    disabled={skipped.has(key) || addSuggestions.isPending}
                    onToggle={(part) => togglePart(s, part)}
                  />
                </div>
              </li>
            )
          })}
        </ul>
      )}
      {addSuggestions.isError && <div className="px-5 pt-4"><ErrorNote>{errorMessage(addSuggestions.error)}</ErrorNote></div>}
      {fresh.length > 0 && (
        <div className="flex flex-wrap items-center gap-3 border-t border-line px-5 py-4">
          <button
            type="button"
            className="btn btn-primary"
            disabled={chosen.length === 0 || addSuggestions.isPending}
            onClick={() =>
              addSuggestions.mutate(chosen.map((s, index) => toInput(s, partsOf(s), nextPosition + index)), {
                onSuccess: () => {
                  setSkipped(new Set())
                  setChangedParts(new Map())
                },
              })
            }
          >
            {addSuggestions.isPending && <Spinner />}
            Agregar seleccionados ({chosen.length})
          </button>
          <span className="text-xs text-muted">Se agregan como componentes; después se pueden renombrar o borrar.</span>
        </div>
      )}
    </Panel>
  )
}

// A qué partes del cliente afecta: se corrige antes de agregar; lo ya agregado se cambia en el componente.
function SuggestionParts({ suggestion, parts, disabled, onToggle }: { suggestion: Suggestion; parts: PortalPart[]; disabled: boolean; onToggle: (part: PortalPart) => void }) {
  if (suggestion.exists) return null
  if (suggestion.kind === 'timer') return <div className="mt-1 text-xs text-muted">Afecta a: ninguna parte (tarjeta de backups)</div>
  return (
    <fieldset className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs" disabled={disabled} aria-label={`Partes que afecta ${suggestion.label}`}>
      <span className="text-muted">Afecta a:</span>
      {PORTAL_PARTS.map((part) => (
        <label key={part} className="flex items-center gap-1.5">
          <input type="checkbox" className="size-3.5" checked={parts.includes(part)} onChange={() => onToggle(part)} />
          {partLabels[part]}
        </label>
      ))}
    </fieldset>
  )
}
