import { FilterBar } from '@/components/ui/filters/FilterBar'
import { FilterSelect } from '@/components/ui/filters/FilterSelect'
import { SearchInput } from '@/components/ui/filters/SearchInput'
import { userStatusLabels, type UserStatus } from './types'

interface UsersFiltersProps {
  search: string
  status: UserStatus | ''
  onSearchChange: (search: string) => void
  onStatusChange: (status: UserStatus | '') => void
}

const statuses = Object.keys(userStatusLabels) as UserStatus[]
const statusOptions = [{ value: '', label: 'Todos' }, ...statuses.map((value) => ({ value, label: userStatusLabels[value] }))]

function isUserStatus(value: string): value is UserStatus {
  return (statuses as string[]).includes(value)
}

export function UsersFilters({ search, status, onSearchChange, onStatusChange }: UsersFiltersProps) {
  return (
    <div className="min-w-0 flex-1">
      <FilterBar active={status ? 1 : 0} search={<SearchInput label="Buscar usuarios por nombre o email" placeholder="Nombre o email" value={search} onChange={onSearchChange} />}>
        <FilterSelect
          label="Estado"
          value={status}
          options={statusOptions}
          onChange={(value) => onStatusChange(isUserStatus(value) ? value : '')}
        />
      </FilterBar>
    </div>
  )
}
