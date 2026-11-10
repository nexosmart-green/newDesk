import { ListChecks } from 'lucide-react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/Button'
import { defaultTaskFilters, type TaskFilters } from './types'

function hasActiveFilters(filters: TaskFilters): boolean {
  return JSON.stringify({ ...filters, search: filters.search.trim() }) !== JSON.stringify(defaultTaskFilters)
}

/** Nothing to show: with filters on, offer to clear them; otherwise point to where tasks come from. */
export function TasksEmptyState({ filters, onReset }: { filters: TaskFilters; onReset: () => void }) {
  const filtered = hasActiveFilters(filters)
  return (
    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-border px-6 py-10 text-center">
      <span className="grid size-12 place-items-center rounded-full bg-brand/15 text-accent">
        <ListChecks aria-hidden="true" className="size-6" strokeWidth={1.75} />
      </span>
      <p className="font-medium">{filtered ? 'Ninguna tarea coincide con los filtros' : 'No hay tareas abiertas'}</p>
      <p className="max-w-sm text-sm text-muted">
        {filtered ? 'Probá con otros filtros o limpialos para ver todo.' : 'Las tareas se crean dentro de cada proyecto.'}
      </p>
      {filtered ? (
        <Button variant="secondary" onClick={onReset}>
          Limpiar filtros
        </Button>
      ) : (
        <Link
          to="/proyectos"
          className="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface px-4 text-sm font-medium hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-accent"
        >
          Ir a Proyectos
        </Link>
      )}
    </div>
  )
}
