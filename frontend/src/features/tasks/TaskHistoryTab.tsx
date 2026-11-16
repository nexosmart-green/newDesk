import { ArrowRight, History } from 'lucide-react'
import { useState } from 'react'
import { EmptyState } from '@/components/ui/EmptyState'
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
      <EmptyState icon={History}>Todavía no hay cambios: acá va a aparecer cada edición, cambio de estado, asignación o movimiento.</EmptyState>
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
                        <ArrowRight aria-hidden="true" className="mx-1 inline size-3 align-[-1px] text-muted" />
                        <span className="sr-only">pasó a </span>
                        <span className="font-medium">{change.after}</span>
                      </dd>
                    </div>
                  ))}
                </dl>
              )}
              <p className="mt-0.5 flex flex-wrap gap-x-3 text-xs text-muted">
                <time dateTime={entry.created_at}>{formatDateTime(entry.created_at)}</time>
                {source && <span>{source}</span>}
              </p>
            </li>
          )
        })}
      </ol>
      <Pagination meta={activity.data.meta} onPageChange={setPage} />
    </div>
  )
}
