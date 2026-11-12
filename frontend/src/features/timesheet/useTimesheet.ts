import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { timesheetWeekKey } from '@/lib/queryKeys'
import { fetchTimesheet } from './timesheetApi'

/** Key starts with 'timesheet': every timer mutation invalidates it. */
export function useTimesheet(from: string, to: string) {
  return useQuery({
    queryKey: timesheetWeekKey(from, to),
    queryFn: () => fetchTimesheet(from, to),
    placeholderData: keepPreviousData,
  })
}
