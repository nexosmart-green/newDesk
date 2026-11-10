import { SelectField } from '@/components/ui/SelectField'
import { emptyDirectoryFilters } from './types'
import { usePeople } from './usePeople'

interface PersonSelectProps {
  label: string
  value: number | null
  onChange: (personId: number | null) => void
  emptyLabel: string
  excludeIds?: readonly number[]
  /** Kept as an option even if the directory no longer lists it (e.g. a deactivated leader). */
  current?: { id: number; full_name: string } | null
  error?: string
  disabled?: boolean
}

/** Active people only: inactive ones disappear from selectors (RF-01.10). */
export function PersonSelect({ label, value, onChange, emptyLabel, excludeIds = [], current, error, disabled }: PersonSelectProps) {
  const people = usePeople(emptyDirectoryFilters)
  const options = (people.data?.data ?? []).filter((person) => !excludeIds.includes(person.id))
  const showCurrent = current && !options.some((person) => person.id === current.id)

  return (
    <SelectField
      label={label}
      value={value ?? ''}
      error={error}
      disabled={disabled || people.isPending}
      onChange={(event) => onChange(event.target.value ? Number(event.target.value) : null)}
    >
      <option value="">{people.isPending ? 'Cargando…' : emptyLabel}</option>
      {showCurrent && <option value={current.id}>{current.full_name}</option>}
      {options.map((person) => (
        <option key={person.id} value={person.id}>
          {person.full_name}
        </option>
      ))}
    </SelectField>
  )
}
