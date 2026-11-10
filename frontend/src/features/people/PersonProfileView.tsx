import type { ReactNode } from 'react'
import { formatDate } from '@/lib/dates'
import { hasSensitiveFields, type PersonProfile } from './types'

function Row({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="grid gap-1 py-2 sm:grid-cols-[12rem_1fr]">
      <dt className="text-sm text-muted">{label}</dt>
      <dd className="text-sm">{children || '—'}</dd>
    </div>
  )
}

/** Read-only profile for colleagues; sensitive fields only appear when the backend sends them. */
export function PersonProfileView({ person }: { person: PersonProfile }) {
  return (
    <dl className="divide-y divide-border">
      <Row label="Puesto">{person.job_title?.name}</Row>
      <Row label="Área">{person.area?.name}</Row>
      <Row label="Tipo de empleo">{person.employment_type?.name}</Row>
      <Row label="Reporta a">{person.reports_to?.full_name}</Row>
      <Row label="Teléfono">{person.phone}</Row>
      <Row label="Ingreso">{person.hired_at && formatDate(person.hired_at)}</Row>
      <Row label="Sobre mí">{person.about && <span className="whitespace-pre-line">{person.about}</span>}</Row>
      <Row label="Habilidades">
        {person.skills?.map((skill) => `${skill.name} (${skill.level})`).join(', ')}
      </Row>
      {hasSensitiveFields(person) && (
        <>
          <Row label="Dirección">{person.address}</Row>
          <Row label="CUIL">{person.cuil}</Row>
          <Row label="Contacto de emergencia">{person.emergency_contact}</Row>
        </>
      )}
    </dl>
  )
}
