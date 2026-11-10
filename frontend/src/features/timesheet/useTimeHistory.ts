import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import { downloadBlob } from '@/lib/csv'
import { exportMyTimeEntries, fetchMyTimeEntries } from './historyApi'
import type { HistoryFilters } from './types'

/** Under 'timesheet': every timer/entry mutation already invalidates it. */
export function useTimeHistory(filters: HistoryFilters, page: number, enabled: boolean) {
  return useQuery({
    enabled,
    queryKey: ['timesheet', 'history', filters, page],
    queryFn: () => fetchMyTimeEntries(filters, page),
    placeholderData: keepPreviousData,
  })
}

export function useExportTimeHistory() {
  return useMutation({
    mutationFn: (filters: HistoryFilters) => exportMyTimeEntries(filters),
    onSuccess: ({ blob, filename }) => {
      downloadBlob(blob, filename)
      toast.success(`Descargaste ${filename}.`)
    },
  })
}
