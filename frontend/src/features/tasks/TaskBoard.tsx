import { useState, type DragEvent } from 'react'
import { Link } from 'react-router'
import { FieldErrorsAlert } from '@/components/ui/ErrorAlert'
import { StartTimerButton } from '@/features/timer/StartTimerButton'
import { boardColumns } from './boardColumns'
import { CategoryTag, LabelChips } from '@/features/taskCatalogs/LabelChip'
import { overdueRowClassName, TaskAttachmentsBadge, TaskChecklistBadge, TaskAssigneeNames, TaskDue, TaskLoggedHours } from './TaskBits'
import { TaskStatusSelect } from './TaskStatusSelect'
import { isAssignedTo, type Task, type TaskFilters, type TaskStatus } from './types'
import { useUpdateTaskStatus } from './useTasks'

interface TaskBoardProps {
  tasks: Task[]
  statuses: TaskStatus[]
  filters: TaskFilters
  userId: number | undefined
}

/** RF-04.04 "Tablero": drag a card to another column, or use its status select (keyboard). */
export function TaskBoard({ tasks, statuses, filters, userId }: TaskBoardProps) {
  const updateStatus = useUpdateTaskStatus()
  const [dropTarget, setDropTarget] = useState<number | null>(null)

  function move(code: string, statusId: number) {
    const task = tasks.find((candidate) => candidate.code === code)
    if (task && task.status?.id !== statusId) updateStatus.mutate({ code, statusId })
  }

  function handleDrop(event: DragEvent, statusId: number) {
    event.preventDefault()
    setDropTarget(null)
    move(event.dataTransfer.getData('text/plain'), statusId)
  }

  return (
    <div className="space-y-3">
      <FieldErrorsAlert error={updateStatus.error} />
      <div className="flex gap-3 overflow-x-auto pb-2">
        {boardColumns(statuses, filters).map((status) => {
          const cards = tasks.filter((task) => task.status?.id === status.id)
          return (
            <section
              key={status.id}
              aria-label={`Columna ${status.name}`}
              onDragOver={(event) => {
                event.preventDefault()
                setDropTarget(status.id)
              }}
              onDragLeave={() => setDropTarget(null)}
              onDrop={(event) => handleDrop(event, status.id)}
              className={`w-72 shrink-0 space-y-2 rounded-lg border bg-bg p-2 ${dropTarget === status.id ? 'border-accent' : 'border-border'}`}
            >
              <h3 className="flex items-center gap-2 px-1 text-sm font-semibold">
                <span aria-hidden="true" className="size-2 rounded-full" style={{ backgroundColor: status.color }} />
                {status.name}
                <span className="text-xs font-normal text-muted">{cards.length}</span>
              </h3>
              <ul className="space-y-2">
                {cards.map((task) => (
                  <li
                    key={task.id}
                    draggable
                    onDragStart={(event) => event.dataTransfer.setData('text/plain', task.code)}
                    className={`cursor-grab space-y-2 rounded border border-border bg-surface p-2 ${task.is_overdue ? overdueRowClassName : ''}`}
                  >
                    <Link to={`/tareas/${task.code}`} className="block text-sm hover:underline">
                      <span className="mr-1 font-mono text-xs uppercase text-muted">{task.code}</span>
                      {task.title}
                    </Link>
                    <LabelChips labels={task.labels} />
                    <div className="flex flex-wrap items-center gap-2">
                      <CategoryTag category={task.category} />
                      <TaskAssigneeNames task={task} />
                      <TaskDue task={task} />
                      <TaskLoggedHours task={task} />
                      <TaskChecklistBadge task={task} />
                      <TaskAttachmentsBadge task={task} />
                    </div>
                    <div className="flex items-center gap-2">
                      <TaskStatusSelect
                        code={task.code}
                        statusId={task.status?.id}
                        statuses={statuses}
                        disabled={updateStatus.isPending}
                        onChange={(statusId) => move(task.code, statusId)}
                      />
                      {isAssignedTo(task, userId) && <StartTimerButton task={task} compact />}
                    </div>
                  </li>
                ))}
              </ul>
            </section>
          )
        })}
      </div>
    </div>
  )
}
