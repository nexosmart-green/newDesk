import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import {
  addProjectMember,
  changeProjectStatus,
  createProject,
  fetchProject,
  fetchProjectActivity,
  fetchProjects,
  removeProjectMember,
  updateProject,
} from './projectsApi'
import type { CreateProjectInput, ProjectDetail, ProjectFilters, ProjectStatus, SaveProjectInput } from './types'

export const projectsKey = ['projects'] as const
const projectKey = (id: number) => [...projectsKey, 'detail', id] as const
const activityKey = (id: number) => [...projectsKey, 'activity', id] as const

export function useProjects(filters: ProjectFilters, { enabled = true } = {}) {
  return useQuery({
    queryKey: [...projectsKey, 'list', filters],
    queryFn: () => fetchProjects(filters),
    placeholderData: keepPreviousData,
    enabled,
  })
}

/** Active projects the person sees (first page): the pickers of "Mover a otro proyecto" and "Nueva tarea". */
export const ACTIVE_PROJECTS = { client_id: null, status: 'active', search: '', running_out: false, page: 1 } as const

/** `id` 0 = none chosen yet: nothing is fetched. */
export function useProject(id: number) {
  return useQuery({ queryKey: projectKey(id), queryFn: () => fetchProject(id), enabled: id > 0 })
}

export function useProjectActivity(id: number, page: number) {
  return useQuery({
    queryKey: [...activityKey(id), page],
    queryFn: () => fetchProjectActivity(id, page),
    placeholderData: keepPreviousData,
  })
}

/** Every project mutation writes the activity log and can change list rows and the client file. */
function useRefreshProject(id: number) {
  const queryClient = useQueryClient()
  return async (project?: ProjectDetail) => {
    if (project) queryClient.setQueryData(projectKey(id), project)
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: projectKey(id) }),
      queryClient.invalidateQueries({ queryKey: activityKey(id) }),
      queryClient.invalidateQueries({ queryKey: [...projectsKey, 'list'] }),
      queryClient.invalidateQueries({ queryKey: ['clients'] }),
    ])
  }
}

export function useCreateProject() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateProjectInput) => createProject(input),
    meta: { successMessage: 'Proyecto creado.' },
    onSuccess: () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: projectsKey }),
        queryClient.invalidateQueries({ queryKey: ['clients'] }),
      ]),
  })
}

export function useUpdateProject(id: number) {
  const refresh = useRefreshProject(id)
  return useMutation({
    mutationFn: (input: Partial<SaveProjectInput>) => updateProject(id, input),
    meta: { successMessage: 'Proyecto actualizado.' },
    onSuccess: refresh,
  })
}

export function useChangeProjectStatus(id: number) {
  const refresh = useRefreshProject(id)
  return useMutation({
    mutationFn: (status: ProjectStatus) => changeProjectStatus(id, status),
    meta: { successMessage: 'Estado actualizado.' },
    onSuccess: refresh,
  })
}

export function useAddProjectMember(id: number) {
  const refresh = useRefreshProject(id)
  return useMutation({
    mutationFn: (userId: number) => addProjectMember(id, userId),
    meta: { successMessage: 'Miembro agregado.' },
    onSuccess: () => refresh(),
  })
}

export function useRemoveProjectMember(id: number) {
  const refresh = useRefreshProject(id)
  return useMutation({
    mutationFn: (userId: number) => removeProjectMember(id, userId),
    onSuccess: (message) => {
      toast.success(message ?? 'Miembro quitado.')
      return refresh()
    },
  })
}
