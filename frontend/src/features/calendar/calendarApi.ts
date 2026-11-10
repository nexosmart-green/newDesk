import type { Holiday } from '@/features/shifts/types'
import { api, dataOf, type ApiEnvelope } from '@/lib/api'
import type { HolidayInput, MyCalendar } from './types'

export async function fetchMyCalendar(from: string, to: string): Promise<MyCalendar> {
  return dataOf(api.get<ApiEnvelope<MyCalendar>>('/me/calendar', { params: { from, to } }))
}

/** RF-06.07: whether I have a live feed URL (the URL itself is never returned again). */
export async function fetchCalendarFeedStatus(): Promise<boolean> {
  return (await dataOf(api.get<ApiEnvelope<{ active: boolean }>>('/me/calendar-feed'))).active
}

/** RF-06.07: issues (or rotates) the personal iCal URL. It is only returned here, once. */
export async function issueCalendarFeed(): Promise<string> {
  return (await dataOf(api.post<ApiEnvelope<{ url: string }>>('/me/calendar-feed'))).url
}

export async function revokeCalendarFeed(): Promise<void> {
  await dataOf(api.delete<ApiEnvelope<null>>('/me/calendar-feed'))
}

/** RF-06.06: the company's holidays (national and own days off) of one year, by date. */
export async function fetchHolidays(year: number): Promise<Holiday[]> {
  return dataOf(api.get<ApiEnvelope<Holiday[]>>('/holidays', { params: { year } }))
}

export async function createHoliday(input: HolidayInput): Promise<Holiday> {
  return dataOf(api.post<ApiEnvelope<Holiday>>('/holidays', input))
}

export async function deleteHoliday(id: number): Promise<void> {
  await dataOf(api.delete<ApiEnvelope<null>>(`/holidays/${id}`))
}
