/** A grid cell: one person on one local day. */
export interface Cell {
  userId: number
  date: string
}

export const cellKey = (cell: Cell) => `${cell.userId}|${cell.date}`
