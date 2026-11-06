import { useId, useState } from 'react'
import { inputClassName } from '@/components/ui/TextField'
import { emptyDirectoryFilters } from '@/features/people/types'
import { usePeople } from '@/features/people/usePeople'
import { useDebouncedValue } from '@/lib/useDebouncedValue'

export interface PickedMember {
  id: number
  full_name: string
}

interface MemberPickerProps {
  selected: PickedMember[]
  onChange: (selected: PickedMember[]) => void
}

/** Multi-select over the active directory (RF-03.03). The leader is added by the backend anyway. */
export function MemberPicker({ selected, onChange }: MemberPickerProps) {
  const searchId = useId()
  const [search, setSearch] = useState('')
  const people = usePeople({ ...emptyDirectoryFilters, search: useDebouncedValue(search) })
  const isSelected = (id: number) => selected.some((member) => member.id === id)

  function toggle(member: PickedMember) {
    onChange(isSelected(member.id) ? selected.filter((current) => current.id !== member.id) : [...selected, member])
  }

  return (
    <fieldset className="space-y-2">
      <legend className="text-sm text-muted">Miembros</legend>
      {selected.length > 0 && (
        <ul className="flex flex-wrap gap-1" aria-label="Miembros elegidos">
          {selected.map((member) => (
            <li key={member.id}>
              <button
                type="button"
                onClick={() => toggle(member)}
                aria-label={`Quitar a ${member.full_name}`}
                className="rounded-full border border-accent/40 px-2 py-0.5 text-xs text-accent"
              >
                {member.full_name} ×
              </button>
            </li>
          ))}
        </ul>
      )}
      <label htmlFor={searchId} className="sr-only">
        Buscar personas
      </label>
      <input
        id={searchId}
        type="search"
        placeholder="Buscar personas"
        value={search}
        onChange={(event) => setSearch(event.target.value)}
        className={inputClassName}
      />
      <ul className="max-h-40 space-y-1 overflow-y-auto rounded border border-border p-2">
        {people.isPending && <li className="text-sm text-muted">Cargando…</li>}
        {people.data?.data.map((person) => (
          <li key={person.id}>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={isSelected(person.id)} onChange={() => toggle(person)} />
              {person.full_name}
            </label>
          </li>
        ))}
      </ul>
    </fieldset>
  )
}
