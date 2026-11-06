import { useId, useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ErrorAlert, SuccessNotice } from '@/components/ui/ErrorAlert'
import { inputClassName, TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import { CompanySettingsFields } from './CompanySettingsFields'
import type { CompanyProfile, UpdateCompanyInput } from './types'
import { useUpdateCompany } from './useCompany'

function timezoneOptions(current: string): string[] {
  const zones = Intl.supportedValuesOf('timeZone')
  return zones.includes(current) ? zones : [current, ...zones]
}

function toInput(company: CompanyProfile): UpdateCompanyInput {
  const { name, tax_id, currency, timezone, settings } = company
  return { name, tax_id, currency, timezone, settings }
}

interface CompanyFormProps {
  company: CompanyProfile
  canEdit: boolean
}

export function CompanyForm({ company, canEdit }: CompanyFormProps) {
  const [draft, setDraft] = useState(() => toInput(company))
  const mutation = useUpdateCompany()
  const timezoneId = useId()

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    mutation.mutate({ ...draft, tax_id: draft.tax_id?.trim() || null })
  }

  const timezoneError = fieldErrorOf(mutation.error, 'timezone')

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <fieldset className="grid gap-4 sm:grid-cols-2" disabled={!canEdit}>
        <legend className="mb-2 font-medium">Datos de la empresa</legend>
        <TextField
          label="Nombre"
          required
          maxLength={120}
          value={draft.name}
          error={fieldErrorOf(mutation.error, 'name')}
          onChange={(event) => setDraft({ ...draft, name: event.target.value })}
        />
        <TextField
          label="CUIT / identificación fiscal"
          maxLength={20}
          value={draft.tax_id ?? ''}
          error={fieldErrorOf(mutation.error, 'tax_id')}
          onChange={(event) => setDraft({ ...draft, tax_id: event.target.value })}
        />
        <TextField
          label="Moneda (código ISO)"
          required
          maxLength={3}
          hint="Por ejemplo ARS o USD."
          value={draft.currency}
          error={fieldErrorOf(mutation.error, 'currency')}
          onChange={(event) => setDraft({ ...draft, currency: event.target.value.toUpperCase() })}
        />
        <div className="space-y-1">
          <label htmlFor={timezoneId} className="block text-sm text-muted">
            Zona horaria
          </label>
          <select
            id={timezoneId}
            value={draft.timezone}
            aria-invalid={timezoneError ? true : undefined}
            onChange={(event) => setDraft({ ...draft, timezone: event.target.value })}
            className={inputClassName}
          >
            {timezoneOptions(draft.timezone).map((zone) => (
              <option key={zone} value={zone}>
                {zone}
              </option>
            ))}
          </select>
          {timezoneError && <p className="text-sm text-danger">{timezoneError}</p>}
        </div>
      </fieldset>
      <CompanySettingsFields
        value={draft.settings}
        onChange={(settings) => setDraft({ ...draft, settings })}
        error={mutation.error}
        disabled={!canEdit}
      />
      <ErrorAlert error={mutation.error} />
      {mutation.isSuccess && <SuccessNotice message="Configuración guardada." />}
      {canEdit && (
        <Button type="submit" disabled={mutation.isPending}>
          {mutation.isPending ? 'Guardando…' : 'Guardar cambios'}
        </Button>
      )}
    </form>
  )
}
