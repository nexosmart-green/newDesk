import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { hasChanges } from '@/lib/formState'
import { projectFormFrom, toSaveProjectInput, type ProjectForm } from './projectForm'
import { ProjectFormFields } from './ProjectFormFields'
import type { ProjectDetail } from './types'
import { useUpdateProject } from './useProjects'

export function EditProjectDialog({ project, onClose }: { project: ProjectDetail; onClose: () => void }) {
  const [initial] = useState<ProjectForm>(() => projectFormFrom(project))
  const [form, setForm] = useState<ProjectForm>(initial)
  const mutation = useUpdateProject(project.id)

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    const { code_prefix, ...rest } = toSaveProjectInput(form)
    // A locked prefix is not sent at all, so it can never trip `prefix_locked`.
    mutation.mutate(project.prefix_locked ? rest : { ...rest, code_prefix }, { onSuccess: onClose })
  }

  return (
    <Dialog title={`Editar ${project.name}`} size="lg" dirty={hasChanges(initial, form)} onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        <ProjectFormFields
          form={form}
          project={project}
          error={mutation.error}
          onChange={(changes) => setForm({ ...form, ...changes })}
        />
        <DialogFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" loading={mutation.isPending}>
            Guardar cambios
          </Button>
        </DialogFooter>
      </form>
    </Dialog>
  )
}
