import type { WorkMode } from './types'

/** RF-06.03 `GET /shift-recurrences`: one person's recurring shift ("lun a vie, Completo, desde 01/12"). */
export interface ShiftRecurrence {
  id: number
  user: { id: number; full_name: string }
  /** null when the template was deleted: the rule stops generating days. */
  template: { id: number; name: string; color: string; starts_at: string; ends_at: string; mode: WorkMode } | null
  /** ISO 1 (Monday) … 7 (Sunday) */
  weekdays: number[]
  starts_on: string
  /** null = "Sin fin" */
  ends_on: string | null
  skip_holidays: boolean
  created_at: string
}

export interface ShiftRecurrenceInput {
  user_ids: number[]
  shift_template_id: number
  weekdays: number[]
  starts_on: string
  ends_on: string | null
  skip_holidays: boolean
}

interface SkippedDay {
  date: string
  reason: 'holiday' | 'taken'
}

export interface CreatedRecurrence {
  recurrence: ShiftRecurrence
  generated: number
  skipped_count: number
  /** At most 20; `skipped_count` has the total. */
  skipped: SkippedDay[]
}

/** The backend's summary ("Turno fijo creado: se generaron 38 días…") plus what each rule did. */
export interface CreateRecurrencesResult {
  message: string | null
  created: CreatedRecurrence[]
}

export interface RemovedDaysResult {
  message: string | null
  removed: number
}

export const WORKING_WEEK = [1, 2, 3, 4, 5] as const
export const WHOLE_WEEK = [1, 2, 3, 4, 5, 6, 7] as const

/** One letter per ISO weekday, as printed calendars do. */
export const weekdayInitials: readonly { value: number; initial: string; name: string }[] = [
  { value: 1, initial: 'L', name: 'lunes' },
  { value: 2, initial: 'M', name: 'martes' },
  { value: 3, initial: 'M', name: 'miércoles' },
  { value: 4, initial: 'J', name: 'jueves' },
  { value: 5, initial: 'V', name: 'viernes' },
  { value: 6, initial: 'S', name: 'sábado' },
  { value: 7, initial: 'D', name: 'domingo' },
]

const sameDays = (weekdays: readonly number[], expected: readonly number[]) =>
  weekdays.length === expected.length && expected.every((day) => weekdays.includes(day))

/** "Lun a vie", "Todos los días" or "lunes, miércoles y viernes" — for screen readers and summaries. */
export function weekdaysSummary(weekdays: readonly number[]): string {
  if (sameDays(weekdays, WORKING_WEEK)) return 'Lun a vie'
  if (sameDays(weekdays, WHOLE_WEEK)) return 'Todos los días'
  const names = weekdayInitials.filter((day) => weekdays.includes(day.value)).map((day) => day.name)
  return names.length <= 1 ? (names[0] ?? '') : `${names.slice(0, -1).join(', ')} y ${names.at(-1)}`
}
