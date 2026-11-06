import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ErrorAlert, SuccessNotice } from '@/components/ui/ErrorAlert'
import { TextAreaField } from '@/components/ui/TextAreaField'
import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { CatalogSelect } from './CatalogSelect'
import {
  catalogFields,
  employmentDateFields,
  identityFields,
  initialEmployeeForm,
  sensitiveFields,
  toEmployeeInput,
  type TextFieldConfig,
} from './employeeFields'
import { PersonSelect } from './PersonSelect'
import { hasSensitiveFields, type PersonProfile } from './types'
import { useUpdatePerson } from './usePeople'

/** RF-01.09 admin side: employment data, "reporta a", and sensitive fields when visible. */
export function EmployeeProfileForm({ person }: { person: PersonProfile }) {
  const [form, setForm] = useState(() => initialEmployeeForm(person))
  const mutation = useUpdatePerson(person.id)
  const errorOf = (field: string) => fieldErrorOf(mutation.error, field)
  const set = (name: string, value: string) => setForm((current) => ({ ...current, [name]: value }))

  function renderText(field: TextFieldConfig) {
    return (
      <TextField
        key={field.name}
        label={field.label}
        type={field.type ?? 'text'}
        required={field.required}
        value={form[field.name] ?? ''}
        error={errorOf(field.name)}
        onChange={(event) => set(field.name, event.target.value)}
      />
    )
  }

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    mutation.mutate(toEmployeeInput(form))
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6" noValidate>
      <fieldset className="grid gap-4 sm:grid-cols-2">
        <legend className="mb-2 font-semibold">Datos personales</legend>
        {identityFields.map(renderText)}
      </fieldset>
      <fieldset className="grid gap-4 sm:grid-cols-2">
        <legend className="mb-2 font-semibold">Datos laborales</legend>
        {catalogFields.map((field) => (
          <CatalogSelect
            key={field.name}
            label={field.label}
            catalog={field.catalog}
            value={form[field.name] ?? ''}
            error={errorOf(field.name)}
            onChange={(value) => set(field.name, value)}
          />
        ))}
        <PersonSelect
          label="Reporta a"
          emptyLabel="Nadie"
          value={form.reports_to_user_id ? Number(form.reports_to_user_id) : null}
          current={person.reports_to}
          excludeIds={[person.id]}
          error={errorOf('reports_to_user_id')}
          onChange={(id) => set('reports_to_user_id', id ? String(id) : '')}
        />
        {employmentDateFields.map(renderText)}
      </fieldset>
      {hasSensitiveFields(person) && (
        <fieldset className="grid gap-4 sm:grid-cols-2">
          <legend className="mb-2 font-semibold">Datos sensibles</legend>
          {sensitiveFields.map(renderText)}
        </fieldset>
      )}
      <TextAreaField
        label="Sobre mí"
        value={form.about ?? ''}
        error={errorOf('about')}
        onChange={(event) => set('about', event.target.value)}
      />
      <SuccessNotice message={mutation.isSuccess ? 'Perfil actualizado.' : null} />
      <ErrorAlert error={mutation.error} />
      <Button type="submit" disabled={mutation.isPending}>
        {mutation.isPending ? 'Guardando…' : 'Guardar cambios'}
      </Button>
    </form>
  )
}
