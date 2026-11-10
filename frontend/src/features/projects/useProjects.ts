import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
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

export function useProjects(filters: ProjectFilters) {
  return useQuery({
    queryKey: [...projectsKey, 'list', filters],
    queryFn: () => fetchProjects(filters),
    placeholderData: keepPreviousData,
  })
}

export function useProject(id: number) {
  return useQuery({ queryKey: projectKey(id), queryFn: () => fetchProject(id) })
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
    onSuccess: () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: projectsKey }),
        queryClient.invalidateQueries({ queryKey: ['clients'] }),
      ]),
  })
}

export function useUpdateProject(id: number) {
  const refresh = useRefreshProject(id)
  return useMutation({ mutationFn: (input: Partial<SaveProjectInput>) => updateProject(id, input), onSuccess: refresh })
}

export function useChangeProjectStatus(id: number) {
  const refresh = useRefreshProject(id)
  return useMutation({ mutationFn: (status: ProjectStatus) => changeProjectStatus(id, status), onSuccess: refresh })
}

export function useAddProjectMember(id: number) {
  const refresh = useRefreshProject(id)
  return useMutation({ mutationFn: (userId: number) => addProjectMember(id, userId), onSuccess: () => refresh() })
}

export function useRemoveProjectMember(id: number) {
  const refresh = useRefreshProject(id)
  return useMutation({ mutationFn: (userId: number) => removeProjectMember(id, userId), onSuccess: () => refresh() })
}
