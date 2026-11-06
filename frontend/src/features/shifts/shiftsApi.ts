import { api, dataOf, type ApiEnvelope } from '@/lib/api'
import type { AssignShiftsInput, ShiftCellsInput, ShiftGrid, ShiftTemplate, ShiftTemplateInput } from './types'

export async function fetchShiftTemplates(): Promise<ShiftTemplate[]> {
  return dataOf(api.get<ApiEnvelope<ShiftTemplate[]>>('/shift-templates'))
}

export async function createShiftTemplate(input: ShiftTemplateInput): Promise<ShiftTemplate> {
  return dataOf(api.post<ApiEnvelope<ShiftTemplate>>('/shift-templates', input))
}

export async function updateShiftTemplate(id: number, input: ShiftTemplateInput): Promise<ShiftTemplate> {
  return dataOf(api.put<ApiEnvelope<ShiftTemplate>>(`/shift-templates/${id}`, input))
}

export async function deleteShiftTemplate(id: number): Promise<void> {
  await dataOf(api.delete<ApiEnvelope<null>>(`/shift-templates/${id}`))
}

export async function fetchShiftGrid(from: string, to: string): Promise<ShiftGrid> {
  return dataOf(api.get<ApiEnvelope<ShiftGrid>>('/shifts', { params: { from, to } }))
}

/** Returns how many person-days were written. */
export async function assignShifts(input: AssignShiftsInput): Promise<number> {
  return (await dataOf(api.post<ApiEnvelope<{ cells: number }>>('/shifts/assign', input))).cells
}

/** RF-06.02 free grid selection in one request. Returns how many person-days were written. */
export async function setShiftCells(input: ShiftCellsInput): Promise<number> {
  return (await dataOf(api.put<ApiEnvelope<{ cells: number }>>('/shifts/cells', input))).cells
}

/** `weekStart` is the Monday of the week that becomes a copy of the previous one. Returns shifts copied. */
export async function copyPreviousWeek(weekStart: string): Promise<number> {
  const result = await dataOf(api.post<ApiEnvelope<{ copied: number }>>('/shifts/copy-previous-week', { week_start: weekStart }))
  return result.copied
}
