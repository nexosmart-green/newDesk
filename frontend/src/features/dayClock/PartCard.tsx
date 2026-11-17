import { Trash2, TriangleAlert } from 'lucide-react'
import { useId, useState } from 'react'
import { Button } from '@/components/ui/Button'
import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import type { ProjectSummary } from '@/features/projects/types'
import type { Task } from '@/features/tasks/types'
import { CommentField } from '@/features/timer/CommentField'
import { fieldErrorOf } from '@/lib/api'
import { formatDuration, formatSignedDuration } from '@/lib/duration'
import { durationOf, type DayPart, type PartColor } from './dayParts'

/** The project's bag before this split and after it (below zero). */
export interface Overdraft {
  available: number
  left: number
}

interface PartCardProps {
  id: string
  index: number
  part: DayPart
  color: PartColor
  /** Phones show only this part open; the rest collapse to one line. Desktop shows all. */
  expanded: boolean
  project: ProjectSummary | undefined
  projects: ProjectSummary[]
  projectsLoading: boolean
  /** My tasks of the chosen project. */
  tasks: Task[]
  overdraft: Overdraft | null
  error: unknown
  canRemove: boolean
  labelAt: (offset: number) => string
  onTimeChange: (edge: 'from' | 'to', time: string) => void
  onChange: (patch: Partial<Omit<DayPart, 'key'>>) => void
  onRemove: () => void
  onExpand: () => void
}

const NEW_TASK = 'new'

/** Typed times apply on blur: applying each keystroke would fight the browser's time field. */
function ClockField({ label, value, error, onCommit }: { label: string; value: string; error?: string; onCommit: (time: string) => void }) {
  const [draft, setDraft] = useState<string | null>(null)
  return (
    <TextField
      type="time"
      label={label}
      value={draft ?? value}
      error={error}
      onChange={(event) => setDraft(event.target.value)}
      onBlur={() => {
        if (draft) onCommit(draft)
        setDraft(null)
      }}
      className="[&_input]:px-2"
    />
  )
}

