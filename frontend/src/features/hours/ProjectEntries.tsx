import { useState } from 'react'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { PageState } from '@/components/ui/PageState'
import { Pagination } from '@/components/ui/Pagination'
import { useCan, useCurrentUser } from '@/features/auth/useAuth'
import type { ProjectDetail } from '@/features/projects/types'
import type { TimeEntry } from '@/features/timer/types'
import { useDeleteTimeEntry } from '@/features/timer/useTimer'
import { EditEntryDialog } from './EditEntryDialog'
import { EntryFilters } from './EntryFilters'
import { EntryRow } from './EntryRow'
import { emptyProjectEntryFilters, type ProjectEntryFilters } from './types'
import { useProjectEntries } from './useHours'

type Change = { entry: TimeEntry; action: 'edit' | 'delete' }

/** RF-05.11: entries by person / task / date, with their comments (RF-05.18). */
export function ProjectEntries({ project }: { project: ProjectDetail }) {
  const { data: user } = useCurrentUser()
  const canManage = useCan('time.manage')
  const [filters, setFilters] = useState(emptyProjectEntryFilters)
  const [changing, setChanging] = useState<Change | null>(null)
  const entries = useProjectEntries(project.id, filters)
  const remove = useDeleteTimeEntry()

  const change = (changes: Partial<ProjectEntryFilters>) =>
    setFilters((current) => ({ ...current, ...changes, page: changes.page ?? 1 }))
  const close = () => {
    remove.reset()
    setChanging(null)
  }

  return (
    <section className="space-y-3" aria-labelledby="project-entries">
      <h2 id="project-entries" className="font-semibold">
        Registros
      </h2>
      <EntryFilters project={project} filters={filters} onChange={change} />
      {!entries.data ? (
        <PageState isPending={entries.isPending} error={entries.error} />
      ) : entries.data.data.length === 0 ? (
        <p className="text-sm text-muted">No hay registros con estos filtros.</p>
      ) : (
        <ul className="divide-y divide-border rounded-lg border border-border bg-surface" aria-label="Registros de horas">
          {entries.data.data.map((entry) => (
            <EntryRow
              key={entry.id}
              entry={entry}
              canChange={canManage || entry.user.id === user?.id}
              onEdit={() => setChanging({ entry, action: 'edit' })}
              onDelete={() => setChanging({ entry, action: 'delete' })}
            />
          ))}
        </ul>
      )}
      {entries.data && <Pagination meta={entries.data.meta} onPageChange={(page) => change({ page })} />}
      {changing?.action === 'edit' && changing.entry.ended_at !== null && (
        <EditEntryDialog entry={{ ...changing.entry, ended_at: changing.entry.ended_at }} onClose={close} />
      )}
      {changing?.action === 'delete' && (
        <ConfirmDialog
          title="Borrar registro"
          confirmLabel="Borrar"
          danger
          pending={remove.isPending}
          error={remove.error}
          onConfirm={() => remove.mutate(changing.entry.id, { onSuccess: close })}
          onClose={close}
        >
          <p>
            ¿Borrás el registro de {changing.entry.user.full_name} en{' '}
            <span className="font-mono uppercase">{changing.entry.task.code}</span>? Queda en Actividad.
          </p>
        </ConfirmDialog>
      )}
    </section>
  )
}
