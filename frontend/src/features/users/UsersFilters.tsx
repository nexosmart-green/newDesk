import { useId } from 'react'
import { inputClassName } from '@/components/ui/TextField'
import { userStatusLabels, type UserStatus } from './types'

interface UsersFiltersProps {
  search: string
  status: UserStatus | ''
  onSearchChange: (search: string) => void
  onStatusChange: (status: UserStatus | '') => void
}

const statuses = Object.keys(userStatusLabels) as UserStatus[]

function isUserStatus(value: string): value is UserStatus {
  return (statuses as string[]).includes(value)
}

export function UsersFilters({ search, status, onSearchChange, onStatusChange }: UsersFiltersProps) {
  const searchId = useId()
  const statusId = useId()

  return (
    <div className="grid flex-1 gap-3 sm:grid-cols-[1fr_12rem]">
      <div className="space-y-1">
        <label htmlFor={searchId} className="block text-sm text-muted">
          Buscar
        </label>
        <input
          id={searchId}
          type="search"
          placeholder="Nombre o email"
          value={search}
          onChange={(event) => onSearchChange(event.target.value)}
          className={inputClassName}
        />
      </div>
      <div className="space-y-1">
        <label htmlFor={statusId} className="block text-sm text-muted">
          Estado
        </label>
        <select
          id={statusId}
          value={status}
          onChange={(event) => onStatusChange(isUserStatus(event.target.value) ? event.target.value : '')}
          className={inputClassName}
        >
          <option value="">Todos</option>
          {statuses.map((value) => (
            <option key={value} value={value}>
              {userStatusLabels[value]}
            </option>
          ))}
        </select>
      </div>
    </div>
  )
}
