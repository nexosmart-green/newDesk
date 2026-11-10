import { TextField } from '@/components/ui/TextField'
import { fieldErrorOf } from '@/lib/api'
import type { CompanySettings } from './types'

interface CompanySettingsFieldsProps {
  value: CompanySettings
  onChange: (value: CompanySettings) => void
  error: unknown
  disabled: boolean
}

type ScalarSetting = Exclude<keyof CompanySettings, 'hour_alert_thresholds'>

const scalarFields: { key: ScalarSetting; label: string; hint: string; min: number; max: number }[] = [
  {
    key: 'manual_entry_days_back',
    label: 'Días hacia atrás para cargar horas a mano',
    hint: '0 = solo el día de hoy.',
    min: 0,
    max: 60,
  },
  {
    key: 'forgotten_timer_max_hours',
    label: 'Horas máximas de un cronómetro olvidado',
    hint: 'Pasado este tiempo se corta solo.',
    min: 1,
    max: 24,
  },
  {
    key: 'forgotten_timer_after_shift_hours',
    label: 'Horas después del turno para avisar',
    hint: 'Aviso de cronómetro que sigue corriendo.',
    min: 0,
    max: 12,
  },
]

export function CompanySettingsFields({ value, onChange, error, disabled }: CompanySettingsFieldsProps) {
  const [warning, critical] = value.hour_alert_thresholds

  return (
    <fieldset className="grid gap-4 sm:grid-cols-2" disabled={disabled}>
      <legend className="mb-2 font-medium">Reglas de horas</legend>
      {scalarFields.map((field) => (
        <TextField
          key={field.key}
          label={field.label}
          hint={field.hint}
          type="number"
          required
          min={field.min}
          max={field.max}
          value={value[field.key]}
          error={fieldErrorOf(error, `settings.${field.key}`)}
          onChange={(event) => onChange({ ...value, [field.key]: Number(event.target.value) })}
        />
      ))}
      <TextField
        label="Alerta de horas: aviso (%)"
        type="number"
        required
        min={1}
        max={99}
        value={warning}
        error={fieldErrorOf(error, 'settings.hour_alert_thresholds.0') ?? fieldErrorOf(error, 'settings.hour_alert_thresholds')}
        onChange={(event) => onChange({ ...value, hour_alert_thresholds: [Number(event.target.value), critical] })}
      />
      <TextField
        label="Alerta de horas: crítico (%)"
        hint="Tiene que ser mayor que el aviso."
        type="number"
        required
        min={1}
        max={100}
        value={critical}
        error={fieldErrorOf(error, 'settings.hour_alert_thresholds.1')}
        onChange={(event) => onChange({ ...value, hour_alert_thresholds: [warning, Number(event.target.value)] })}
      />
    </fieldset>
  )
}
