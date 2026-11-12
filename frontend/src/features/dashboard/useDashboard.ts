import { useQuery } from '@tanstack/react-query'
import { dashboardKey } from '@/lib/queryKeys'
import { fetchDashboard } from './dashboardApi'

/** Task and time mutations invalidate `dashboard`, so the counters and the week follow what you do. */
export function useDashboard() {
  return useQuery({ queryKey: dashboardKey, queryFn: fetchDashboard })
}
