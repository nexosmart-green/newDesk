import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import type { ProjectDetail } from '@/features/projects/types'
import { defaultTaskFilters } from '@/features/tasks/types'
import { useProjectTasks } from '@/features/tasks/useTasks'
import type { ProjectEntryFilters } from './types'

interface EntryFiltersProps {
  project: ProjectDetail
  filters: ProjectEntryFilters
  onChange: (changes: Partial<ProjectEntryFilters>) => void
}

const allTasks = { ...defaultTaskFilters, include_closed: true }
const idOrNull = (value: string) => (value ? Number(value) : null)

export function EntryFilters({ project, filters, onChange }: EntryFiltersProps) {
  const tasks = useProjectTasks(project.id, allTasks)

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <SelectField
        label="Persona"
        value={filters.user_id ?? ''}
        onChange={(event) => onChange({ user_id: idOrNull(event.target.value) })}
      >
        <option value="">Todas</option>
        {project.members.map((member) => (
          <option key={member.id} value={member.id}>
            {member.full_name}
          </option>
        ))}
      </SelectField>
      <SelectField
        label="Tarea"
        value={filters.task_id ?? ''}
        onChange={(event) => onChange({ task_id: idOrNull(event.target.value) })}
      >
        <option value="">Todas</option>
        {tasks.data?.data.map((task) => (
          <option key={task.id} value={task.id}>
            {task.code.toUpperCase()} · {task.title}
          </option>
        ))}
      </SelectField>
      <TextField label="Desde" type="date" value={filters.from} onChange={(event) => onChange({ from: event.target.value })} />
      <TextField label="Hasta" type="date" value={filters.to} onChange={(event) => onChange({ to: event.target.value })} />
    </div>
  )
}
