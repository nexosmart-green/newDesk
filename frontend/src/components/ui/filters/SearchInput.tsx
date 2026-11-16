import { Search } from 'lucide-react'

interface SearchInputProps {
  value: string
  onChange: (value: string) => void
  /** Accessible name; the placeholder says what can be searched. */
  label?: string
  placeholder?: string
  className?: string
}

/** The list's search box: magnifier inside, no visible label (the placeholder and the icon say it). */
export function SearchInput({ value, onChange, label = 'Buscar', placeholder, className = '' }: SearchInputProps) {
  return (
    <div className={`relative ${className}`}>
      <Search aria-hidden="true" className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted" />
      <input
        type="search"
        aria-label={label}
        placeholder={placeholder}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="min-h-9 w-full rounded-md border border-field-border bg-field py-1.5 pl-8 pr-3 text-sm transition-colors placeholder:text-muted hover:border-muted/60 focus-visible:border-accent focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-accent/40 coarse:min-h-11 coarse:text-base"
      />
    </div>
  )
}
