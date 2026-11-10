import { useState, type ReactNode } from 'react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/Button'
import { HoursBar } from '@/features/hours/HoursBar'
import { formatDate } from '@/lib/dates'
import { EditProjectDialog } from './EditProjectDialog'
import { useProjectFromOutlet } from './projectContext'
import { ProjectStatusControl } from './ProjectStatusControl'
import { projectTypeLabels } from './types'

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="text-sm">{children || '—'}</dd>
    </div>
  )
}

/** RF-03.05 Resumen: hours bar on top, then the project's data. */
export function ProjectSummaryTab() {
  const project = useProjectFromOutlet()
  const [editing, setEditing] = useState(false)

  return (
    <div className="space-y-6">
      <section className="space-y-2 rounded-lg border border-border bg-surface p-4" aria-labelledby="summary-hours">
        <h2 id="summary-hours" className="font-semibold">
          Horas
        </h2>
        <HoursBar bag={project.hours} />
      </section>
      <dl className="grid gap-4 rounded-lg border border-border bg-surface p-4 sm:grid-cols-2 lg:grid-cols-3">
        <Field label="Cliente">
          {project.client && <Link to={`/clientes/${project.client.id}`} className="text-accent">{project.client.name}</Link>}
        </Field>
        <Field label="Tipo">{projectTypeLabels[project.type]}</Field>
        <Field label="Responsable">{project.leader?.full_name}</Field>
        <Field label="Inicio">{project.starts_on && formatDate(project.starts_on)}</Field>
        <Field label="Fin estimado">{project.ends_on && formatDate(project.ends_on)}</Field>
        <Field label="Prefijo">
          <span className="font-mono uppercase">{project.code_prefix}</span>
        </Field>
        <div className="sm:col-span-2 lg:col-span-3">
          <Field label="Descripción">
            {project.description && <span className="whitespace-pre-line">{project.description}</span>}
          </Field>
        </div>
      </dl>
      {project.can.update && (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <ProjectStatusControl project={project} />
          <Button variant="secondary" onClick={() => setEditing(true)}>
            Editar proyecto
          </Button>
        </div>
      )}
      {editing && <EditProjectDialog project={project} onClose={() => setEditing(false)} />}
    </div>
  )
}
