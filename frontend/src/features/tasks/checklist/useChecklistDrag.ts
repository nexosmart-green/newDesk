import { useState, type DragEvent } from 'react'
import type { DropSide, RowDrag } from './ChecklistItemRow'
import { dropIndex } from './moveItem'

const DRAG_TYPE = 'application/x-newdesk-checklist'

/** Mouse drag by the handle (HTML5 DnD). Keyboard and touch use the arrows, so this stays small. */
export function useChecklistDrag(ids: number[], onMove: (id: number, toIndex: number) => void) {
  const [draggedId, setDraggedId] = useState<number | null>(null)
  const [over, setOver] = useState<{ index: number; side: DropSide } | null>(null)

  function reset() {
    setDraggedId(null)
    setOver(null)
  }

  return (id: number, index: number): RowDrag => ({
    dragging: draggedId === id,
    indicator: over?.index === index && draggedId !== null && draggedId !== id ? over.side : null,
    onStart(event: DragEvent<HTMLElement>) {
      event.dataTransfer.effectAllowed = 'move'
      event.dataTransfer.setData(DRAG_TYPE, String(id))
      const row = event.currentTarget.closest('li')
      if (row) event.dataTransfer.setDragImage(row, 16, 16)
      setDraggedId(id)
    },
    onEnd: reset,
    onOver(event: DragEvent<HTMLLIElement>) {
      if (draggedId === null) return
      event.preventDefault()
      event.dataTransfer.dropEffect = 'move'
      const box = event.currentTarget.getBoundingClientRect()
      const side: DropSide = event.clientY > box.top + box.height / 2 ? 'after' : 'before'
      if (over?.index !== index || over.side !== side) setOver({ index, side })
    },
    onDrop(event: DragEvent<HTMLLIElement>) {
      event.preventDefault()
      const from = draggedId === null ? -1 : ids.indexOf(draggedId)
      if (draggedId !== null && from !== -1 && over) onMove(draggedId, dropIndex(from, index, over.side === 'after'))
      reset()
    },
  })
}
