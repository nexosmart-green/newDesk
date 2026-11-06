import type { Developer, DevelopersInput } from '../api/types'
import { useTeamUsers } from '../api/usePortalAdmin'
import { Field } from './ComponentForm'

// Desarrolladores del proyecto (D-053). El MCP trae por defecto los proyectos de quien lo usa.
export function DevelopersFields({ value, onChange, error }: { value: DevelopersInput; onChange: (next: DevelopersInput) => void; error?: string }) {
  const team = useTeamUsers().data ?? []
  const secondary = new Set(value.secondary_user_ids)
  const lead = team.find((user) => user.id === value.lead_user_id)

  return (
    <>
      {/* La nota va fuera del <label>: si no, cambia el nombre accesible del select. */}
      <div>
        <Field label="Desarrollador principal" error={error}>
          <select
            className="input"
            value={value.lead_user_id ?? ''}
            onChange={(e) => {
              const leadId = e.target.value ? Number(e.target.value) : null
              onChange({ lead_user_id: leadId, secondary_user_ids: value.secondary_user_ids.filter((id) => id !== leadId) })
            }}
          >
            <option value="">Sin asignar</option>
            {team.map((user) => <option key={user.id} value={user.id}>{user.email}</option>)}
          </select>
        </Field>
        {lead && !lead.discord_user_id && <p className="mt-1.5 text-xs text-muted">(sin Discord cargado: no se lo etiqueta)</p>}
      </div>
      <fieldset className="space-y-1.5">
        <legend className="label mb-1.5 block">Desarrolladores secundarios</legend>
        {team.filter((user) => user.id !== value.lead_user_id).map((user) => (
          <label key={user.id} className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              className="size-4"
              checked={secondary.has(user.id)}
              onChange={(e) =>
                onChange({
                  ...value,
                  secondary_user_ids: e.target.checked ? [...value.secondary_user_ids, user.id] : value.secondary_user_ids.filter((id) => id !== user.id),
                })
              }
            />
            {user.email}
          </label>
        ))}
      </fieldset>
    </>
  )
}

export function DevelopersSummary({ developers }: { developers: Developer[] }) {
  const lead = developers.find((developer) => developer.role === 'lead')
  const secondary = developers.filter((developer) => developer.role === 'secondary')
  return (
    <p className="text-sm sm:col-span-2">
      <span className="text-muted">Desarrollador principal:</span> {lead?.email ?? 'sin asignar'}
      {secondary.length > 0 && (
        <>
          {' · '}<span className="text-muted">Secundarios:</span> {secondary.map((developer) => developer.email).join(', ')}
        </>
      )}
    </p>
  )
}
