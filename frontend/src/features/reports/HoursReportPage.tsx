import { Link } from 'react-router'
import { Button } from '@/components/ui/Button'
import { PageState } from '@/components/ui/PageState'
import { StaleContent } from '@/components/ui/StaleContent'
import { useCan } from '@/features/auth/useAuth'
import { HoursBar } from '@/features/hours/HoursBar'
import { downloadCsv, toCsv } from '@/lib/csv'
import { formatDuration } from '@/lib/duration'
import { ReportPeriodPicker } from './ReportPeriodPicker'
import { bagOf, REPORTS_PERMISSION, type HoursReport } from './types'
import { useHoursReport, useReportPeriod } from './useReports'

const cellClass = 'px-3 py-2'

function exportCsv(report: HoursReport) {
  const hours = (seconds: number | null) => (seconds === null ? '' : formatDuration(seconds))
  const rows = report.projects.map((row) => [
    row.project.name,
    row.client,
    hours(row.allocated_seconds),
    hours(row.consumed_total_seconds),
    hours(row.consumed_in_period_seconds),
    row.percent_consumed,
  ])
  const header = ['Proyecto', 'Cliente', 'Vendidas', 'Consumidas (total)', 'Consumidas en el período', '% consumido']
  downloadCsv(`horas-${report.period.from}-a-${report.period.to}.csv`, toCsv([header, ...rows]))
}

/** RF-10.01: hours per project and period — sold / consumed / % (`projects.manage`). */
export function HoursReportPage() {
  const canSee = useCan(REPORTS_PERMISSION)
  const { period, search } = useReportPeriod()
  const report = useHoursReport(period, canSee)

  if (!canSee) return <p className="text-muted">No tenés acceso a los reportes.</p>

  return (
    <section className="mx-auto max-w-6xl space-y-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <h1 className="text-lg font-semibold">Reporte de horas</h1>
        <Button variant="secondary" disabled={!report.data} onClick={() => report.data && exportCsv(report.data)}>
          Exportar CSV
        </Button>
      </div>
      <ReportPeriodPicker applied={report.data?.period} />
      {!report.data ? (
        <PageState isPending={report.isPending} error={report.error} shape="table" onRetry={() => void report.refetch()} />
      ) : report.data.projects.length === 0 ? (
        <StaleContent stale={report.isPlaceholderData}>
          <p className="text-sm text-muted">No hay proyectos activos ni horas en el período.</p>
        </StaleContent>
      ) : (
        <StaleContent stale={report.isPlaceholderData} className="overflow-x-auto rounded-lg border border-border bg-surface">
          <table className="w-full min-w-[48rem] text-sm">
            <caption className="sr-only">Horas por proyecto</caption>
            <thead className="border-b border-border text-left text-muted">
              <tr>
                <th scope="col" className={cellClass}>Proyecto</th>
                <th scope="col" className={cellClass}>Cliente</th>
                <th scope="col" className={`${cellClass} text-right`}>Vendidas</th>
                <th scope="col" className={`${cellClass} text-right`}>Consumidas</th>
                <th scope="col" className={`${cellClass} text-right`}>En el período</th>
                <th scope="col" className={cellClass}>%</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {report.data.projects.map((row) => (
                <tr key={row.project.id}>
                  <th scope="row" className={`${cellClass} text-left font-medium`}>
                    <Link to={`/reportes/horas/proyectos/${row.project.id}${search ? `?${search}` : ''}`} className="text-accent hover:underline">
                      {row.project.name}
                    </Link>
                  </th>
                  <td className={cellClass}>{row.client ?? '—'}</td>
                  <td className={`${cellClass} text-right font-mono`}>{row.allocated_seconds === null ? 'Sin tope' : formatDuration(row.allocated_seconds)}</td>
                  <td className={`${cellClass} text-right font-mono`}>{formatDuration(row.consumed_total_seconds)}</td>
                  <td className={`${cellClass} text-right font-mono`}>{formatDuration(row.consumed_in_period_seconds)}</td>
                  <td className={cellClass}>
                    <HoursBar bag={bagOf(row)} compact />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </StaleContent>
      )}
    </section>
  )
}
