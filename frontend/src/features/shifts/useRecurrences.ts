import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import { createShiftRecurrences, deleteShiftRecurrence, endShiftRecurrence, fetchShiftRecurrences } from './recurrencesApi'
import { calendarKey, shiftRecurrencesKey, shiftsKey } from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'
import type { ShiftRecurrenceInput } from './recurrenceTypes'

export function useShiftRecurrences() {
  return useQuery({ queryKey: shiftRecurrencesKey, queryFn: fetchShiftRecurrences })
}

/**
 * A rule writes days: the rules panel waits for its list; the weekly grid, everyone's calendar and the
 * team calendar follow behind.
 */
function useRefreshAfterRecurrence() {
  const queryClient = useQueryClient()
  return () => refreshQueries(queryClient, { now: [shiftRecurrencesKey], later: [shiftsKey, calendarKey] })
}

const announce = (result: { message: string | null }, fallback: string) => toast.success(result.message ?? fallback)

export function useCreateShiftRecurrences() {
  const refresh = useRefreshAfterRecurrence()
  return useMutation({
    mutationFn: (input: ShiftRecurrenceInput) => createShiftRecurrences(input),
    onSuccess: (result) => {
      announce(result, 'Turno fijo creado.')
      return refresh()
    },
  })
}

export function useEndShiftRecurrence() {
  const refresh = useRefreshAfterRecurrence()
  return useMutation({
    mutationFn: ({ id, endsOn }: { id: number; endsOn: string }) => endShiftRecurrence(id, endsOn),
    onSuccess: (result) => {
      announce(result, 'Turno fijo finalizado.')
      return refresh()
    },
  })
}

export function useDeleteShiftRecurrence() {
  const refresh = useRefreshAfterRecurrence()
  return useMutation({
    mutationFn: (id: number) => deleteShiftRecurrence(id),
    onSuccess: (result) => {
      announce(result, 'Turno fijo borrado.')
      return refresh()
    },
  })
}
