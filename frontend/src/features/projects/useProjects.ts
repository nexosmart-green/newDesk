import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import type { PersonSummary } from '@/features/people/types'
import { clientKey, clientListsKey, projectActivityKey, projectKey, projectListsKey, projectTasksKey } from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'
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
import { projectStatusVerbs, type CreateProjectInput, type ProjectDetail, type ProjectFilters, type ProjectStatus, type SaveProjectInput } from './types'

export function useProjects(filters: ProjectFilters) {
  return useQuery({
    queryKey: [...projectListsKey, filters],
    queryFn: () => fetchProjects(filters),
    placeholderData: keepPreviousData,
  })
}

export function useProject(id: number) {
  return useQuery({ queryKey: projectKey(id), queryFn: () => fetchProject(id) })
}

/** The members come with the project detail (shared cache: the project page has usually loaded it). */
export function useProjectMembers(projectId: number | undefined) {
  return useQuery({
    queryKey: projectKey(projectId ?? 0),
    queryFn: () => fetchProject(projectId ?? 0),
    enabled: projectId !== undefined,
    select: (project): PersonSummary[] => project.members,
  })
}

export function useProjectActivity(id: number, page: number) {
  return useQuery({
    queryKey: [...projectActivityKey(id), page],
    queryFn: () => fetchProjectActivity(id, page),
    placeholderData: keepPreviousData,
  })
}

/** Client files list their projects (and count them). */
const clientsOf = (...ids: (number | undefined)[]) => [clientListsKey, ...ids.flatMap((id) => (id === undefined ? [] : [clientKey(id)]))]

/**
 * Edits answer with the whole project: it is written to the detail at once (not refetched); the activity log,
 * the list rows and the client files that show it refresh in the background.
 */
function useStoreProject(id: number) {
  const queryClient = useQueryClient()
  return (project: ProjectDetail) => {
    const previousClient = queryClient.getQueryData<ProjectDetail>(projectKey(id))?.client?.id
    queryClient.setQueryData(projectKey(id), project)
    return refreshQueries(queryClient, {
      later: [projectActivityKey(id), projectListsKey, ...clientsOf(previousClient, project.client?.id ?? undefined)],
    })
  }
}

/** Members live in the detail: patched in place; the list counts members and the log records the change. */
function usePatchMembers(id: number) {
  const queryClient = useQueryClient()
  return (update: (members: PersonSummary[]) => PersonSummary[], alsoChanged: readonly (readonly unknown[])[] = []) => {
    queryClient.setQueryData<ProjectDetail>(projectKey(id), (project) => project && { ...project, members: update(project.members) })
    return refreshQueries(queryClient, { later: [projectActivityKey(id), projectListsKey, ...alsoChanged] })
  }
}

export function useCreateProject() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateProjectInput) => createProject(input),
    onSuccess: (project) => {
      toast.success(`Proyecto «${project.name}» creado.`)
      // The dialog opens the new project: it renders from here without a second request.
      queryClient.setQueryData(projectKey(project.id), project)
      return refreshQueries(queryClient, { later: [projectListsKey, ...clientsOf(project.client?.id ?? undefined)] })
    },
  })
}

export function useUpdateProject(id: number) {
  const store = useStoreProject(id)
  return useMutation({
    mutationFn: (input: Partial<SaveProjectInput>) => updateProject(id, input),
    onSuccess: (project: ProjectDetail) => {
      toast.success(`Proyecto «${project.name}» guardado.`)
      return store(project)
    },
  })
}

export function useChangeProjectStatus(id: number) {
  const store = useStoreProject(id)
  return useMutation({
    mutationFn: (status: ProjectStatus) => changeProjectStatus(id, status),
    onSuccess: (project: ProjectDetail, status) => {
      toast.success(`Proyecto «${project.name}» ${projectStatusVerbs[status].done}.`)
      return store(project)
    },
  })
}

export function useAddProjectMember(id: number) {
  const patchMembers = usePatchMembers(id)
  return useMutation({
    mutationFn: (userId: number) => addProjectMember(id, userId),
    onSuccess: (person) => {
      toast.success(`${person.full_name} ahora es miembro del proyecto.`)
      return patchMembers((members) => [...members.filter((member) => member.id !== person.id), person])
    },
  })
}

export function useRemoveProjectMember(id: number) {
  const patchMembers = usePatchMembers(id)
  return useMutation({
    mutationFn: (userId: number) => removeProjectMember(id, userId),
    onSuccess: (message, userId) => {
      toast.success(message ?? 'Miembro quitado.')
      // Leaving the project also unassigns them from its tasks.
      return patchMembers((members) => members.filter((member) => member.id !== userId), [projectTasksKey(id)])
    },
  })
}
