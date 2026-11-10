import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import {
  assignShifts,
  copyPreviousWeek,
  createShiftTemplate,
  deleteShiftTemplate,
  fetchShiftGrid,
  fetchShiftTemplates,
  setShiftCells,
  updateShiftTemplate,
} from './shiftsApi'
import type { AssignShiftsInput, ShiftCellsInput, ShiftTemplateInput } from './types'

const shiftsKey = ['shifts'] as const
const templatesKey = [...shiftsKey, 'templates'] as const

export const daysLabel = (count: number) => (count === 1 ? '1 día' : `${count} días`)
const assigned = (cells: number) => toast.success(`Listo: ${daysLabel(cells)} actualizados.`)

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
    mutationFn: (input: AssignShiftsInput) => assignShifts(input),
    onSuccess: (cells) => {
      assigned(cells)
      return refresh()
    },
  })
}

export function useSetShiftCells() {
  const refresh = useRefreshShifts()
  return useMutation({
    mutationFn: (input: ShiftCellsInput) => setShiftCells(input),
    onSuccess: (cells) => {
      assigned(cells)
      return refresh()
    },
  })
}

export function useCopyPreviousWeek() {
  const refresh = useRefreshShifts()
  return useMutation({
    mutationFn: (weekStart: string) => copyPreviousWeek(weekStart),
    onSuccess: (copied) => {
      if (copied === 0) toast.info('La semana anterior no tenía turnos.')
      else toast.success(`Se copiaron ${copied} turnos.`)
      return refresh()
    },
  })
}

export function useSaveShiftTemplate() {
  const refresh = useRefreshShifts()
  return useMutation({
    mutationFn: ({ id, input }: { id: number | null; input: ShiftTemplateInput }) =>
      id === null ? createShiftTemplate(input) : updateShiftTemplate(id, input),
    meta: { successMessage: 'Plantilla guardada.' },
    onSuccess: refresh,
  })
}

export function useDeleteShiftTemplate() {
  const refresh = useRefreshShifts()
  return useMutation({
    mutationFn: (id: number) => deleteShiftTemplate(id),
    meta: { successMessage: 'Plantilla borrada.' },
    onSuccess: refresh,
  })
}
