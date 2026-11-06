interface AssigneeCheckboxesProps {
  members: { id: number; full_name: string }[]
  selected: number[]
  onChange: (selected: number[]) => void
}

/** Assignees are always project members (RF-03.03). */
export function AssigneeCheckboxes({ members, selected, onChange }: AssigneeCheckboxesProps) {
  const toggle = (id: number) => onChange(selected.includes(id) ? selected.filter((current) => current !== id) : [...selected, id])

  return (
    <fieldset className="space-y-1">
      <legend className="text-sm text-muted">Asignados</legend>
      {members.length === 0 && <p className="text-sm text-muted">El proyecto no tiene miembros.</p>}
      <ul className="max-h-40 space-y-1 overflow-y-auto rounded border border-border p-2">
        {members.map((member) => (
          <li key={member.id}>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={selected.includes(member.id)} onChange={() => toggle(member.id)} />
              {member.full_name}
            </label>
          </li>
        ))}
      </ul>
    </fieldset>
  )
}
