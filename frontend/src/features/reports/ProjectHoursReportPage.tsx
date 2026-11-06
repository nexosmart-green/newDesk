import type { ReactNode } from 'react'
import { Link, useParams } from 'react-router'
import { PageState } from '@/components/ui/PageState'
import { useCan } from '@/features/auth/useAuth'
import { HoursBar } from '@/features/hours/HoursBar'
import { formatDuration } from '@/lib/duration'
import { ReportPeriodPicker } from './ReportPeriodPicker'
import { bagOf, REPORTS_PERMISSION } from './types'
import { useProjectHoursReport, useReportPeriod } from './useReports'

interface BreakdownRow {
  key: string | number
  label: ReactNode
  seconds: number
}

function BreakdownTable({ title, column, rows }: { title: string; column: string; rows: BreakdownRow[] }) {
  return (
    <section className="space-y-2">
      <h2 className="font-semibold">{title}</h2>
      {rows.length === 0 ? (
        <p className="text-sm text-muted">Sin horas en el período.</p>
      ) : (
        <table className="w-full rounded-lg border border-border bg-surface text-sm">
          <caption className="sr-only">{title}</caption>
          <thead className="border-b border-border text-left text-muted">
            <tr>
              <th scope="col" className="px-3 py-2">{column}</th>
              <th scope="col" className="px-3 py-2 text-right">Horas</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {rows.map((row) => (
              <tr key={row.key}>
                <td className="px-3 py-2">{row.label}</td>
                <td className="px-3 py-2 text-right font-mono">{formatDuration(row.seconds)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </section>
  )
}

/** RF-10.01 detail: one project's hours in the period, by person and by task. */
export function ProjectHoursReportPage() {
  const canSee = useCan(REPORTS_PERMISSION)
  const projectId = Number(useParams().projectId)
  const { period, search } = useReportPeriod()
  const report = useProjectHoursReport(projectId, period, canSee)

  if (!canSee) return <p className="text-muted">No tenés acceso a los reportes.</p>

  return (
    <section className="mx-auto max-w-4xl space-y-4">
      <Link to={`/reportes/horas${search ? `?${search}` : ''}`} className="text-sm text-accent hover:underline">
        ← Todos los proyectos
      </Link>
      <h1 className="text-lg font-semibold">{report.data ? `Horas de ${report.data.project.name}` : 'Horas del proyecto'}</h1>
      <ReportPeriodPicker applied={report.data?.period} />
      {!report.data ? (
        <PageState isPending={report.isPending} error={report.error} />
      ) : (
        <>
          <div className="space-y-2 rounded-lg border border-border bg-surface p-4">
            <p className="text-sm">
              En el período: <strong className="font-mono">{formatDuration(report.data.consumed_in_period_seconds)}</strong>
            </p>
            <HoursBar bag={bagOf(report.data)} />
          </div>
          <div className="grid gap-4 md:grid-cols-2">
            <BreakdownTable
              title="Por persona"
              column="Persona"
              rows={report.data.by_person.map((row) => ({ key: row.user_id, label: row.name, seconds: row.seconds }))}
            />
            <BreakdownTable
              title="Por tarea"
              column="Tarea"
              rows={report.data.by_task.map((row) => ({
                key: row.code,
                label: (
                  <>
                    <Link to={`/tareas/${row.code}`} className="font-mono uppercase text-accent hover:underline">
                      {row.code}
                    </Link>{' '}
                    {row.title}
                  </>
                ),
                seconds: row.seconds,
              }))}
            />
          </div>
        </>
      )}
    </section>
  )
}
