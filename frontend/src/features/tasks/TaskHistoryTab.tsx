import { History } from 'lucide-react'
import { useState } from 'react'
import { PageState } from '@/components/ui/PageState'
import { Pagination } from '@/components/ui/Pagination'
import { activitySourceLabel } from '@/features/projects/activityMessages'
import { formatDateTime } from '@/lib/dates'
import { describeTaskActivity, visibleChanges } from './taskActivityMessages'
import { useTaskActivity } from './useTasks'

/** Tab "Historial": who changed what on this task, newest first (immutable log). */
export function TaskHistoryTab({ code }: { code: string }) {
  const [page, setPage] = useState(1)
  const activity = useTaskActivity(code, page)

  if (!activity.data) {
    return <PageState isPending={activity.isPending} error={activity.error} shape="list" onRetry={() => void activity.refetch()} />
  }
  if (activity.data.data.length === 0) {
    return (
      <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed border-border px-6 py-10 text-center">
        <History aria-hidden="true" className="size-6 text-muted" />
        <p className="font-medium">Sin cambios registrados</p>
        <p className="max-w-sm text-sm text-muted">Acá aparece cada edición, cambio de estado, asignación o movimiento de la tarea.</p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      <ol className="space-y-4" aria-label="Historial de la tarea">
        {activity.data.data.map((entry) => {
          const source = activitySourceLabel(entry)
          const changes = visibleChanges(entry)
          return (
            <li key={entry.id} className="relative border-l-2 border-border pl-4">
              <span aria-hidden="true" className="absolute top-1.5 -left-[5px] size-2 rounded-full bg-brand" />
              <p className="text-sm">{describeTaskActivity(entry)}</p>
              {changes.length > 0 && (
                <dl className="mt-1 space-y-0.5 text-xs">
                  {changes.map((change) => (
                    <div key={change.key} className="flex flex-wrap gap-x-1.5">
                      <dt className="text-muted">{change.label}:</dt>
                      <dd>
                        <span className="text-muted line-through decoration-muted/60">{change.before}</span>
                        <span aria-label="pasó a"> → </span>
                        <span className="font-medium">{change.after}</span>
                      </dd>
                    </div>
                  ))}
                </dl>
              )}
              <p className="mt-0.5 text-xs text-muted">
                <time dateTime={entry.created_at}>{formatDateTime(entry.created_at)}</time>
                {source && ` · ${source}`}
              </p>
            </li>
          )
        })}
      </ol>
      <Pagination meta={activity.data.meta} onPageChange={setPage} />
    </div>
  )
}
