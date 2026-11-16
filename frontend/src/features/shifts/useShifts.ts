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
import { calendarKey, shiftGridKey, shiftsKey, shiftTemplatesKey } from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'
import type { AssignShiftsInput, ShiftCellsInput, ShiftTemplate, ShiftTemplateInput } from './types'

export const daysLabel = (count: number) => (count === 1 ? '1 día' : `${count} días`)
const assigned = (cells: number) => toast.success(`Listo: ${daysLabel(cells)} actualizados.`)

export function useShiftTemplates() {
  return useQuery({ queryKey: shiftTemplatesKey, queryFn: fetchShiftTemplates, staleTime: 5 * 60_000 })
}

export function useShiftGrid(from: string, to: string) {
  return useQuery({
    queryKey: shiftGridKey(from, to),
    queryFn: () => fetchShiftGrid(from, to),
    placeholderData: keepPreviousData,
  })
}

/** A shift change moves the weekly grid (awaited: the dialog closes onto it) and everyone's calendar (behind). */
function useRefreshShifts() {
  const queryClient = useQueryClient()
  return () => refreshQueries(queryClient, { now: [[...shiftsKey, 'grid']], later: [shiftsKey, calendarKey] })
}

/** A template change shows in the templates panel (awaited); grids and calendars label shifts with it (behind). */
function useRefreshTemplates() {
  const queryClient = useQueryClient()
  return () => refreshQueries(queryClient, { now: [shiftTemplatesKey], later: [shiftsKey, calendarKey] })
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
      else toast.success(`Semana reemplazada con ${copied === 1 ? '1 turno' : `${copied} turnos`} de la semana anterior.`)
      return refresh()
    },
  })
}

export function useSaveShiftTemplate() {
  const refresh = useRefreshTemplates()
  return useMutation({
    mutationFn: ({ id, input }: { id: number | null; input: ShiftTemplateInput }) =>
      id === null ? createShiftTemplate(input) : updateShiftTemplate(id, input),
    onSuccess: (_template, { id, input }) => {
      toast.success(`Plantilla «${input.name}» ${id === null ? 'creada' : 'guardada'}.`)
      return refresh()
    },
  })
}

export function useDeleteShiftTemplate() {
  const refresh = useRefreshTemplates()
  return useMutation({
    mutationFn: (template: ShiftTemplate) => deleteShiftTemplate(template.id),
    onSuccess: (_result, template) => {
      toast.success(`Plantilla «${template.name}» borrada.`)
      return refresh()
    },
  })
}
