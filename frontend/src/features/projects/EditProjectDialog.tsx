import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog } from '@/components/ui/Dialog'
import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { projectFormFrom, toSaveProjectInput, type ProjectForm } from './projectForm'
import { ProjectFormFields } from './ProjectFormFields'
import type { ProjectDetail } from './types'
import { useUpdateProject } from './useProjects'

export function EditProjectDialog({ project, onClose }: { project: ProjectDetail; onClose: () => void }) {
  const [form, setForm] = useState<ProjectForm>(() => projectFormFrom(project))
  const mutation = useUpdateProject(project.id)

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    const { code_prefix, ...rest } = toSaveProjectInput(form)
    // A locked prefix is not sent at all, so it can never trip `prefix_locked`.
    mutation.mutate(project.prefix_locked ? rest : { ...rest, code_prefix }, { onSuccess: onClose })
  }

  return (
    <Dialog title={`Editar ${project.name}`} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        <ProjectFormFields
          form={form}
          project={project}
          error={mutation.error}
          onChange={(changes) => setForm({ ...form, ...changes })}
        />
        <ErrorAlert error={mutation.error} />
        <div className="flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? 'Guardando…' : 'Guardar cambios'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}
