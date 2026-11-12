/** The ids in their new order after moving `id` to `toIndex` (clamped). Same order when nothing moves. */
export function moveItem(ids: number[], id: number, toIndex: number): number[] {
  const from = ids.indexOf(id)
  if (from === -1) return ids
  const target = Math.max(0, Math.min(ids.length - 1, toIndex))
  if (target === from) return ids
  const next = ids.filter((candidate) => candidate !== id)
  next.splice(target, 0, id)
  return next
}

/**
 * Drop position → final index. `overIndex` is the row under the pointer and `after` whether the pointer
 * is on its lower half; removing the dragged row first shifts the rows below it up by one.
 */
export function dropIndex(fromIndex: number, overIndex: number, after: boolean): number {
  const slot = overIndex + (after ? 1 : 0)
  return slot > fromIndex ? slot - 1 : slot
}
