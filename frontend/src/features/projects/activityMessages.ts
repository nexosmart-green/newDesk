import { isProjectStatus, projectStatusLabels, type ActivityEntry } from './types'

/** Field names in `project.updated` snapshots → how they read in a sentence. */
const projectFieldLabels: Record<string, string> = {
  name: 'el nombre',
  code_prefix: 'el prefijo',
  description: 'la descripción',
  leader_user_id: 'el responsable',
  client_id: 'el cliente',
  type: 'el tipo',
  starts_on: 'la fecha de inicio',
  ends_on: 'la fecha de fin',
}

function textOf(values: Record<string, unknown> | null, key: string): string {
  const value = values?.[key]
  return typeof value === 'string' || typeof value === 'number' ? String(value) : '—'
}

function statusOf(values: Record<string, unknown> | null): string {
  const value = values?.status
  return isProjectStatus(value) ? projectStatusLabels[value] : textOf(values, 'status')
}

export function joinWithY(items: string[]): string {
  if (items.length <= 1) return items.join('')
  return `${items.slice(0, -1).join(', ')} y ${items.at(-1)}`
}

function changedFields(entry: ActivityEntry): string[] {
  const keys = new Set([...Object.keys(entry.before ?? {}), ...Object.keys(entry.after ?? {})])
  return [...keys]
    .filter((key) => JSON.stringify(entry.before?.[key] ?? null) !== JSON.stringify(entry.after?.[key] ?? null))
    .map((key) => projectFieldLabels[key] ?? key)
}

/** RF-03.06: one readable Spanish sentence per log entry. Unknown events still read as something. */
export function describeActivity(entry: ActivityEntry): string {
  const actor = entry.actor?.full_name ?? 'El sistema'

  switch (entry.event) {
    case 'project.created':
      return `${actor} creó el proyecto «${textOf(entry.after, 'name')}»`
    case 'project.updated': {
      const fields = changedFields(entry)
      return fields.length > 0 ? `${actor} cambió ${joinWithY(fields)}` : `${actor} editó el proyecto`
    }
    case 'project.status_changed':
      return `${actor} cambió el estado de «${statusOf(entry.before)}» a «${statusOf(entry.after)}»`
    case 'project.member_added':
      return `${actor} agregó a ${textOf(entry.after, 'member')} al proyecto`
    case 'project.member_removed':
      return `${actor} quitó a ${textOf(entry.before, 'member')} del proyecto`
    default:
      return `${actor} registró «${entry.event}»`
  }
}

/** Changes made through Claude Code (MCP) say so, with the token name when there is one. */
export function activitySourceLabel(entry: ActivityEntry): string | null {
  if (entry.source === 'mcp') return entry.token_name ? `vía MCP (${entry.token_name})` : 'vía MCP'
  if (entry.source === 'system') return 'automático'
  return null
}
