import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useSearchParams } from 'react-router'
import { isLocalDateString } from '@/features/timesheet/week'
import { fetchHoursReport, fetchProjectHoursReport } from './reportsApi'
import type { ReportPeriodQuery } from './types'

const reportsKey = ['reports', 'hours'] as const

/** `?desde=&hasta=` (bookmarkable, kept when drilling into a project). Missing = backend default (this month). */
export function useReportPeriod() {
  const [searchParams, setSearchParams] = useSearchParams()
  const from = searchParams.get('desde')
  const to = searchParams.get('hasta')
  const period: ReportPeriodQuery = {
    ...(isLocalDateString(from) ? { from } : {}),
    ...(isLocalDateString(to) ? { to } : {}),
  }

  function setPeriodValue(key: 'desde' | 'hasta', value: string) {
    setSearchParams(
      (current) => {
        const next = new URLSearchParams(current)
        if (value) next.set(key, value)
        else next.delete(key)
        return next
      },
      { replace: true },
    )
  }

  return { period, search: searchParams.toString(), setPeriodValue }
}

export function useHoursReport(period: ReportPeriodQuery, enabled = true) {
  return useQuery({
    enabled,
    queryKey: [...reportsKey, period],
    queryFn: () => fetchHoursReport(period),
    placeholderData: keepPreviousData,
  })
}

export function useProjectHoursReport(projectId: number, period: ReportPeriodQuery, enabled = true) {
  return useQuery({
    enabled,
    queryKey: [...reportsKey, 'project', projectId, period],
    queryFn: () => fetchProjectHoursReport(projectId, period),
    placeholderData: keepPreviousData,
  })
}
