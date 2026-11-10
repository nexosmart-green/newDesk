import type { ProjectDetail, ProjectType, SaveProjectInput } from './types'

export interface ProjectForm {
  name: string
  code_prefix: string
  type: ProjectType
  client_id: string
  leader_user_id: string
  starts_on: string
  ends_on: string
  description: string
}

export const emptyProjectForm: ProjectForm = {
  name: '',
  code_prefix: '',
  type: 'bag',
  client_id: '',
  leader_user_id: '',
  starts_on: '',
  ends_on: '',
  description: '',
}

export function projectFormFrom(project: ProjectDetail): ProjectForm {
  return {
    name: project.name,
    code_prefix: project.code_prefix,
    type: project.type,
    client_id: project.client ? String(project.client.id) : '',
    leader_user_id: project.leader ? String(project.leader.id) : '',
    starts_on: project.starts_on ?? '',
    ends_on: project.ends_on ?? '',
    description: project.description ?? '',
  }
}

const orNull = (value: string) => (value.trim() === '' ? null : value.trim())
const idOrNull = (value: string) => (value ? Number(value) : null)

export function toSaveProjectInput(form: ProjectForm): SaveProjectInput {
  return {
    name: form.name.trim(),
    code_prefix: form.code_prefix.trim().toLowerCase(),
    type: form.type,
    client_id: idOrNull(form.client_id),
    description: orNull(form.description),
    leader_user_id: idOrNull(form.leader_user_id),
    starts_on: orNull(form.starts_on),
    ends_on: orNull(form.ends_on),
  }
}
