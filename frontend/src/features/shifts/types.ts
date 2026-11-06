export type WorkMode = 'office' | 'remote' | 'hybrid'

export const workModeLabels: Record<WorkMode, string> = { office: 'Oficina', remote: 'Remoto', hybrid: 'Híbrido' }

export const SHIFTS_PERMISSION = 'shifts.manage'

export interface ShiftTemplate {
  id: number
  name: string
  /** `HH:mm`, company-local */
  starts_at: string
  ends_at: string
  /** `#RRGGBB` */
  color: string
  mode: WorkMode
}

export type ShiftTemplateInput = Omit<ShiftTemplate, 'id'>

/** One assigned day (`Calendar::presentShift`). Times are company-local; `*_utc` are the real instants. */
export interface Shift {
  id: number
  date: string
  /** null when its template was deleted (the shift keeps its own times). */
  name: string | null
  color: string | null
  starts_at: string
  ends_at: string
  mode: WorkMode
  mode_label: string
  start_utc: string
  end_utc: string
}

export interface Holiday {
  id: number
  date: string
  name: string
  scope: 'national' | 'company'
}

export interface GridPerson {
  id: number
  full_name: string
  shifts: Shift[]
}

export interface ShiftGrid {
  timezone: string
  people: GridPerson[]
  holidays: Holiday[]
}

export interface AssignShiftsInput {
  user_ids: number[]
  /** null clears the days. */
  shift_template_id: number | null
  from: string
  to: string
  /** ISO 1 (Monday) … 7 (Sunday); empty = every day. */
  weekdays: number[]
}

export const weekdayOptions: readonly { value: number; label: string }[] = [
  { value: 1, label: 'Lun' },
  { value: 2, label: 'Mar' },
  { value: 3, label: 'Mié' },
  { value: 4, label: 'Jue' },
  { value: 5, label: 'Vie' },
  { value: 6, label: 'Sáb' },
  { value: 7, label: 'Dom' },
]

/** "Mañana 09:00–13:00" (or just the times when the template was deleted). */
export function shiftLabel(shift: Pick<Shift, 'name' | 'starts_at' | 'ends_at'>): string {
  const times = `${shift.starts_at}–${shift.ends_at}`
  return shift.name ? `${shift.name} ${times}` : times
}

/** `''` in the template select = clear the days (`shift_template_id: null`). */
export const CLEAR_SHIFT = ''

export function templateIdOf(value: string): number | null {
  return value === CLEAR_SHIFT ? null : Number(value)
}

export function defaultTemplateValue(templates: readonly ShiftTemplate[]): string {
  return templates[0] ? String(templates[0].id) : CLEAR_SHIFT
}
