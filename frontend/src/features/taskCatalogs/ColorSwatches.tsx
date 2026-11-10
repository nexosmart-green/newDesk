import { Check } from 'lucide-react'
import { useId } from 'react'
import { LABEL_COLORS } from './catalogFormat'

interface ColorSwatchesProps {
  value: string
  onChange: (color: string) => void
  error?: string
}

/** Preset swatches (radio group) plus the native picker for any other color. */
export function ColorSwatches({ value, onChange, error }: ColorSwatchesProps) {
  const name = useId()
  const customId = useId()
  const isPreset = (LABEL_COLORS as readonly string[]).includes(value.toLowerCase())

  return (
    <fieldset className="space-y-1">
      <legend className="text-sm text-muted">Color</legend>
      <div className="flex flex-wrap items-center gap-1.5">
        {LABEL_COLORS.map((color) => {
          const checked = value.toLowerCase() === color
          return (
            <label key={color} className="relative grid size-9 cursor-pointer place-items-center rounded-full">
              <input
                type="radio"
                name={name}
                value={color}
                checked={checked}
                onChange={() => onChange(color)}
                aria-label={`Color ${color}`}
                className="peer absolute inset-0 cursor-pointer appearance-none rounded-full focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
              />
              <span
                aria-hidden="true"
                className="grid size-7 place-items-center rounded-full ring-offset-2 ring-offset-surface peer-checked:ring-2 peer-checked:ring-text"
                style={{ backgroundColor: color }}
              >
                {checked && <Check className="size-4 text-white drop-shadow" strokeWidth={3} />}
              </span>
            </label>
          )
        })}
        <label htmlFor={customId} className="ml-1 inline-flex min-h-9 cursor-pointer items-center gap-2 rounded-full border border-border px-2 text-xs text-muted hover:text-text">
          <input
            id={customId}
            type="color"
            value={value}
            onChange={(event) => onChange(event.target.value)}
            className={`size-6 cursor-pointer rounded-full border-0 bg-transparent p-0 ${isPreset ? '' : 'ring-2 ring-text'}`}
          />
          Otro
        </label>
      </div>
      {error && <p className="text-sm text-danger">{error}</p>}
    </fieldset>
  )
}
