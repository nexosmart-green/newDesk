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
import { calendarFeedKey, calendarKey, holidaysKey, myCalendarKey, shiftsKey } from '@/lib/queryKeys'
import { refreshQueries } from '@/lib/refreshQueries'
import type { HolidayInput } from './types'

export function useMyCalendar(from: string, to: string, enabled = true) {
  return useQuery({
    queryKey: myCalendarKey(from, to),
    queryFn: () => fetchMyCalendar(from, to),
    placeholderData: keepPreviousData,
    enabled,
  })
}

export function useHolidays(year: number) {
  return useQuery({
    queryKey: holidaysKey(year),
    queryFn: () => fetchHolidays(year),
    placeholderData: keepPreviousData,
  })
}

export function useCalendarFeedStatus() {
  return useQuery({ queryKey: calendarFeedKey, queryFn: fetchCalendarFeedStatus })
}

function useRefreshFeedStatus() {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: calendarFeedKey })
}

export function useIssueCalendarFeed() {
  const refresh = useRefreshFeedStatus()
  // gcTime 0: the secret URL is shown once and must not linger in the mutation cache.
  return useMutation({ mutationFn: issueCalendarFeed, gcTime: 0, onSuccess: refresh })
}

export function useRevokeCalendarFeed() {
  const refresh = useRefreshFeedStatus()
  return useMutation({
    mutationFn: revokeCalendarFeed,
    onSuccess: refresh,
    meta: { successMessage: 'Suscripción revocada: la URL anterior ya no funciona.' },
  })
}

/** The holidays tab waits for its list; every calendar and the team grid show them too and follow behind. */
function useRefreshHolidays() {
  const queryClient = useQueryClient()
  return () => refreshQueries(queryClient, { now: [[...calendarKey, 'holidays']], later: [calendarKey, shiftsKey] })
}

export function useCreateHoliday() {
  const refresh = useRefreshHolidays()
  return useMutation({ mutationFn: (input: HolidayInput) => createHoliday(input), onSuccess: refresh, meta: { successMessage: 'Feriado guardado.' } })
}

export function useDeleteHoliday() {
  const refresh = useRefreshHolidays()
  return useMutation({ mutationFn: (id: number) => deleteHoliday(id), onSuccess: refresh, meta: { successMessage: 'Feriado borrado.' } })
}
