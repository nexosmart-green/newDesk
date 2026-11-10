interface SwitchProps {
  label: string
  description?: string
  checked: boolean
  disabled?: boolean
  onChange: (checked: boolean) => void
}

/** On/off for a whole area. `role="switch"` so it announces "activado / desactivado". */
export function Switch({ label, description, checked, disabled = false, onChange }: SwitchProps) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      disabled={disabled}
      onClick={() => onChange(!checked)}
      className="group flex min-h-14 w-full cursor-pointer items-center gap-3 rounded-xl border border-border bg-surface px-4 py-2.5 text-left transition-colors hover:bg-surface-2 focus-visible:outline-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-70 disabled:hover:bg-surface aria-checked:border-brand/50"
    >
      <span className="min-w-0 flex-1">
        <span className="block text-sm font-medium">{label}</span>
        {description && <span className="block text-xs text-muted">{description}</span>}
      </span>
      <span aria-hidden="true" className="relative h-6 w-11 shrink-0 rounded-full bg-border transition-colors group-aria-checked:bg-brand-strong">
        <span className="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow transition-transform duration-200 group-aria-checked:translate-x-5" />
      </span>
    </button>
  )
}
