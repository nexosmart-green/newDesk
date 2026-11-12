import type { CatalogType } from '@/features/catalogs/types'
import { hasSensitiveFields, type PersonProfile, type SensitiveProfileFields, type UpdateEmployeeInput } from './types'

export interface TextFieldConfig {
  name: string
  label: string
  type?: 'text' | 'date' | 'tel'
  required?: boolean
}

interface CatalogFieldConfig {
  name: 'job_title_id' | 'area_id' | 'employment_type_id'
  label: string
  catalog: CatalogType
}

export const identityFields: TextFieldConfig[] = [
  { name: 'first_name', label: 'Nombre', required: true },
  { name: 'last_name', label: 'Apellido', required: true },
  { name: 'employee_code', label: 'Legajo' },
  { name: 'phone', label: 'Teléfono', type: 'tel' },
]

export const catalogFields: CatalogFieldConfig[] = [
  { name: 'job_title_id', label: 'Puesto', catalog: 'job-titles' },
  { name: 'area_id', label: 'Área', catalog: 'areas' },
  { name: 'employment_type_id', label: 'Tipo de empleo', catalog: 'employment-types' },
]

export const employmentDateFields: TextFieldConfig[] = [
  { name: 'hired_at', label: 'Ingreso', type: 'date' },
  { name: 'probation_ends_at', label: 'Fin del período de prueba', type: 'date' },
  { name: 'left_at', label: 'Egreso', type: 'date' },
]

/** RNF-06: only rendered when the backend sent them (the viewer may see them). */
export const sensitiveFields: (TextFieldConfig & { name: keyof SensitiveProfileFields })[] = [
  { name: 'address', label: 'Dirección' },
  { name: 'birth_date', label: 'Fecha de nacimiento', type: 'date' },
  { name: 'gender', label: 'Género' },
  { name: 'cuil', label: 'CUIL' },
  { name: 'emergency_contact', label: 'Contacto de emergencia' },
]

const ID_FIELDS = ['job_title_id', 'area_id', 'employment_type_id', 'reports_to_user_id']

type EmployeeForm = Record<string, string>

export function initialEmployeeForm(person: PersonProfile): EmployeeForm {
  const text = (value: string | null | undefined) => value ?? ''
  const id = (value: { id: number } | null) => (value ? String(value.id) : '')
  const form: EmployeeForm = {
    first_name: person.first_name,
    last_name: person.last_name,
    employee_code: text(person.employee_code),
    phone: text(person.phone),
    about: text(person.about),
    hired_at: text(person.hired_at),
    probation_ends_at: text(person.probation_ends_at),
    left_at: text(person.left_at),
    job_title_id: id(person.job_title),
    area_id: id(person.area),
    employment_type_id: id(person.employment_type),
    reports_to_user_id: id(person.reports_to),
  }
  if (hasSensitiveFields(person)) {
    for (const field of sensitiveFields) form[field.name] = text(person[field.name])
  }
  return form
}

/** Empty strings become null (clear the value); ids become numbers. */
export function toEmployeeInput(form: EmployeeForm): UpdateEmployeeInput {
  const input: UpdateEmployeeInput = {}
  for (const [name, raw] of Object.entries(form)) {
    const value = raw.trim()
    if (ID_FIELDS.includes(name)) input[name] = value ? Number(value) : null
    else input[name] = value === '' && name !== 'first_name' && name !== 'last_name' ? null : value
  }
  return input
}
