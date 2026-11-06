import { useState, type FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { ErrorAlert, SuccessNotice } from '@/components/ui/ErrorAlert'
import { SelectField } from '@/components/ui/SelectField'
import { TextAreaField } from '@/components/ui/TextAreaField'
import { TextField } from '@/components/ui/TextField'
import type { Locale, PersonProfile, UpdateOwnProfileInput } from '@/features/people/types'
import { fieldErrorOf } from '@/lib/api'
import { useUpdateMyProfile } from './useMyProfile'

const locales: Record<Locale, string> = { es: 'Español', en: 'English' }
const timeZones = Intl.supportedValuesOf('timeZone')

type FormState = Record<'phone' | 'address' | 'about' | 'timezone', string> & { locale: Locale }

function initialForm(profile: PersonProfile): FormState {
  return {
    phone: profile.phone ?? '',
    address: profile.address ?? '',
    about: profile.about ?? '',
    locale: profile.locale === 'en' ? 'en' : 'es',
    timezone: profile.timezone ?? '',
  }
}

const orNull = (value: string) => (value.trim() === '' ? null : value.trim())

/** RF-01.09: contact, bio, language and time zone. Employment data is edited by an admin. */
export function OwnProfileForm({ profile }: { profile: PersonProfile }) {
  const [form, setForm] = useState(() => initialForm(profile))
  const mutation = useUpdateMyProfile()

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    const input: UpdateOwnProfileInput = {
      phone: orNull(form.phone),
      address: orNull(form.address),
      about: orNull(form.about),
      locale: form.locale,
      timezone: orNull(form.timezone),
    }
    mutation.mutate(input)
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4" noValidate>
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField label="Email" value={profile.email} disabled readOnly />
        <TextField
          label="Teléfono"
          type="tel"
          autoComplete="tel"
          value={form.phone}
          error={fieldErrorOf(mutation.error, 'phone')}
          onChange={(event) => setForm({ ...form, phone: event.target.value })}
        />
      </div>
      <TextField
        label="Dirección"
        autoComplete="street-address"
        hint="Solo la ven vos y quien administra usuarios."
        value={form.address}
        error={fieldErrorOf(mutation.error, 'address')}
        onChange={(event) => setForm({ ...form, address: event.target.value })}
      />
      <TextAreaField
        label="Sobre mí"
        maxLength={2000}
        value={form.about}
        error={fieldErrorOf(mutation.error, 'about')}
        onChange={(event) => setForm({ ...form, about: event.target.value })}
      />
      <div className="grid gap-4 sm:grid-cols-2">
        <SelectField
          label="Idioma"
          value={form.locale}
          error={fieldErrorOf(mutation.error, 'locale')}
          onChange={(event) => setForm({ ...form, locale: event.target.value === 'en' ? 'en' : 'es' })}
        >
          {Object.entries(locales).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </SelectField>
        <SelectField
          label="Zona horaria"
          value={form.timezone}
          error={fieldErrorOf(mutation.error, 'timezone')}
          onChange={(event) => setForm({ ...form, timezone: event.target.value })}
        >
          <option value="">La de la empresa</option>
          {timeZones.map((zone) => (
            <option key={zone} value={zone}>
              {zone}
            </option>
          ))}
        </SelectField>
      </div>
      <SuccessNotice message={mutation.isSuccess ? 'Perfil actualizado.' : null} />
      <ErrorAlert error={mutation.error} />
      <Button type="submit" disabled={mutation.isPending}>
        {mutation.isPending ? 'Guardando…' : 'Guardar cambios'}
      </Button>
    </form>
  )
}
