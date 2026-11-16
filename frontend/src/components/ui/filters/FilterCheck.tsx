interface FilterCheckProps {
  label: string
  checked: boolean
  onChange: (checked: boolean) => void
  className?: string
}

/** A yes/no filter with the same footprint as the selects next to it. */
export function FilterCheck({ label, checked, onChange, className = '' }: FilterCheckProps) {
  return (
    <label
      className={`inline-flex min-h-9 cursor-pointer items-center gap-2 rounded-md border px-3 text-sm transition-colors coarse:min-h-11 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-accent/40 ${
        checked ? 'border-accent/60 bg-field font-medium text-text' : 'border-field-border bg-field text-muted hover:border-muted/60 hover:text-text'
      } ${className}`}
    >
      <input
        type="checkbox"
        checked={checked}
        onChange={(event) => onChange(event.target.checked)}
        className="size-4 rounded-sm accent-brand focus-visible:outline-none"
      />
      {label}
    </label>
  )
}
