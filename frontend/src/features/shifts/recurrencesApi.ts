import { api, dataOf, envelopeOf, type ApiEnvelope } from '@/lib/api'
import type { CreateRecurrencesResult, CreatedRecurrence, RemovedDaysResult, ShiftRecurrence, ShiftRecurrenceInput } from './recurrenceTypes'

export async function fetchShiftRecurrences(): Promise<ShiftRecurrence[]> {
  return dataOf(api.get<ApiEnvelope<ShiftRecurrence[]>>('/shift-recurrences'))
}

export async function createShiftRecurrences(input: ShiftRecurrenceInput): Promise<CreateRecurrencesResult> {
  const envelope = await envelopeOf(api.post<ApiEnvelope<CreatedRecurrence[]>>('/shift-recurrences', input))
  return { message: envelope.message, created: envelope.data }
}

/** "Finalizar": the generated days after `endsOn` go away. */
export async function endShiftRecurrence(id: number, endsOn: string): Promise<RemovedDaysResult> {
  const envelope = await envelopeOf(
    api.put<ApiEnvelope<{ recurrence: ShiftRecurrence; removed: number }>>(`/shift-recurrences/${id}/end`, { ends_on: endsOn }),
  )
  return { message: envelope.message, removed: envelope.data.removed }
}

/** Days up to today stay as plain shifts; the future ones go away. */
export async function deleteShiftRecurrence(id: number): Promise<RemovedDaysResult> {
  const envelope = await envelopeOf(api.delete<ApiEnvelope<{ removed: number }>>(`/shift-recurrences/${id}`))
  return { message: envelope.message, removed: envelope.data.removed }
}