/** One part of the split day: when, for which project and task, and what was done (RF-05.19). */
export function PartCard(props: PartCardProps) {
  const { id, index, part, color, expanded, project, projects, projectsLoading, tasks, overdraft, error, canRemove, labelAt } = props
  const titleId = useId()
  const number = index + 1
  const fieldError = (name: string) => fieldErrorOf(error, `parts.${index}.${name}`)
  const task = tasks.find((candidate) => candidate.id === part.taskId)
  const range = `${labelAt(part.from)} – ${labelAt(part.to)}`
  const taskLabel = part.newTaskTitle?.trim() || task?.title || 'Falta la tarea'

  return (
    <article
      id={id}
      aria-labelledby={titleId}
      className={`scroll-mt-4 rounded-xl border bg-bg ${overdraft ? 'border-warning/50' : expanded ? 'border-accent/50 sm:border-border' : 'border-border'}`}
    >
      {!expanded && (
        <button type="button" onClick={props.onExpand} aria-expanded="false" className="flex min-h-16 w-full cursor-pointer items-center gap-3 px-3.5 py-2.5 text-left sm:hidden">
          <span aria-hidden="true" className={`size-2.5 shrink-0 rounded-[3px] ${color.fill}`} />
          <span className="min-w-0 flex-1">
            <span className="block truncate text-sm font-semibold">
              {project?.name ?? `Parte ${number}`}
              {task && ` · ${task.code}`}
            </span>
            <span className={`block truncate text-xs ${overdraft ? 'text-warning' : 'text-muted'}`}>
              {range} · {overdraft ? `deja la bolsa en ${formatSignedDuration(overdraft.left)}` : taskLabel}
            </span>
          </span>
          <span className="tabular text-sm font-semibold">{formatDuration(durationOf(part))}</span>
        </button>
      )}
      <div className={`${expanded ? 'grid' : 'hidden sm:grid'} gap-3 p-3.5 sm:grid-cols-[13.5rem_minmax(0,1fr)_minmax(0,1fr)] sm:gap-x-4 sm:p-4`}>
        <div className="space-y-2">
          <div className="flex min-h-7 items-center justify-between gap-2">
            <h3 id={titleId} className="flex items-center gap-2 text-sm font-semibold">
              <span aria-hidden="true" className={`size-2.5 shrink-0 rounded-[3px] ${color.fill}`} />
              <span>
                Parte {number} · <span className="tabular">{formatDuration(durationOf(part))}</span>
              </span>
            </h3>
            {canRemove && (
              <button
                type="button"
                onClick={props.onRemove}
                aria-label={`Quitar parte ${number}`}
                title="Quitar parte (queda sin asignar)"
                className="-my-2 -mr-2 grid size-11 shrink-0 cursor-pointer place-items-center rounded-lg text-muted transition-colors hover:bg-surface-2 hover:text-danger focus-visible:outline-2 focus-visible:outline-accent"
              >
                <Trash2 aria-hidden="true" className="size-4" />
              </button>
            )}
          </div>
          <div className="grid grid-cols-2 gap-2">
            <ClockField label="Desde" value={labelAt(part.from)} error={fieldError('from')} onCommit={(time) => props.onTimeChange('from', time)} />
            <ClockField label="Hasta" value={labelAt(part.to)} error={fieldError('to')} onCommit={(time) => props.onTimeChange('to', time)} />
          </div>
        </div>
        <SelectField
          label="Proyecto"
          value={part.projectId ?? ''}
          error={fieldError('project_id')}
          onChange={(event) => props.onChange({ projectId: Number(event.target.value) || null, taskId: null })}
        >
          <option value="">{projectsLoading ? 'Cargando proyectos…' : 'Elegí un proyecto…'}</option>
          {projects.map((option) => (
            <option key={option.id} value={option.id}>
              {option.name} ({option.code_prefix}){option.client ? ` · ${option.client.name}` : ''}
            </option>
          ))}
        </SelectField>
        {part.newTaskTitle === null ? (
          <SelectField
            label="Tarea"
            value={part.taskId ?? ''}
            disabled={part.projectId === null}
            error={fieldError('task_id')}
            onChange={(event) =>
              props.onChange(event.target.value === NEW_TASK ? { taskId: null, newTaskTitle: '' } : { taskId: Number(event.target.value) || null })
            }
          >
            <option value="">{part.projectId === null ? 'Primero elegí el proyecto' : tasks.length > 0 ? 'Elegí una tarea…' : 'No tenés tareas acá'}</option>
            {tasks.map((option) => (
              <option key={option.id} value={option.id}>
                {option.code} · {option.title}
              </option>
            ))}
            <option value={NEW_TASK}>Nueva tarea…</option>
          </SelectField>
        ) : (
          <div>
            <TextField
              label="Tarea nueva"
              value={part.newTaskTitle}
              onChange={(event) => props.onChange({ newTaskTitle: event.target.value })}
              hint="Tarea nueva: se crea al guardar"
              error={fieldError('new_task_title')}
              maxLength={255}
              autoFocus
              className="[&_input]:border-accent"
            />
            <Button variant="link" className="mt-1 text-xs" onClick={() => props.onChange({ newTaskTitle: null })}>
              Elegir una de mis tareas
            </Button>
          </div>
        )}
        <div className="sm:col-span-2 sm:col-start-2">
          <CommentField value={part.comment} onChange={(comment) => props.onChange({ comment })} error={fieldError('comment')} />
        </div>
        {overdraft && project && (
          <p className="flex items-start gap-2 text-sm leading-snug text-warning sm:col-span-3">
            <TriangleAlert aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
            <span>
              A {project.name} le quedan {formatSignedDuration(overdraft.available)}: con esta parte la bolsa queda en{' '}
              {formatSignedDuration(overdraft.left)}. Se guarda igual y se avisa al admin y al responsable.
            </span>
          </p>
        )}
      </div>
    </article>
  )
}
