import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import { PersonSelect } from '@/features/people/PersonSelect'
import type { MyEntryFilters } from './types'

interface MyHoursFiltersProps {
  filters: MyEntryFilters
  projects: { id: number; name: string }[]
  /** `time.manage`: may look at someone else's hours. */
  canPickPerson: boolean
  /** The viewer: already the "Yo" option, so not listed again. */
  viewerId?: number
  /** The calendar walks months on its own: the range and the search only apply to the list. */
  showRange: boolean
  rangeError?: string
  fromError?: string
  toError?: string
  onChange: (changes: Partial<MyEntryFilters>) => void
}

export function MyHoursFilters({ filters, projects, canPickPerson, viewerId, showRange, rangeError, fromError, toError, onChange }: MyHoursFiltersProps) {
  return (
    <div className="space-y-2">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
        {canPickPerson && (
          <PersonSelect
            label="Persona"
            emptyLabel="Yo"
            value={filters.user_id}
            excludeIds={viewerId === undefined ? [] : [viewerId]}
            onChange={(user_id) => onChange({ user_id })}
          />
        )}
        <SelectField
          label="Proyecto"
          value={filters.project_id ?? ''}
          onChange={(event) => onChange({ project_id: event.target.value ? Number(event.target.value) : null })}
        >
          <option value="">Todos</option>
          {projects.map((project) => (
            <option key={project.id} value={project.id}>
              {project.name}
            </option>
          ))}
        </SelectField>
        {showRange && (
          <>
            <TextField
              label="Desde"
              type="date"
              value={filters.from}
              max={filters.to || undefined}
              error={fromError}
              onChange={(event) => onChange({ from: event.target.value })}
            />
            <TextField
              label="Hasta"
              type="date"
              value={filters.to}
              min={filters.from || undefined}
              error={toError}
              onChange={(event) => onChange({ to: event.target.value })}
            />
            <TextField
              label="Buscar"
              type="search"
              placeholder="Comentario, código o título"
              className={canPickPerson ? 'sm:col-span-2 lg:col-span-4' : undefined}
              value={filters.search}
              onChange={(event) => onChange({ search: event.target.value })}
            />
          </>
        )}
      </div>
      {showRange && rangeError && (
        <p role="alert" className="text-sm text-danger">
          {rangeError}
        </p>
      )}
    </div>
  )
}
