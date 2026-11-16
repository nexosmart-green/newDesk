import { SlidersHorizontal } from 'lucide-react'
import { useId, useState, type ReactNode } from 'react'
import { Button } from '../Button'
import { Dialog, DialogFooter } from '../Dialog'

interface FilterBarProps {
  /** The `SearchInput`: always visible, desktop and phone. */
  search: ReactNode
  /** The filters used most, in the same row as the search. */
  children?: ReactNode
  /** The rest, behind "Más filtros". */
  more?: ReactNode
  /** How many of the `more` filters are in use (shown on the toggle; opens it on arrival). */
  moreActive?: number
  /** How many filters are in use overall (the phone's "Filtros (n)"). */
  active: number
}

const counted = (text: string, count: number) => (count > 0 ? `${text} (${count})` : text)

/**
 * One row: search, the main filters, "Más filtros (n)". On a phone the row is the search and a
 * "Filtros" button that opens every filter in a sheet (they apply as they change).
 */
export function FilterBar({ search, children, more, moreActive = 0, active }: FilterBarProps) {
  const moreId = useId()
  const [moreOpen, setMoreOpen] = useState(moreActive > 0)
  const [sheetOpen, setSheetOpen] = useState(false)

  return (
    <div className="space-y-2">
      <div className="flex flex-wrap items-center gap-2">
        <div className="min-w-0 flex-1 sm:max-w-xs sm:flex-none sm:basis-64">{search}</div>
        <div className="hidden sm:contents">
          {children}
          {more && (
            <Button
              variant="ghost"
              aria-expanded={moreOpen}
              aria-controls={moreId}
              onClick={() => setMoreOpen((open) => !open)}
              className={moreActive > 0 ? 'text-text' : undefined}
            >
              {counted(moreOpen ? 'Menos filtros' : 'Más filtros', moreActive)}
            </Button>
          )}
        </div>
        <Button variant="secondary" className="sm:hidden" aria-haspopup="dialog" onClick={() => setSheetOpen(true)}>
          <SlidersHorizontal aria-hidden="true" className="size-4" />
          {counted('Filtros', active)}
        </Button>
      </div>
      {more && (
        // Hidden by class, not by the attribute: same for the browser and assistive tech (display: none).
        <div id={moreId} className={`hidden flex-wrap items-center gap-2 ${moreOpen ? 'sm:flex' : ''}`}>
          {more}
        </div>
      )}
      {sheetOpen && (
        <Dialog title="Filtros" size="sm" onClose={() => setSheetOpen(false)}>
          <div className="flex flex-col gap-2 [&>*]:w-full">
            {children}
            {more}
          </div>
          <DialogFooter>
            <Button onClick={() => setSheetOpen(false)}>Listo</Button>
          </DialogFooter>
        </Dialog>
      )}
    </div>
  )
}
