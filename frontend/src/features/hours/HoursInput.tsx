import { TextField } from '@/components/ui/TextField'
import { formatDuration } from '@/lib/duration'
import { hoursToSeconds } from './durationInput'

interface HoursInputProps {
  value: string
  onChange: (value: string) => void
  /** Negative values correct a load made by mistake. */
  allowNegative?: boolean
  error?: string
}

/** One "Horas" field with decimals; the hint echoes what will be recorded ("= 2h 30m"). */
export function HoursInput({ value, onChange, allowNegative = false, error }: HoursInputProps) {
  const seconds = hoursToSeconds(value)
  const echo =
    seconds === null || seconds === 0 ? null : `${seconds < 0 ? 'Se restan' : 'Se suman'} ${formatDuration(Math.abs(seconds))}.`
  const negativeTip = allowNegative ? ' Para descontar horas cargadas de más, usá un número negativo (-1,5).' : ''
  // Once something valid is typed, the echo replaces the example (no need to explain 2,5 twice).
  const hint = echo ? `${echo}${negativeTip}` : `Podés usar decimales: 2,5 = 2h 30m.${negativeTip}`

  return (
    <TextField
      label="Horas"
      required
      inputMode="decimal"
      autoComplete="off"
      placeholder="Ej.: 2,5"
      value={value}
      onChange={(event) => onChange(event.target.value)}
      error={error}
      hint={hint}
    />
  )
}
