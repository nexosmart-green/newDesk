import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { isLocalDateString } from '@/lib/week'
import { hoursReportsKey } from '@/lib/queryKeys'
import { useSearchParamPatch } from '@/lib/useSearchParamPatch'
import { fetchHoursReport, fetchProjectHoursReport } from './reportsApi'
import type { ReportPeriodQuery } from './types'

/** `?desde=&hasta=` (bookmarkable, kept when drilling into a project). Missing = backend default (this month). */
export function useReportPeriod() {
  const [searchParams, patch] = useSearchParamPatch()
  const from = searchParams.get('desde')
  const to = searchParams.get('hasta')
  const period: ReportPeriodQuery = {
    ...(isLocalDateString(from) ? { from } : {}),
    ...(isLocalDateString(to) ? { to } : {}),
  }

  const setPeriodValue = (key: 'desde' | 'hasta', value: string) => patch({ [key]: value || null })

  return { period, search: searchParams.toString(), setPeriodValue }
}

export function useHoursReport(period: ReportPeriodQuery, enabled = true) {
  return useQuery({
    enabled,
    queryKey: [...hoursReportsKey, period],
    queryFn: () => fetchHoursReport(period),
    placeholderData: keepPreviousData,
  })
}

export function useProjectHoursReport(projectId: number, period: ReportPeriodQuery, enabled = true) {
  return useQuery({
    enabled,
    queryKey: [...hoursReportsKey, 'project', projectId, period],
    queryFn: () => fetchProjectHoursReport(projectId, period),
    placeholderData: keepPreviousData,
  })
}
