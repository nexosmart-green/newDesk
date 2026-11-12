import { ChevronDown, ChevronUp, GripVertical, Trash2, type LucideIcon } from 'lucide-react'
import { useRef, useState, type DragEvent } from 'react'
import type { ChecklistItem } from '../types'
import { ChecklistTextEditor } from './ChecklistTextEditor'

export type DropSide = 'before' | 'after'

export interface RowDrag {
  dragging: boolean
  indicator: DropSide | null
  onStart: (event: DragEvent<HTMLElement>) => void
  onEnd: () => void
  onOver: (event: DragEvent<HTMLLIElement>) => void
  onDrop: (event: DragEvent<HTMLLIElement>) => void
}

interface ChecklistItemRowProps {
  code: string
  item: ChecklistItem
  index: number
  count: number
  drag: RowDrag
  onToggle: (done: boolean) => void
  onMove: (toIndex: number) => void
  onDelete: () => void
}

// With a mouse the row actions appear on hover/focus (space reserved, no jump); on touch they're always there.
const actionsClass =
  'flex shrink-0 items-center transition-opacity pointer-fine:opacity-0 pointer-fine:group-hover:opacity-100 pointer-fine:group-focus-within:opacity-100'

interface RowButtonProps {
  icon: LucideIcon
  label: string
  onClick: () => void
  disabled?: boolean
  danger?: boolean
  move?: 'up' | 'down'
}

function RowButton({ icon: Icon, label, disabled, onClick, danger, move }: RowButtonProps) {
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      disabled={disabled}
      data-move={move}
      onClick={onClick}
      className={`grid size-9 cursor-pointer place-items-center rounded-md text-muted transition-colors hover:bg-surface focus-visible:outline-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-30 ${danger ? 'hover:text-danger' : 'hover:text-text'}`}
    >
      <Icon aria-hidden="true" className="size-4" />
    </button>
  )
}

/** One subtask: tick it, click the text to rename, drag by the handle or move with the arrows. */
export function ChecklistItemRow({ code, item, index, count, drag, onToggle, onMove, onDelete }: ChecklistItemRowProps) {
  const [editing, setEditing] = useState(false)
  const rowRef = useRef<HTMLLIElement>(null)
  const doneBy = item.done && item.done_by ? `Hecha por ${item.done_by.full_name}` : undefined

  // Reaching the top (or bottom) disables the arrow just pressed: keep the focus on the row, on the other arrow.
  function move(direction: 'up' | 'down') {
    onMove(direction === 'up' ? index - 1 : index + 1)
    requestAnimationFrame(() => {
      const pressed = rowRef.current?.querySelector<HTMLButtonElement>(`[data-move="${direction}"]`)
      if (pressed?.disabled) rowRef.current?.querySelector<HTMLButtonElement>(`[data-move="${direction === 'up' ? 'down' : 'up'}"]`)?.focus()
    })
  }

  return (
    <li
      ref={rowRef}
      onDragOver={drag.onOver}
      onDrop={drag.onDrop}
      className={`group relative flex items-center gap-0.5 rounded-lg pr-0.5 transition-colors hover:bg-surface-2 ${drag.dragging ? 'opacity-40' : ''}`}
    >
      {drag.indicator && (
        <span
          aria-hidden="true"
          className={`pointer-events-none absolute inset-x-1 h-0.5 rounded-full bg-accent ${drag.indicator === 'before' ? '-top-px' : '-bottom-px'}`}
        />
      )}
      <span
        draggable
        onDragStart={drag.onStart}
        onDragEnd={drag.onEnd}
        aria-hidden="true"
        title="Arrastrá para reordenar"
        className="hidden h-11 w-6 shrink-0 cursor-grab place-items-center text-muted/70 hover:text-text active:cursor-grabbing pointer-fine:grid"
      >
        <GripVertical className="size-4" />
      </span>
      <label className="grid size-11 shrink-0 cursor-pointer place-items-center" title={doneBy}>
        <input
          type="checkbox"
          checked={item.done}
          aria-label={item.text}
          onChange={(event) => onToggle(event.target.checked)}
          className="size-[1.125rem] cursor-pointer accent-brand-strong"
        />
      </label>
      {editing ? (
        <ChecklistTextEditor code={code} item={item} onDone={() => setEditing(false)} />
      ) : (
        <button
          type="button"
          onClick={() => setEditing(true)}
          aria-label={`Editar «${item.text}»`}
          className={`min-h-11 min-w-0 flex-1 cursor-text rounded-md px-1 py-2 text-left text-sm leading-snug break-words focus-visible:outline-2 focus-visible:outline-accent ${item.done ? 'text-muted line-through decoration-muted/70' : ''}`}
        >
          {item.text}
          {doneBy && <span className="sr-only"> ({doneBy})</span>}
        </button>
      )}
      <div className={actionsClass}>
        <RowButton icon={ChevronUp} label={`Subir «${item.text}»`} disabled={index === 0} onClick={() => move('up')} move="up" />
        <RowButton icon={ChevronDown} label={`Bajar «${item.text}»`} disabled={index === count - 1} onClick={() => move('down')} move="down" />
        <RowButton icon={Trash2} label={`Borrar «${item.text}»`} onClick={onDelete} danger />
      </div>
    </li>
  )
}
