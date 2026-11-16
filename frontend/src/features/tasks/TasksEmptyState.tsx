import { ListChecks, SearchX } from 'lucide-react'
import { Link } from 'react-router'
import { Button } from '@/components/ui/Button'
import { EmptyState } from '@/components/ui/EmptyState'
import { defaultTaskFilters, type TaskFilters } from './types'

function hasActiveFilters(filters: TaskFilters): boolean {
  return JSON.stringify({ ...filters, search: filters.search.trim() }) !== JSON.stringify(defaultTaskFilters)
}

/** Nothing to show: with filters on, offer to clear them; otherwise point to where tasks come from. */
export function TasksEmptyState({ filters, onReset }: { filters: TaskFilters; onReset: () => void }) {
  if (hasActiveFilters(filters)) {
    return (
      <EmptyState icon={SearchX} action={<Button variant="secondary" onClick={onReset}>Limpiar filtros</Button>}>
        Ninguna tarea coincide con los filtros.
      </EmptyState>
    )
  }
  return (
    <EmptyState
      icon={ListChecks}
      action={
        <Link
          to="/proyectos"
          className="inline-flex min-h-9 items-center rounded-md border border-border bg-surface px-3.5 text-sm font-medium hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-accent coarse:min-h-11"
        >
          Ir a Proyectos
        </Link>
      }
    >
      No hay tareas abiertas. Las tareas se crean dentro de cada proyecto.
    </EmptyState>
  )
}
