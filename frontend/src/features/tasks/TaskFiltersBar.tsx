import { FilterBar } from '@/components/ui/filters/FilterBar'
import { FilterCheck } from '@/components/ui/filters/FilterCheck'
import { FilterSelect, type FilterOption } from '@/components/ui/filters/FilterSelect'
import { SearchInput } from '@/components/ui/filters/SearchInput'
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
const optionsOf = (none: string, items: readonly Option[]): FilterOption[] => [
  { value: '', label: none },
  ...items.map((item) => ({ value: String(item.id), label: item.name })),
]

/** RF-04.05: estado (default oculta cerradas), asignado, categoría, etiqueta, vencidas, búsqueda por código o título. */
export function TaskFiltersBar({ filters, statuses, members, projects, onChange }: TaskFiltersBarProps) {
  const categories = useTaskCategories()
  const labels = useTaskLabels()

  const statusOptions: FilterOption[] = [
    { value: OPEN, label: 'Abiertas' },
    { value: ALL, label: 'Todas (incluye cerradas)' },
    ...statuses.map((status) => ({ value: String(status.id), label: status.name })),
  ]
  const assignee = members && (
    <FilterSelect
      label="Asignado"
      value={String(filters.assignee_id ?? '')}
      options={optionsOf('Todos', members.map((member) => ({ id: member.id, name: member.full_name })))}
      onChange={(value) => onChange({ assignee_id: idOrNull(value) })}
    />
  )
  // With a project filter in the row, who it's assigned to waits behind "Más filtros".
  const assigneeInRow = !projects
  const moreActive =
    [filters.category_id, filters.label_id].filter((id) => id !== null).length + (!assigneeInRow && filters.assignee_id !== null ? 1 : 0)
  const rowActive =
    (statusValue(filters) !== OPEN ? 1 : 0) +
    (filters.project_id !== null ? 1 : 0) +
    (assigneeInRow && filters.assignee_id !== null ? 1 : 0) +
    (filters.overdue ? 1 : 0)

  return (
    <FilterBar
      active={rowActive + moreActive}
      moreActive={moreActive}
      search={
        <SearchInput label="Buscar tareas por código o título" placeholder="Código o título" value={filters.search} onChange={(search) => onChange({ search })} />
      }
      more={
        <>
          {!assigneeInRow && assignee}
          <FilterSelect
            label="Categoría"
            value={String(filters.category_id ?? '')}
            options={optionsOf('Todas', categories.data ?? [])}
            onChange={(value) => onChange({ category_id: idOrNull(value) })}
          />
          <FilterSelect
            label="Etiqueta"
            value={String(filters.label_id ?? '')}
            options={optionsOf('Todas', labels.data ?? [])}
            onChange={(value) => onChange({ label_id: idOrNull(value) })}
          />
        </>
      }
    >
      <FilterSelect label="Estado" value={statusValue(filters)} options={statusOptions} onChange={(value) => onChange(statusChanges(value))} />
      {projects && (
        <FilterSelect
          label="Proyecto"
          value={String(filters.project_id ?? '')}
          options={optionsOf('Todos', projects)}
          onChange={(value) => onChange({ project_id: idOrNull(value) })}
        />
      )}
      {assigneeInRow && assignee}
      <FilterCheck label="Solo vencidas" checked={filters.overdue} onChange={(overdue) => onChange({ overdue })} />
    </FilterBar>
  )
}
