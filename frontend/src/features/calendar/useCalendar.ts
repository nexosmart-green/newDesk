import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  createHoliday,
  deleteHoliday,
  fetchCalendarFeedStatus,
  fetchHolidays,
  fetchMyCalendar,
  issueCalendarFeed,
  revokeCalendarFeed,
} from './calendarApi'
import type { HolidayInput } from './types'

const calendarKey = ['calendar'] as const
const feedKey = ['calendar-feed'] as const

export function useMyCalendar(from: string, to: string) {
  return useQuery({
    queryKey: [...calendarKey, 'mine', from, to],
    queryFn: () => fetchMyCalendar(from, to),
    placeholderData: keepPreviousData,
  })
}

export function useHolidays(year: number) {
  return useQuery({
    queryKey: [...calendarKey, 'holidays', year],
    queryFn: () => fetchHolidays(year),
    placeholderData: keepPreviousData,
  })
}

export function useCalendarFeedStatus() {
  return useQuery({ queryKey: feedKey, queryFn: fetchCalendarFeedStatus })
}

function useRefreshFeedStatus() {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: feedKey })
}

export function useIssueCalendarFeed() {
  const refresh = useRefreshFeedStatus()
  // gcTime 0: the secret URL is shown once and must not linger in the mutation cache.
  return useMutation({ mutationFn: issueCalendarFeed, gcTime: 0, onSuccess: refresh })
}

export function useRevokeCalendarFeed() {
  const refresh = useRefreshFeedStatus()
  return useMutation({ mutationFn: revokeCalendarFeed, onSuccess: refresh })
}

/** Holidays show in every calendar and in the team grid. */
function useRefreshHolidays() {
  const queryClient = useQueryClient()
  return () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: calendarKey }),
      queryClient.invalidateQueries({ queryKey: ['shifts'] }),
    ])
}

export function useCreateHoliday() {
  const refresh = useRefreshHolidays()
  return useMutation({ mutationFn: (input: HolidayInput) => createHoliday(input), onSuccess: refresh })
}

export function useDeleteHoliday() {
  const refresh = useRefreshHolidays()
  return useMutation({ mutationFn: (id: number) => deleteHoliday(id), onSuccess: refresh })
}
