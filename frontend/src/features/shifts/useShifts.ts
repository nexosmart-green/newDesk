import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  assignShifts,
  copyPreviousWeek,
  createShiftTemplate,
  deleteShiftTemplate,
  fetchShiftGrid,
  fetchShiftTemplates,
  updateShiftTemplate,
} from './shiftsApi'
import type { AssignShiftsInput, ShiftTemplateInput } from './types'

const shiftsKey = ['shifts'] as const
const templatesKey = [...shiftsKey, 'templates'] as const

export function useShiftTemplates() {
  return useQuery({ queryKey: templatesKey, queryFn: fetchShiftTemplates, staleTime: 5 * 60_000 })
}

export function useShiftGrid(from: string, to: string) {
  return useQuery({
    queryKey: [...shiftsKey, 'grid', from, to],
    queryFn: () => fetchShiftGrid(from, to),
    placeholderData: keepPreviousData,
  })
}

/** A shift change moves the team grid and everyone's calendar. */
function useRefreshShifts() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: shiftsKey }),
      queryClient.invalidateQueries({ queryKey: ['calendar'] }),
    ])
}

export function useAssignShifts() {
  const refresh = useRefreshShifts()
  return useMutation({
    // A free grid selection may need several requests (one per weekday set, see `selectionToAssignments`).
    mutationFn: async (inputs: AssignShiftsInput[]) => {
      let cells = 0
      for (const input of inputs) cells += await assignShifts(input)
      return cells
    },
    onSettled: refresh,
  })
}

export function useCopyPreviousWeek() {
  const refresh = useRefreshShifts()
  return useMutation({ mutationFn: (weekStart: string) => copyPreviousWeek(weekStart), onSuccess: refresh })
}

export function useSaveShiftTemplate() {
  const refresh = useRefreshShifts()
  return useMutation({
    mutationFn: ({ id, input }: { id: number | null; input: ShiftTemplateInput }) =>
      id === null ? createShiftTemplate(input) : updateShiftTemplate(id, input),
    onSuccess: refresh,
  })
}

export function useDeleteShiftTemplate() {
  const refresh = useRefreshShifts()
  return useMutation({ mutationFn: (id: number) => deleteShiftTemplate(id), onSuccess: refresh })
}
