import { api, dataOf, type ApiEnvelope } from '@/lib/api'
import type { Dashboard } from './types'

export async function fetchDashboard(): Promise<Dashboard> {
  return dataOf(api.get<ApiEnvelope<Dashboard>>('/me/dashboard'))
}
