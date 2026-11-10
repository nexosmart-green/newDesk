import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Dialog, DialogFooter } from '@/components/ui/Dialog'
import { MemberPicker, type PickedMember } from './MemberPicker'
import { emptyProjectForm, toSaveProjectInput, type ProjectForm } from './projectForm'
import { ProjectFormFields } from './ProjectFormFields'
import type { ProjectDetail } from './types'
import { useCreateProject } from './useProjects'

interface CreateProjectDialogProps {
  onClose: () => void
  onCreated: (project: ProjectDetail) => void
}

export function CreateProjectDialog({ onClose, onCreated }: CreateProjectDialogProps) {
  const [form, setForm] = useState<ProjectForm>(emptyProjectForm)
  const [members, setMembers] = useState<PickedMember[]>([])
  const mutation = useCreateProject()

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    mutation.mutate(
      { ...toSaveProjectInput(form), member_ids: members.map((member) => member.id) },
      { onSuccess: onCreated },
    )
  }

  return (
    <Dialog title="Nuevo proyecto" size="lg" onClose={onClose}>
      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        <ProjectFormFields form={form} error={mutation.error} onChange={(changes) => setForm({ ...form, ...changes })} />
        <MemberPicker selected={members} onChange={setMembers} />
        <DialogFooter>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button type="submit" loading={mutation.isPending}>
            Crear proyecto
          </Button>
        </DialogFooter>
      </form>
    </Dialog>
  )
}
