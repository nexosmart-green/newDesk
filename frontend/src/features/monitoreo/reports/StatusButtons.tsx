import { errorMessage } from '../api/monitoringApi'
import { useReportStatus } from '../api/useReports'
import type { ReportDetail, ReportStatus } from '../api/types'
import { ErrorNote, Spinner } from '../components/Panel'
import { formatRelative } from '../lib/format'
import { reportStatusMeta } from '../lib/reports'

const statuses: ReportStatus[] = ['received', 'in_review', 'resolved']

// Recibido / En revisión / Resuelto: en la página del reporte y en su ventana flotante.
export function StatusButtons({ report }: { report: ReportDetail }) {
  const update = useReportStatus(report.id)
  return (
    <div className="space-y-2">
      <div role="group" aria-label="Estado del reporte" className="flex flex-wrap gap-2">
        {statuses.map((status) => {
          const current = report.status === status
          return (
            <button
              key={status}
              type="button"
              aria-pressed={current}
              className={`btn btn-sm ${current ? 'btn-primary' : ''}`}
              disabled={update.isPending}
              onClick={() => !current && update.mutate(status)}
            >
              {update.isPending && update.variables === status && <Spinner />}
              {reportStatusMeta[status].label}
            </button>
          )
        })}
        {report.resolved_at && <span className="self-center text-xs text-muted">Resuelto {formatRelative(report.resolved_at)}</span>}
      </div>
      {update.isError && <ErrorNote>{errorMessage(update.error)}</ErrorNote>}
    </div>
  )
}
