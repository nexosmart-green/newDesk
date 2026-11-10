import { CircleAlert, Lock, RotateCw } from 'lucide-react'
import type { ReactElement } from 'react'
import { ApiError } from '@/lib/api'
import { errorTextOf, MODULE_DISABLED } from '@/lib/errorText'
import { Button } from './Button'
import { CardGridSkeleton, DetailSkeleton, ListSkeleton, TableSkeleton } from './Skeleton'

type Shape = 'list' | 'cards' | 'table' | 'detail'

interface PageStateProps {
  isPending: boolean
  error: unknown
  /** Which skeleton to show while loading (what the content will look like). */
  shape?: Shape
  /** Offers "Reintentar" on errors that may go away (network, server). */
  onRetry?: () => void
}

const skeletons: Record<Shape, () => ReactElement> = {
  list: () => <ListSkeleton />,
  cards: () => <CardGridSkeleton />,
  table: () => <TableSkeleton />,
  detail: () => <DetailSkeleton />,
}

/** Loading / error placeholder shared by pages that fetch a resource or a list. */
export function PageState({ isPending, error, shape = 'list', onRetry }: PageStateProps) {
  if (isPending) return skeletons[shape]()
  if (!error) return null
  if (error instanceof ApiError && error.code === MODULE_DISABLED) return <ModuleDisabledState />

  const text = errorTextOf(error)
  return (
    <div role="alert" className="flex flex-col items-start gap-3 rounded-lg border border-danger/40 bg-danger/10 p-4 sm:flex-row sm:items-center">
      <CircleAlert aria-hidden="true" className="size-5 shrink-0 text-danger" />
      <div className="min-w-0 flex-1 text-sm">
        <p className="font-semibold">{text.title}</p>
        <p className="text-muted">{text.message}</p>
      </div>
      {onRetry && (
        <Button variant="secondary" onClick={onRetry}>
          <RotateCw aria-hidden="true" className="size-4" />
          Reintentar
        </Button>
      )}
    </div>
  )
}

/** A 403 `module_disabled`: not an error the person can fix, so it reads as an empty state. */
export function ModuleDisabledState() {
  return (
    <div className="mx-auto flex max-w-md flex-col items-center gap-3 rounded-2xl border border-border bg-surface px-6 py-10 text-center">
      <span className="grid size-12 place-items-center rounded-full bg-surface-2 text-muted">
        <Lock aria-hidden="true" className="size-5" />
      </span>
      <p className="font-semibold">Módulo no disponible</p>
      <p className="text-sm text-muted">Tu rol no tiene acceso a este módulo. Pedíselo a un administrador.</p>
    </div>
  )
}
