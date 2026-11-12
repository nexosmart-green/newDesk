import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { dashboardKey, extensionRequestsKey, projectHoursKey, projectKey, projectListsKey, timerKey } from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'
import {
  allocateHours,
  approveExtension,
  fetchExtensionRequests,
  fetchProjectEntries,
  fetchProjectHours,
  rejectExtension,
  requestExtension,
} from './hoursApi'
import type { AllocationInput, ProjectEntryFilters } from './types'

export function useProjectHours(projectId: number) {
  return useQuery({ queryKey: [...projectHoursKey(projectId), 'bag'], queryFn: () => fetchProjectHours(projectId) })
}

export function useProjectEntries(projectId: number, filters: ProjectEntryFilters) {
  return useQuery({
    queryKey: [...projectHoursKey(projectId), 'entries', filters],
    queryFn: () => fetchProjectEntries(projectId, filters),
    placeholderData: keepPreviousData,
  })
}

/**
 * A bag change moves the hours tab (awaited: the dialog closes onto the new balance); the project header
 * and list (%), the Panel's bags and blocked timers follow in the background.
 */
function useRefreshBag() {
  const queryClient = useQueryClient()
  return (projectId: number, now: readonly (readonly unknown[])[] = [projectHoursKey(projectId)]) =>
    refreshQueries(queryClient, {
      now,
      later: [projectHoursKey(projectId), projectKey(projectId), projectListsKey, dashboardKey, timerKey],
    })
}

export function useAllocateHours(projectId: number) {
  const refresh = useRefreshBag()
  return useMutation({
    meta: { successMessage: 'Horas cargadas en la bolsa.' },
    mutationFn: (input: AllocationInput) => allocateHours(projectId, input),
    onSuccess: () => refresh(projectId),
  })
}

export function useRequestExtension() {
  const queryClient = useQueryClient()
  return useMutation({
    // Shown where it was asked (the timer block): the button turns into the confirmation.
    meta: { silentError: true },
    mutationFn: ({ projectId, message }: { projectId: number; message?: string }) => requestExtension(projectId, message),
    onSuccess: () => refreshQueries(queryClient, { later: [extensionRequestsKey] }),
  })
}

export function useExtensionRequests(enabled: boolean) {
  return useQuery({ queryKey: extensionRequestsKey, queryFn: fetchExtensionRequests, enabled })
}

export function useApproveExtension() {
  const refresh = useRefreshBag()
  return useMutation({
    meta: { successMessage: 'Ampliación aprobada.' },
    mutationFn: ({ requestId, input }: { requestId: number; input: AllocationInput }) => approveExtension(requestId, input),
    onSuccess: (request) => refresh(request.project.id, [projectHoursKey(request.project.id), extensionRequestsKey]),
  })
}

export function useRejectExtension() {
  const queryClient = useQueryClient()
  return useMutation({
    meta: { successMessage: 'Pedido rechazado.' },
    mutationFn: (requestId: number) => rejectExtension(requestId),
    onSuccess: () => refreshQueries(queryClient, { now: [extensionRequestsKey] }),
  })
}
