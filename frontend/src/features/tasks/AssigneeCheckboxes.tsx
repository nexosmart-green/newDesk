interface AssigneeCheckboxesProps {
  members: { id: number; full_name: string }[]
  selected: number[]
  onChange: (selected: number[]) => void
  /** Where a visible "Asignados" already names the group (the task's property list): said only to screen readers. */
  hideLegend?: boolean
}

/** Assignees are always project members (RF-03.03). */
export function AssigneeCheckboxes({ members, selected, onChange, hideLegend = false }: AssigneeCheckboxesProps) {
  const toggle = (id: number) => onChange(selected.includes(id) ? selected.filter((current) => current !== id) : [...selected, id])

  return (
    <fieldset className="space-y-1">
      <legend className={hideLegend ? 'sr-only' : 'text-sm text-muted'}>Asignados</legend>
      {members.length === 0 && <p className="text-sm text-muted">El proyecto no tiene miembros.</p>}
      <ul className="max-h-40 space-y-1 overflow-y-auto rounded border border-border p-2">
        {members.map((member) => (
          <li key={member.id}>
            <label className="flex min-h-9 cursor-pointer items-center gap-2 text-sm coarse:min-h-11">
              <input type="checkbox" checked={selected.includes(member.id)} onChange={() => toggle(member.id)} />
              {member.full_name}
            </label>
          </li>
        ))}
      </ul>
    </fieldset>
  )
}
