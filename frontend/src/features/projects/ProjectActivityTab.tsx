import { useState } from 'react'
import { PageState } from '@/components/ui/PageState'
import { Pagination } from '@/components/ui/Pagination'
import { formatDateTime } from '@/lib/dates'
import { activitySourceLabel, describeActivity } from './activityMessages'
import { useProjectFromOutlet } from './projectContext'
import { useProjectActivity } from './useProjects'

/** RF-03.06: immutable log, newest first. */
export function ProjectActivityTab() {
  const project = useProjectFromOutlet()
  const [page, setPage] = useState(1)
  const activity = useProjectActivity(project.id, page)

  if (!activity.data) return <PageState isPending={activity.isPending} error={activity.error} shape="list" onRetry={() => void activity.refetch()} />
  if (activity.data.data.length === 0) return <p className="text-sm text-muted">Todavía no hay actividad.</p>

  return (
    <div className="space-y-4">
      <ol className="space-y-3" aria-label="Actividad del proyecto">
        {activity.data.data.map((entry) => {
          const source = activitySourceLabel(entry)
          return (
            <li key={entry.id} className="border-l-2 border-border pl-3">
              <p className="text-sm">{describeActivity(entry)}</p>
              <p className="text-xs text-muted">
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
