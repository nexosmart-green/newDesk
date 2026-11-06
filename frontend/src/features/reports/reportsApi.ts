import { api, dataOf, queryParams, type ApiEnvelope } from '@/lib/api'
import type { HoursReport, ProjectHoursReport, ReportPeriodQuery } from './types'

export async function fetchHoursReport(period: ReportPeriodQuery): Promise<HoursReport> {
  return dataOf(api.get<ApiEnvelope<HoursReport>>('/reports/hours', { params: queryParams({ ...period }) }))
}

export async function fetchProjectHoursReport(projectId: number, period: ReportPeriodQuery): Promise<ProjectHoursReport> {
  return dataOf(
    api.get<ApiEnvelope<ProjectHoursReport>>(`/reports/hours/projects/${projectId}`, { params: queryParams({ ...period }) }),
  )
}
