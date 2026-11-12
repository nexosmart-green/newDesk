import { joinWithY } from '@/features/projects/activityMessages'
import type { ActivityEntry } from '@/features/projects/types'
import { formatDate } from '@/lib/dates'
import { formatDuration } from '@/lib/duration'
import { isTaskPriority, taskPriorityLabels } from './types'

/** Field names in `task.updated` snapshots → label and how their values read. */
const fields: Record<string, { label: string; noun: string; format?: (value: unknown) => string }> = {
  title: { label: 'Título', noun: 'el título' },
  description: { label: 'Descripción', noun: 'la descripción' },
  // The backend flags a description edit instead of storing the text twice.
  description_changed: { label: 'Descripción', noun: 'la descripción' },
  priority: {
    label: 'Prioridad',
    noun: 'la prioridad',
    format: (value) => (isTaskPriority(value) ? taskPriorityLabels[value] : String(value)),
  },
  starts_on: { label: 'Inicio', noun: 'la fecha de inicio', format: (value) => formatDate(String(value)) },
  due_on: { label: 'Vencimiento', noun: 'el vencimiento', format: (value) => formatDate(String(value)) },
  estimate_seconds: {
    label: 'Estimación',
    noun: 'la estimación',
    format: (value) => (typeof value === 'number' ? formatDuration(value) : String(value)),
  },
  category: { label: 'Categoría', noun: 'la categoría' },
  category_id: { label: 'Categoría', noun: 'la categoría' },
  labels: { label: 'Etiquetas', noun: 'las etiquetas' },
}

/** Text too long to diff inline: the sentence already says it changed. */
const SENTENCE_ONLY = new Set(['description', 'description_changed'])

export interface ActivityChange {
  key: string
  label: string
  before: string
  after: string
}

function plain(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  if (Array.isArray(value)) return value.length > 0 ? value.map(plain).join(', ') : '—'
  if (typeof value === 'object' && 'name' in value) return String((value as { name: unknown }).name)
  return String(value)
}

function readable(key: string, value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  const format = fields[key]?.format
  return format ? format(value) : plain(value)
}

/** Field-by-field before → after of an update (only what changed). */
export function activityChanges(entry: ActivityEntry): ActivityChange[] {
  const keys = new Set([...Object.keys(entry.before ?? {}), ...Object.keys(entry.after ?? {})])
  return [...keys]
    .filter((key) => JSON.stringify(entry.before?.[key] ?? null) !== JSON.stringify(entry.after?.[key] ?? null))
    .map((key) => ({
      key,
      label: fields[key]?.label ?? key,
      before: readable(key, entry.before?.[key]),
      after: readable(key, entry.after?.[key]),
    }))
}

function textOf(values: Record<string, unknown> | null, key: string): string {
  return plain(values?.[key])
}

/** Checklist and attachment events carry the item's text / file name in `after` (or `before` when removed). */
function subjectOf(entry: ActivityEntry, key: string): string {
  const value = entry.after?.[key] ?? entry.before?.[key]
  return plain(value)
}

/** One sentence per entry of the task's history. Unknown events still read as something. */
export function describeTaskActivity(entry: ActivityEntry): string {
  const actor = entry.actor?.full_name ?? 'El sistema'

  switch (entry.event) {
    case 'task.created':
      return `${actor} creó la tarea`
    case 'task.updated': {
      const nouns = activityChanges(entry).map((change) => fields[change.key]?.noun ?? change.key)
      return nouns.length > 0 ? `${actor} cambió ${joinWithY([...new Set(nouns)])}` : `${actor} editó la tarea`
    }
    case 'task.status_changed':
      return `${actor} cambió el estado de «${textOf(entry.before, 'status')}» a «${textOf(entry.after, 'status')}»`
    case 'task.assigned': {
      const assignees = entry.after?.assignees
      return Array.isArray(assignees) && assignees.length === 0
        ? `${actor} dejó la tarea sin asignar`
        : `${actor} asignó la tarea a ${textOf(entry.after, 'assignees')}`
    }
    case 'task.moved_out':
      return `${actor} movió la tarea al proyecto «${textOf(entry.after, 'project')}»`
    case 'task.moved_in':
      return `${actor} trajo la tarea desde el proyecto «${textOf(entry.after, 'project')}»`
    case 'checklist.added':
      return `${actor} agregó la subtarea «${subjectOf(entry, 'text')}»`
    case 'checklist.checked':
      return `${actor} tildó la subtarea «${subjectOf(entry, 'text')}»`
    case 'checklist.unchecked':
      return `${actor} destildó la subtarea «${subjectOf(entry, 'text')}»`
    case 'checklist.renamed':
      return `${actor} renombró la subtarea «${textOf(entry.before, 'text')}» a «${textOf(entry.after, 'text')}»`
    case 'checklist.removed':
      return `${actor} borró la subtarea «${subjectOf(entry, 'text')}»`
    case 'attachment.added':
      return `${actor} adjuntó «${subjectOf(entry, 'name')}»`
    case 'attachment.removed':
      return `${actor} borró el archivo «${subjectOf(entry, 'name')}»`
    default:
      return `${actor} registró «${entry.event}»`
  }
}

/** What an update lists under its sentence (other events say it all in the sentence). */
export function visibleChanges(entry: ActivityEntry): ActivityChange[] {
  return entry.event === 'task.updated' ? activityChanges(entry).filter((change) => !SENTENCE_ONLY.has(change.key)) : []
}
