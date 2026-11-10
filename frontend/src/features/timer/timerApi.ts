import { api, dataOf, envelopeOf, type ApiEnvelope } from '@/lib/api'
import type { CorrectEntryInput, DayClock, DayPartInput, DaySplitResult, ManualEntryInput, TimeEntry, TimerState } from './types'

const taskPath = (code: string) => `/tasks/${encodeURIComponent(code)}`

export async function fetchTimer(): Promise<TimerState> {
  return dataOf(api.get<ApiEnvelope<TimerState>>('/me/timer'))
}

/** RF-05.02/16: with another timer running the backend needs `previous_comment` to close it. */
export async function startTimer(taskCode: string, previousComment?: string): Promise<TimeEntry> {
  const body = previousComment ? { previous_comment: previousComment } : {}
  return dataOf(api.post<ApiEnvelope<TimeEntry>>(`${taskPath(taskCode)}/timer`, body))
}

export async function stopTimer(comment: string): Promise<TimeEntry> {
  return dataOf(api.post<ApiEnvelope<TimeEntry>>('/me/timer/stop', { comment }))
}

/** RF-05.17: comment of an automatically cut entry. */
export async function completeEntryComment(entryId: number, comment: string): Promise<TimeEntry> {
  return dataOf(api.put<ApiEnvelope<TimeEntry>>(`/time-entries/${entryId}/comment`, { comment }))
}

/** RF-05.09 manual hours. */
export async function logManualTime(taskCode: string, input: ManualEntryInput): Promise<TimeEntry> {
  return dataOf(api.post<ApiEnvelope<TimeEntry>>(`${taskPath(taskCode)}/time-entries`, input))
}

/** RF-05.15: the backend decides who may and within which window. */
export async function correctTimeEntry(entryId: number, input: CorrectEntryInput): Promise<TimeEntry> {
  return dataOf(api.put<ApiEnvelope<TimeEntry>>(`/time-entries/${entryId}`, input))
}

export async function deleteTimeEntry(entryId: number): Promise<string | null> {
  return (await envelopeOf(api.delete<ApiEnvelope<null>>(`/time-entries/${entryId}`))).message
}

/** RF-05.19 day clock. */
export async function startDay(): Promise<DayClock> {
  return dataOf(api.post<ApiEnvelope<DayClock>>('/me/day-clock'))
}

export async function stopDay(): Promise<DayClock> {
  return dataOf(api.post<ApiEnvelope<DayClock>>('/me/day-clock/stop'))
}

/** Empty `parts` = nothing to count that day (the clock is discarded). */
export async function splitDay(dayClockId: number, parts: DayPartInput[]): Promise<ApiEnvelope<DaySplitResult>> {
  return envelopeOf(api.post<ApiEnvelope<DaySplitResult>>(`/me/day-clocks/${dayClockId}/split`, { parts }))
}
