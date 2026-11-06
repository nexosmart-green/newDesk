import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import type { TaskFilters, TaskStatus } from './types'

interface TaskFiltersBarProps {
  filters: TaskFilters
  statuses: TaskStatus[]
  /** Project members; omitted in "Mis tareas" (they're all mine). */
  members?: { id: number; full_name: string }[]
  onChange: (changes: Partial<TaskFilters>) => void
}

const OPEN = 'open'
const ALL = 'all'

function statusValue(filters: TaskFilters): string {
  const [first] = filters.status_ids
  if (first !== undefined) return String(first)
  return filters.include_closed ? ALL : OPEN
}

function statusChanges(value: string): Partial<TaskFilters> {
  if (value === OPEN) return { status_ids: [], include_closed: false }
  if (value === ALL) return { status_ids: [], include_closed: true }
  return { status_ids: [Number(value)], include_closed: false }
}

/** RF-04.05: estado (default oculta cerradas), asignado, vencidas, búsqueda por código o título. */
export function TaskFiltersBar({ filters, statuses, members, onChange }: TaskFiltersBarProps) {
  return (
    <div className={`grid gap-3 sm:items-end ${members ? 'sm:grid-cols-[1fr_11rem_11rem_auto]' : 'sm:grid-cols-[1fr_11rem_auto]'}`}>
      <TextField
        label="Buscar"
        type="search"
        placeholder="Código o título"
        value={filters.search}
        onChange={(event) => onChange({ search: event.target.value })}
      />
      <SelectField label="Estado" value={statusValue(filters)} onChange={(event) => onChange(statusChanges(event.target.value))}>
        <option value={OPEN}>Abiertas</option>
        <option value={ALL}>Todas (incluye cerradas)</option>
        {statuses.map((status) => (
          <option key={status.id} value={status.id}>
            {status.name}
          </option>
        ))}
      </SelectField>
      {members && (
        <SelectField
          label="Asignado"
          value={filters.assignee_id ?? ''}
          onChange={(event) => onChange({ assignee_id: event.target.value ? Number(event.target.value) : null })}
        >
          <option value="">Todos</option>
          {members.map((member) => (
            <option key={member.id} value={member.id}>
              {member.full_name}
            </option>
          ))}
        </SelectField>
      )}
      <label className="flex items-center gap-2 py-2 text-sm">
        <input type="checkbox" checked={filters.overdue} onChange={(event) => onChange({ overdue: event.target.checked })} />
        Solo vencidas
      </label>
    </div>
  )
}
