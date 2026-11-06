import { isoWeekdayOf, type Week } from '@/features/timesheet/week'
import type { AssignShiftsInput } from './types'

/** A grid cell: one person on one local day. */
export interface Cell {
  userId: number
  date: string
}

export const cellKey = (cell: Cell) => `${cell.userId}|${cell.date}`

/**
 * Turns a free (people × days) selection inside one week into the fewest `POST /shifts/assign` calls:
 * within a week each weekday names exactly one date, so people sharing the same weekday set go together.
 */
export function selectionToAssignments(cells: readonly Cell[], week: Week, templateId: number | null): AssignShiftsInput[] {
  const weekdaysByUser = new Map<number, Set<number>>()
  for (const cell of cells) {
    const weekdays = weekdaysByUser.get(cell.userId) ?? new Set<number>()
    weekdays.add(isoWeekdayOf(cell.date))
    weekdaysByUser.set(cell.userId, weekdays)
  }

  const groups = new Map<string, { weekdays: number[]; userIds: number[] }>()
  for (const [userId, weekdaySet] of weekdaysByUser) {
    const weekdays = [...weekdaySet].sort((a, b) => a - b)
    const signature = weekdays.join(',')
    const group = groups.get(signature) ?? { weekdays, userIds: [] }
    group.userIds.push(userId)
    groups.set(signature, group)
  }

  return [...groups.values()].map((group) => ({
    user_ids: group.userIds,
    shift_template_id: templateId,
    from: week.from,
    to: week.to,
    weekdays: group.weekdays,
  }))
}
