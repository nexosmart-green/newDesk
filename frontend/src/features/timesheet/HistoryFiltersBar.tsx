import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import { PersonSelect } from '@/features/people/PersonSelect'
import type { HistoryFilters } from './types'

interface HistoryFiltersBarProps {
  filters: HistoryFilters
  projects: { id: number; name: string }[]
  /** `time.manage`: may look at someone else's hours. */
  canPickPerson: boolean
  rangeError?: string
  fromError?: string
  toError?: string
  onChange: (changes: Partial<HistoryFilters>) => void
}

export function HistoryFiltersBar({ filters, projects, canPickPerson, rangeError, fromError, toError, onChange }: HistoryFiltersBarProps) {
  return (
    <div className="space-y-2">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
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
        {canPickPerson ? (
          <PersonSelect label="Persona" value={filters.user_id} emptyLabel="Yo" onChange={(user_id) => onChange({ user_id })} />
        ) : (
          <TextField
            label="Buscar"
            type="search"
            placeholder="Comentario, código o título"
            value={filters.search}
            onChange={(event) => onChange({ search: event.target.value })}
          />
        )}
        {canPickPerson && (
          <TextField
            label="Buscar"
            type="search"
            placeholder="Comentario, código o título"
            className="sm:col-span-2 lg:col-span-4"
            value={filters.search}
            onChange={(event) => onChange({ search: event.target.value })}
          />
        )}
      </div>
      {rangeError && (
        <p role="alert" className="text-sm text-danger">
          {rangeError}
        </p>
      )}
    </div>
  )
}
