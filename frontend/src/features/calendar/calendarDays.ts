import { parseLocalDate, toLocalDateString, weekOf, type Week } from '@/lib/week'
import type { CalendarDay, MyCalendar } from './types'

const emptyDay = (): CalendarDay => ({ shifts: [], tasks: [], holidays: [] })

/** Everything of one calendar date together, so a day cell just reads `days.get(date)`. */
export function groupByDate(calendar: MyCalendar): Map<string, CalendarDay> {
  const days = new Map<string, CalendarDay>()
  const dayOf = (date: string) => {
    const day = days.get(date) ?? emptyDay()
    days.set(date, day)
    return day
  }
  for (const shift of calendar.shifts) dayOf(shift.date).shifts.push(shift)
  for (const task of calendar.tasks_due) dayOf(task.due_on).tasks.push(task)
  for (const holiday of calendar.holidays) dayOf(holiday.date).holidays.push(holiday)
  return days
}

export function isEmptyDay(day: CalendarDay | undefined): boolean {
  return !day || (day.shifts.length === 0 && day.tasks.length === 0 && day.holidays.length === 0)
}

/** `YYYY-MM` of a local date. */
export function monthOf(date: Date): string {
  return toLocalDateString(date).slice(0, 7)
}

export function isMonthString(value: string | null): value is string {
  return value !== null && /^\d{4}-(0[1-9]|1[0-2])$/.test(value)
}

export function shiftMonth(month: string, months: number): string {
  const date = parseLocalDate(`${month}-01`)
  return monthOf(new Date(date.getFullYear(), date.getMonth() + months, 1))
}

/** Whole Monday–Sunday weeks covering the month: what a month grid shows and fetches. */
export function monthGridRange(month: string): Week {
  const first = parseLocalDate(`${month}-01`)
  const last = new Date(first.getFullYear(), first.getMonth() + 1, 0)
  return { from: weekOf(first).from, to: weekOf(last).to }
}

/** "noviembre de 2026" */
export function monthLabel(month: string): string {
  return parseLocalDate(`${month}-01`).toLocaleDateString('es-AR', { month: 'long', year: 'numeric' })
}
