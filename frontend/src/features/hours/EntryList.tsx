import { useState } from 'react'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Pagination } from '@/components/ui/Pagination'
import { useCan, useCurrentUser } from '@/features/auth/useAuth'
import type { TimeEntry } from '@/features/timer/types'
import { useDeleteTimeEntry } from '@/features/timer/useTimer'
import { EditEntryDialog } from './EditEntryDialog'
import { EntryRow } from './EntryRow'
import type { EntryPage } from './types'

interface EntryListProps {
  page: EntryPage
  /** Shown when the page has no entries. */
  empty: string
  showProject?: boolean
  onPageChange: (page: number) => void
}

type Change = { entry: TimeEntry; action: 'edit' | 'delete' }

/** Entries with their comments (RF-05.18), corrected or deleted by an admin or their owner within the window (RF-05.15). */
export function EntryList({ page, empty, showProject = false, onPageChange }: EntryListProps) {
  const { data: user } = useCurrentUser()
  const canManage = useCan('time.manage')
  const [changing, setChanging] = useState<Change | null>(null)
  const remove = useDeleteTimeEntry()
  const editableSince = page.meta.editable_since
  const isLocked = (entry: TimeEntry) => editableSince !== null && Date.parse(entry.started_at) < Date.parse(editableSince)

  const close = () => {
    remove.reset()
    setChanging(null)
  }

  return (
    <>
      {page.data.length === 0 ? (
        <p className="text-sm text-muted">{empty}</p>
      ) : (
        <ul className="divide-y divide-border rounded-lg border border-border bg-surface" aria-label="Registros de horas">
          {page.data.map((entry) => (
            <EntryRow
              key={entry.id}
              entry={entry}
              canChange={canManage || entry.user.id === user?.id}
              locked={isLocked(entry)}
              showProject={showProject}
              onEdit={() => setChanging({ entry, action: 'edit' })}
              onDelete={() => setChanging({ entry, action: 'delete' })}
            />
          ))}
        </ul>
      )}
      <Pagination meta={page.meta} onPageChange={onPageChange} />
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
            ¿Borrás {changing.entry.user.id === user?.id ? 'tu registro' : `el registro de ${changing.entry.user.full_name}`} en{' '}
            <span className="font-mono uppercase">{changing.entry.task.code}</span>? Queda en Actividad.
          </p>
        </ConfirmDialog>
      )}
    </>
  )
}
