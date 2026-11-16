import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { parseLocalDate, toLocalDateString } from '@/lib/week'
import { teamCalendarKey } from '@/lib/queryKeys'
import { fetchTeamCalendar } from './teamCalendarApi'
import { useNow } from './useNow'

export function useTeamCalendar(from: string, to: string) {
  return useQuery({
    queryKey: teamCalendarKey(from, to),
    queryFn: () => fetchTeamCalendar(from, to),
    placeholderData: keepPreviousData,
  })
}

function addDays(date: string, days: number): string {
  const day = parseLocalDate(date)
  day.setDate(day.getDate() + days)
  return toLocalDateString(day)
}

/**
 * Browser yesterday..tomorrow: covers "today" in any company timezone, plus yesterday's night shifts
 * that may still be running. Recomputed on every render, so a tab left open past midnight moves on to the
 * new day within a minute (the key changes and the query refetches).
 */
export function useTeamToday() {
  const today = toLocalDateString(useNow())
  return useTeamCalendar(addDays(today, -1), addDays(today, 1))
}
