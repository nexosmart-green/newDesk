import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import { createShiftRecurrences, deleteShiftRecurrence, endShiftRecurrence, fetchShiftRecurrences } from './recurrencesApi'
import type { ShiftRecurrenceInput } from './recurrenceTypes'

const recurrencesKey = ['shifts', 'recurrences'] as const

export function useShiftRecurrences() {
  return useQuery({ queryKey: recurrencesKey, queryFn: fetchShiftRecurrences })
}

/** A rule writes days: the weekly grid, everyone's calendar and the team calendar move with it. */
function useRefreshAfterRecurrence() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: ['shifts'] }),
      queryClient.invalidateQueries({ queryKey: ['calendar'] }),
    ])
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
