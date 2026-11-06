import { SelectField } from '@/components/ui/SelectField'
import { TextAreaField } from '@/components/ui/TextAreaField'
import { TextField } from '@/components/ui/TextField'
import { useActiveClients } from '@/features/clients/useClients'
import { PersonSelect } from '@/features/people/PersonSelect'
import { fieldErrorOf } from '@/lib/api'
import type { ProjectForm } from './projectForm'
import { projectTypeLabels, type ProjectDetail, type ProjectType } from './types'

interface ProjectFormFieldsProps {
  form: ProjectForm
  onChange: (changes: Partial<ProjectForm>) => void
  error: unknown
  /** When editing: keeps the current client/leader selectable even if inactive, and locks the prefix. */
  project?: ProjectDetail
}

const types = Object.keys(projectTypeLabels) as ProjectType[]

export function ProjectFormFields({ form, onChange, error, project }: ProjectFormFieldsProps) {
  const clients = useActiveClients()
  const errorOf = (field: string) => fieldErrorOf(error, field)
  const activeClients = clients.data?.data ?? []
  const currentClient = project?.client && !activeClients.some((client) => client.id === project.client?.id) ? project.client : null
  const prefixLocked = project?.prefix_locked ?? false

  return (
    <>
      <div className="grid gap-4 sm:grid-cols-[1fr_9rem]">
        <TextField
          label="Nombre"
          required
          value={form.name}
          error={errorOf('name')}
          onChange={(event) => onChange({ name: event.target.value })}
        />
        <TextField
          label="Prefijo"
          required
          minLength={2}
          maxLength={10}
          autoCapitalize="none"
          disabled={prefixLocked}
          hint={prefixLocked ? 'Ya hay tareas con este prefijo.' : 'Ej. bhn → bhn-12'}
          value={form.code_prefix}
          error={errorOf('code_prefix')}
          onChange={(event) => onChange({ code_prefix: event.target.value.replace(/[^a-zA-Z0-9]/g, '') })}
        />
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <SelectField label="Tipo" value={form.type} error={errorOf('type')} onChange={(event) => onChange({ type: event.target.value === 'internal' ? 'internal' : 'bag' })}>
          {types.map((type) => (
            <option key={type} value={type}>
              {projectTypeLabels[type]}
            </option>
          ))}
        </SelectField>
        <SelectField label="Cliente" value={form.client_id} error={errorOf('client_id')} onChange={(event) => onChange({ client_id: event.target.value })}>
          <option value="">{clients.isPending ? 'Cargando…' : 'Sin cliente'}</option>
          {currentClient && <option value={currentClient.id}>{currentClient.name} (inactivo)</option>}
          {activeClients.map((client) => (
            <option key={client.id} value={client.id}>
              {client.display_name}
            </option>
          ))}
        </SelectField>
        <PersonSelect
          label="Responsable"
          emptyLabel="Sin responsable"
          value={form.leader_user_id ? Number(form.leader_user_id) : null}
          current={project?.leader}
          error={errorOf('leader_user_id')}
          onChange={(id) => onChange({ leader_user_id: id ? String(id) : '' })}
        />
        <div className="grid grid-cols-2 gap-2">
          <TextField label="Inicio" type="date" value={form.starts_on} error={errorOf('starts_on')} onChange={(event) => onChange({ starts_on: event.target.value })} />
          <TextField label="Fin estimado" type="date" value={form.ends_on} error={errorOf('ends_on')} onChange={(event) => onChange({ ends_on: event.target.value })} />
        </div>
      </div>
      <TextAreaField
        label="Descripción"
        value={form.description}
        error={errorOf('description')}
        onChange={(event) => onChange({ description: event.target.value })}
      />
    </>
  )
}
