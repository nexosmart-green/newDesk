import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  allocateHours,
  approveExtension,
  fetchDailyHours,
  fetchEntries,
  fetchExtensionRequests,
  fetchProjectEntries,
  fetchProjectHours,
  rejectExtension,
  requestExtension,
} from './hoursApi'
import type { AllocationInput, DailyHoursFilters, MyEntryFilters, ProjectEntryFilters } from './types'

export const hoursKey = ['hours'] as const
const projectHoursKey = (projectId: number) => [...hoursKey, projectId] as const
const extensionRequestsKey = [...hoursKey, 'extension-requests'] as const

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

export function useEntries(filters: MyEntryFilters) {
  return useQuery({
    queryKey: [...hoursKey, 'entries', filters],
    queryFn: () => fetchEntries(filters),
    placeholderData: keepPreviousData,
  })
}

export function useDailyHours(filters: DailyHoursFilters) {
  return useQuery({
    queryKey: [...hoursKey, 'days', filters],
    queryFn: () => fetchDailyHours(filters),
    placeholderData: keepPreviousData,
  })
}

/** A bag change moves the hours tab, the project header/list (%) and unblocks timers. */
function useRefreshBags() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: hoursKey }),
      queryClient.invalidateQueries({ queryKey: ['projects'] }),
      queryClient.invalidateQueries({ queryKey: ['timer'] }),
    ])
}

export function useAllocateHours(projectId: number) {
  const refresh = useRefreshBags()
  return useMutation({
    meta: { successMessage: 'Horas cargadas en la bolsa.' },
    mutationFn: (input: AllocationInput) => allocateHours(projectId, input),
    onSuccess: refresh,
  })
}

export function useRequestExtension() {
  const queryClient = useQueryClient()
  return useMutation({
    // Shown where it was asked (the timer block): the button turns into the confirmation.
    meta: { silentError: true },
    mutationFn: ({ projectId, message }: { projectId: number; message?: string }) => requestExtension(projectId, message),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: extensionRequestsKey }),
  })
}

export function useExtensionRequests(enabled: boolean) {
  return useQuery({ queryKey: extensionRequestsKey, queryFn: fetchExtensionRequests, enabled })
}

export function useApproveExtension() {
  const refresh = useRefreshBags()
  return useMutation({
    meta: { successMessage: 'Ampliación aprobada.' },
    mutationFn: ({ requestId, input }: { requestId: number; input: AllocationInput }) => approveExtension(requestId, input),
    onSuccess: refresh,
  })
}

export function useRejectExtension() {
  const queryClient = useQueryClient()
  return useMutation({
    meta: { successMessage: 'Pedido rechazado.' },
    mutationFn: (requestId: number) => rejectExtension(requestId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: extensionRequestsKey }),
  })
}
