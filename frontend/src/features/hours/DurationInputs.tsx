import { TextField } from '@/components/ui/TextField'
import type { DurationValue } from './durationInput'

interface DurationInputsProps {
  value: DurationValue
  onChange: (value: DurationValue) => void
  error?: string
}

/** Hours + minutes side by side; the caller converts with `toSeconds`. */
export function DurationInputs({ value, onChange, error }: DurationInputsProps) {
  return (
    <div className="space-y-1">
      <div className="grid grid-cols-2 gap-3">
        <TextField
          label="Horas"
          type="number"
          inputMode="numeric"
          min={0}
          step={1}
          value={value.hours}
          onChange={(event) => onChange({ ...value, hours: event.target.value })}
        />
        <TextField
          label="Minutos"
          type="number"
          inputMode="numeric"
          min={0}
          max={59}
          step={1}
          value={value.minutes}
          onChange={(event) => onChange({ ...value, minutes: event.target.value })}
        />
      </div>
      {error && <p className="text-sm text-danger">{error}</p>}
    </div>
  )
}
