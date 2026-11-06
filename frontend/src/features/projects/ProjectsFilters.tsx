import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import { useCanAny } from '@/features/auth/useAuth'
import { CLIENTS_READ_PERMISSIONS } from '@/features/clients/types'
import { useClients } from '@/features/clients/useClients'
import { isProjectStatus, projectStatuses, projectStatusLabels, type ProjectFilters } from './types'

interface ProjectsFiltersProps {
  filters: ProjectFilters
  onChange: (changes: Partial<ProjectFilters>) => void
}

/** RF-03.07 filters. The client filter needs reading clients, so collaborators don't get it. */
export function ProjectsFilters({ filters, onChange }: ProjectsFiltersProps) {
  const canReadClients = useCanAny(CLIENTS_READ_PERMISSIONS)
  const clients = useClients({ status: '', search: '', page: 1 }, canReadClients)

  return (
    <div className={`grid gap-3 ${canReadClients ? 'sm:grid-cols-[1fr_12rem_12rem]' : 'sm:grid-cols-[1fr_12rem]'}`}>
      <TextField
        label="Buscar"
        type="search"
        placeholder="Nombre o prefijo"
        value={filters.search}
        onChange={(event) => onChange({ search: event.target.value })}
      />
      {canReadClients && (
        <SelectField
          label="Cliente"
          value={filters.client_id ?? ''}
          onChange={(event) => onChange({ client_id: event.target.value ? Number(event.target.value) : null })}
        >
          <option value="">Todos</option>
          {clients.data?.data.map((client) => (
            <option key={client.id} value={client.id}>
              {client.display_name}
            </option>
          ))}
        </SelectField>
      )}
      <SelectField
        label="Estado"
        value={filters.status}
        onChange={(event) => onChange({ status: isProjectStatus(event.target.value) ? event.target.value : '' })}
      >
        <option value="">Todos</option>
        {projectStatuses.map((status) => (
          <option key={status} value={status}>
            {projectStatusLabels[status]}
          </option>
        ))}
      </SelectField>
    </div>
  )
}
