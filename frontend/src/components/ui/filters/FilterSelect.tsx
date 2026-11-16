import { ChevronDown } from 'lucide-react'

export interface FilterOption {
  value: string
  label: string
}

interface FilterSelectProps {
  /** The dimension: accessible name and the visible prefix ("Estado: Abiertas"). */
  label: string
  value: string
  /** The first option is the "no filter" one (Todos, Todas, Cualquiera). */
  options: readonly FilterOption[]
  onChange: (value: string) => void
  disabled?: boolean
  className?: string
}

/**
 * A native select (keyboard, screen readers and the phone's picker for free) dressed as one compact
 * control whose text names the dimension. A filter in use reads in full ink with a teal edge.
 */
export function FilterSelect({ label, value, options, onChange, disabled = false, className = '' }: FilterSelectProps) {
  const selected = options.find((option) => option.value === value) ?? options[0]
  const active = selected !== undefined && selected !== options[0]

  return (
    <div
      className={`relative inline-flex min-h-9 min-w-0 items-center gap-1.5 rounded-md border pl-3 pr-2 text-sm transition-colors coarse:min-h-11 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-accent/40 ${
        active ? 'border-accent/60 bg-field text-text' : 'border-field-border bg-field text-muted hover:border-muted/60 hover:text-text'
      } ${disabled ? 'opacity-50' : ''} ${className}`}
    >
      <span aria-hidden="true" className="min-w-0 flex-1 truncate">
        {label}: <span className={active ? 'font-medium' : 'text-text'}>{selected?.label}</span>
      </span>
      <ChevronDown aria-hidden="true" className="size-4 shrink-0" />
      <select
        aria-label={label}
        value={value}
        disabled={disabled}
        onChange={(event) => onChange(event.target.value)}
        className="absolute inset-0 w-full cursor-pointer appearance-none opacity-0 disabled:cursor-not-allowed coarse:text-base"
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </div>
  )
}
