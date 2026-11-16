import { Link } from 'react-router'
import { PageHeader } from '@/components/ui/PageHeader'
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

const cellClass = 'px-4 py-2.5'
const hoursCellClass = `${cellClass} tabular text-right`

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
    <section className="max-w-6xl space-y-6">
      <PageHeader
        title="Reporte de horas"
        actions={
          <Button variant="secondary" disabled={!report.data} onClick={() => report.data && exportCsv(report.data)}>
            Exportar CSV
          </Button>
        }
      />
      <ReportPeriodPicker applied={report.data?.period} />
      {!report.data ? (
        <PageState isPending={report.isPending} error={report.error} shape="table" onRetry={() => void report.refetch()} />
      ) : report.data.projects.length === 0 ? (
        <StaleContent stale={report.isPlaceholderData}>
          <p className="text-sm text-muted">No hay proyectos activos ni horas en el período.</p>
        </StaleContent>
      ) : (
        <StaleContent stale={report.isPlaceholderData} className="overflow-x-auto rounded-lg border border-line bg-surface">
          <table className="w-full min-w-[40rem] text-sm">
            <caption className="sr-only">Horas por proyecto</caption>
            <thead className="border-b border-line text-left text-[13px] text-muted">
              <tr>
                <th scope="col" className={cellClass}>Proyecto</th>
                <th scope="col" className={`${cellClass} text-right font-normal`}>Vendidas</th>
                <th scope="col" className={`${cellClass} text-right font-normal`}>Consumidas</th>
                <th scope="col" className={`${cellClass} text-right font-normal`}>En el período</th>
                <th scope="col" className={`${cellClass} font-normal`}>% consumido</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {report.data.projects.map((row) => (
                <tr key={row.project.id}>
                  <th scope="row" className={`${cellClass} text-left font-normal`}>
                    <span className="flex items-baseline gap-2">
                      <Link to={`/reportes/horas/proyectos/${row.project.id}${search ? `?${search}` : ''}`} className="font-medium text-accent hover:underline">
                        {row.project.name}
                      </Link>
                      <span className="text-[13px] text-muted">{row.project.prefix}</span>
                    </span>
                    {row.client && <span className="block text-[13px] text-muted">{row.client}</span>}
                  </th>
                  <td className={hoursCellClass}>{row.allocated_seconds === null ? <span className="text-muted">Sin tope</span> : formatDuration(row.allocated_seconds)}</td>
                  <td className={hoursCellClass}>{formatDuration(row.consumed_total_seconds)}</td>
                  <td className={hoursCellClass}>{formatDuration(row.consumed_in_period_seconds)}</td>
                  <td className={cellClass}>{row.allocated_seconds !== null && <HoursBar bag={bagOf(row)} compact />}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </StaleContent>
      )}
    </section>
  )
}
