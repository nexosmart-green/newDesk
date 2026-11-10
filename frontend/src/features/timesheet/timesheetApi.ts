import { api, dataOf, type ApiEnvelope } from '@/lib/api'
import type { Timesheet } from './types'

export async function fetchTimesheet(from: string, to: string): Promise<Timesheet> {
  return dataOf(api.get<ApiEnvelope<Timesheet>>('/me/timesheet', { params: { from, to } }))
}
