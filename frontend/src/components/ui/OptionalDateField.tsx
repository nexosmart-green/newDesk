import { X } from 'lucide-react'
import { TextField } from './TextField'

interface OptionalDateFieldProps {
  label: string
  value: string
  onChange: (value: string) => void
  error?: string
  hint?: string
}

/** A date that may stay empty: labelled "(opcional)" and clearable in one tap (sent as `null`). */
export function OptionalDateField({ label, value, onChange, error, hint }: OptionalDateFieldProps) {
  return (
    <div className="relative">
      <TextField
        label={`${label} (opcional)`}
        type="date"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        error={error}
        hint={hint}
        className="[&_input]:pr-12"
      />
      {value && (
        <button
          type="button"
          onClick={() => onChange('')}
          aria-label={`Quitar ${label.toLowerCase()}`}
          title="Quitar fecha"
          className="absolute right-0.5 top-[1.6rem] grid size-10 cursor-pointer place-items-center rounded-lg text-muted hover:bg-surface-2 hover:text-text focus-visible:outline-2 focus-visible:outline-accent"
        >
          <X aria-hidden="true" className="size-4" />
        </button>
      )}
    </div>
  )
}
