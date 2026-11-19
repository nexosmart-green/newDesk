import type { UseQueryResult } from '@tanstack/react-query'
import { Download } from 'lucide-react'
import { Button } from '@/components/ui/Button'
import { PageState } from '@/components/ui/PageState'
import { StaleContent } from '@/components/ui/StaleContent'
import { formatDuration } from '@/lib/duration'
import { EntryList } from './EntryList'
import type { MyEntriesPage, MyEntryFilters } from './types'
import { useExportEntries } from './useHours'

interface MyHoursListProps {
  entries: UseQueryResult<MyEntriesPage>
  /** What the list was asked with: the CSV exports exactly that. */
  filters: MyEntryFilters
  onPageChange: (page: number) => void
}

/** The range's total and CSV over the entries themselves, each one fixable inside the window. */
export function MyHoursList({ entries, filters, onPageChange }: MyHoursListProps) {
  const exporter = useExportEntries()
  const page = entries.data

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-surface px-4 py-3">
        <p className="text-sm" aria-live="polite">
          {page ? (
            <>
              Total: <strong className="tabular text-base font-semibold">{formatDuration(page.meta.total_seconds)}</strong>
              <span className="text-muted"> en {page.meta.total === 1 ? '1 registro' : `${page.meta.total} registros`}</span>
            </>
          ) : (
            !entries.error && <span className="text-muted">Calculando el total…</span>
          )}
        </p>
        <Button variant="secondary" loading={exporter.isPending} disabled={!page || page.meta.total === 0} onClick={() => exporter.mutate(filters)}>
          <Download aria-hidden="true" className="size-4" />
          Exportar CSV
        </Button>
      </div>
      {!page ? (
        <PageState isPending={entries.isPending} error={entries.error} shape="table" onRetry={() => void entries.refetch()} />
      ) : (
        <StaleContent stale={entries.isPlaceholderData}>
          <EntryList
            page={page}
            empty="No hay horas en ese rango. Probá con uno más amplio o sacá el proyecto o la búsqueda."
            showProject
            onPageChange={onPageChange}
          />
        </StaleContent>
      )}
    </div>
  )
}
