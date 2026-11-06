import { TextField } from '@/components/ui/TextField'
import type { ReportPeriod } from './types'
import { useReportPeriod } from './useReports'

/** From/to of the report. Shows the period the backend actually used (default: this month up to today). */
export function ReportPeriodPicker({ applied }: { applied: ReportPeriod | undefined }) {
  const { period, setPeriodValue } = useReportPeriod()

  return (
    <fieldset className="flex flex-wrap items-end gap-3">
      <legend className="sr-only">Período</legend>
      <TextField
        label="Desde"
        type="date"
        value={period.from ?? applied?.from ?? ''}
        onChange={(event) => setPeriodValue('desde', event.target.value)}
      />
      <TextField
        label="Hasta"
        type="date"
        value={period.to ?? applied?.to ?? ''}
        onChange={(event) => setPeriodValue('hasta', event.target.value)}
      />
    </fieldset>
  )
}
