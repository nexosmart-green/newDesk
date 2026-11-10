import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import { useTaskCategories, useTaskLabels } from '@/features/taskCatalogs/useTaskCatalogs'
import type { TaskFilters, TaskStatus } from './types'

interface Option {
  id: number
  name: string
}

interface TaskFiltersBarProps {
  filters: TaskFilters
  statuses: TaskStatus[]
  /** People to filter by; omitted in "Mis tareas" (they're all mine). */
  members?: { id: number; full_name: string }[]
  /** Only "Todas las tareas" (the other lists are already one project). */
  projects?: Option[]
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

const idOrNull = (value: string) => (value ? Number(value) : null)

/** RF-04.05: estado (default oculta cerradas), asignado, categoría, etiqueta, vencidas, búsqueda por código o título. */
export function TaskFiltersBar({ filters, statuses, members, projects, onChange }: TaskFiltersBarProps) {
  const categories = useTaskCategories()
  const labels = useTaskLabels()

  return (
    <div className="grid grid-cols-2 items-end gap-3 lg:grid-cols-4">
      <TextField
        label="Buscar"
        type="search"
        placeholder="Código o título"
        className="col-span-2"
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
      {projects && (
        <SelectField label="Proyecto" value={filters.project_id ?? ''} onChange={(event) => onChange({ project_id: idOrNull(event.target.value) })}>
          <option value="">Todos</option>
          {projects.map((project) => (
            <option key={project.id} value={project.id}>
              {project.name}
            </option>
          ))}
        </SelectField>
      )}
      {members && (
        <SelectField label="Asignado" value={filters.assignee_id ?? ''} onChange={(event) => onChange({ assignee_id: idOrNull(event.target.value) })}>
          <option value="">Todos</option>
          {members.map((member) => (
            <option key={member.id} value={member.id}>
              {member.full_name}
            </option>
          ))}
        </SelectField>
      )}
      <SelectField label="Categoría" value={filters.category_id ?? ''} onChange={(event) => onChange({ category_id: idOrNull(event.target.value) })}>
        <option value="">Todas</option>
        {(categories.data ?? []).map((category) => (
          <option key={category.id} value={category.id}>
            {category.name}
          </option>
        ))}
      </SelectField>
      <SelectField label="Etiqueta" value={filters.label_id ?? ''} onChange={(event) => onChange({ label_id: idOrNull(event.target.value) })}>
        <option value="">Todas</option>
        {(labels.data ?? []).map((label) => (
          <option key={label.id} value={label.id}>
            {label.name}
          </option>
        ))}
      </SelectField>
      <label className="col-span-2 flex min-h-11 items-center gap-2 text-sm sm:col-span-1">
        <input type="checkbox" checked={filters.overdue} onChange={(event) => onChange({ overdue: event.target.checked })} className="size-4 accent-brand" />
        Solo vencidas
      </label>
    </div>
  )
}
