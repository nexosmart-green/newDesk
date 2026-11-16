import { FilterBar } from '@/components/ui/filters/FilterBar'
import { FilterCheck } from '@/components/ui/filters/FilterCheck'
import { FilterSelect } from '@/components/ui/filters/FilterSelect'
import { SearchInput } from '@/components/ui/filters/SearchInput'
import { useCanAny } from '@/features/auth/useAuth'
import { CLIENTS_READ_PERMISSIONS } from '@/features/clients/types'
import { useClients } from '@/features/clients/useClients'
import { isProjectStatus, projectStatuses, projectStatusLabels, type ProjectFilters } from './types'

interface ProjectsFiltersProps {
  filters: ProjectFilters
  onChange: (changes: Partial<ProjectFilters>) => void
}

const statusOptions = [{ value: '', label: 'Todos' }, ...projectStatuses.map((status) => ({ value: status, label: projectStatusLabels[status] }))]

/** RF-03.07 filters. The client filter needs reading clients, so collaborators don't get it. */
export function ProjectsFilters({ filters, onChange }: ProjectsFiltersProps) {
  const canReadClients = useCanAny(CLIENTS_READ_PERMISSIONS)
  const clients = useClients({ status: '', search: '', page: 1 }, canReadClients)
  const active = [filters.client_id !== null, filters.status !== '', filters.running_out].filter(Boolean).length

  return (
    <FilterBar
      active={active}
      search={<SearchInput label="Buscar proyectos por nombre o prefijo" placeholder="Nombre o prefijo" value={filters.search} onChange={(search) => onChange({ search })} />}
    >
      <FilterSelect
        label="Estado"
        value={filters.status}
        options={statusOptions}
        onChange={(value) => onChange({ status: isProjectStatus(value) ? value : '' })}
      />
      {canReadClients && (
        <FilterSelect
          label="Cliente"
          value={String(filters.client_id ?? '')}
          options={[{ value: '', label: 'Todos' }, ...(clients.data?.data ?? []).map((client) => ({ value: String(client.id), label: client.display_name }))]}
          onChange={(value) => onChange({ client_id: value ? Number(value) : null })}
        />
      )}
      <FilterCheck label="Con horas por agotarse" checked={filters.running_out} onChange={(running_out) => onChange({ running_out })} />
    </FilterBar>
  )
}
