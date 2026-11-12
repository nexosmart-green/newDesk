import { api, dataOf, type ApiEnvelope } from '@/lib/api'
import type { TeamCalendar } from './teamCalendarTypes'

/** At most 31 days. 403 = the person neither manages shifts nor has anyone in charge. */
export async function fetchTeamCalendar(from: string, to: string): Promise<TeamCalendar> {
  return dataOf(api.get<ApiEnvelope<TeamCalendar>>('/team-calendar', { params: { from, to } }))
}
