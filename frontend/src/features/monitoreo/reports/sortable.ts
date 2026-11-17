// La cuenta del arrastre del roadmap (D-064). Las posiciones se miden al empezar a arrastrar
// y no cambian: durante el arrastre solo se mueven con transform.
export interface Slot { top: number; height: number }

// Dónde caería: cuántas de las otras tarjetas tienen el centro arriba del centro de la arrastrada.
// No depende de estar encima de una tarjeta: los huecos y lo que queda debajo de la última cuentan.
export function dropIndex(slots: Slot[], from: number, dy: number): number {
  const dragged = slots[from]
  if (!dragged) return from
  const center = dragged.top + dragged.height / 2 + dy
  return slots.filter((slot, index) => index !== from && slot.top + slot.height / 2 < center).length
}

// Cuánto se corre una tarjeta para hacerle lugar a la arrastrada: su alto más el espacio entre tarjetas.
export function shiftOf(index: number, from: number, over: number, slots: Slot[]): number {
  const dragged = slots[from]
  if (!dragged || index === from) return 0
  const next = slots[from + 1] ?? slots[from - 1]
  const gap = next ? Math.abs(next.top - dragged.top) - (next.top > dragged.top ? dragged.height : next.height) : 0
  const size = dragged.height + gap
  if (from < over && index > from && index <= over) return -size
  if (over < from && index >= over && index < from) return size
  return 0
}

// Dónde termina la arrastrada al soltar, respecto de donde estaba: el lugar que le hicieron.
export function settleOffset(from: number, over: number, slots: Slot[]): number {
  const dragged = slots[from]
  const target = slots[over]
  if (!dragged || !target || from === over) return 0
  return over > from ? target.top + target.height - dragged.height - dragged.top : target.top - dragged.top
}

export function moveItem<T>(list: T[], from: number, to: number): T[] {
  const next = [...list]
  next.splice(to, 0, ...next.splice(from, 1))
  return next
}
