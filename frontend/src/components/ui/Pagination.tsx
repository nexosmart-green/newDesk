import { Button } from '@/components/ui/Button'
import type { Paginated } from '@/lib/api'

interface PaginationProps {
  meta: Paginated<unknown>['meta']
  onPageChange: (page: number) => void
}

export function Pagination({ meta, onPageChange }: PaginationProps) {
  if (meta.last_page <= 1) return null

  return (
    <nav aria-label="Paginación" className="flex items-center justify-between gap-2 text-sm">
      <Button variant="secondary" disabled={meta.current_page <= 1} onClick={() => onPageChange(meta.current_page - 1)}>
        Anterior
      </Button>
      <span className="text-muted">
        Página {meta.current_page} de {meta.last_page}
      </span>
      <Button
        variant="secondary"
        disabled={meta.current_page >= meta.last_page}
        onClick={() => onPageChange(meta.current_page + 1)}
      >
        Siguiente
      </Button>
    </nav>
  )
}
