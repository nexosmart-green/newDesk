import { useId, type CSSProperties, type KeyboardEvent } from 'react'
import { Icon, type IconName } from './Icon'

interface TabsProps<T extends string> {
  tabs: readonly { id: T; label: string; badge?: number }[]
  active: T
  onChange: (id: T) => void
  label: string
}

// Pestañas con scroll horizontal en mobile. Flechas izq/der mueven el foco. La pastilla la anima el CSS (.tabs).
export function Tabs<T extends string>({ tabs, active, onChange, label }: TabsProps<T>) {
  const onKeyDown = (event: KeyboardEvent, index: number) => {
    if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') return
    const next = tabs[(index + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length]
    if (next) {
      onChange(next.id)
      document.getElementById(`tab-${next.id}`)?.focus()
    }
  }

  return (
    <div role="tablist" aria-label={label} className="tabs">
      {tabs.map((tab, index) => {
        const selected = tab.id === active
        return (
          <button
            key={tab.id}
            id={`tab-${tab.id}`}
            role="tab"
            type="button"
            aria-selected={selected}
            tabIndex={selected ? 0 : -1}
            onClick={() => onChange(tab.id)}
            onKeyDown={(event) => onKeyDown(event, index)}
          >
            {tab.label}
            {tab.badge ? <span className="num rounded-full bg-crit/12 px-1.5 text-[0.6875rem] leading-[1.125rem] font-semibold text-crit">{tab.badge}</span> : null}
          </button>
        )
      })}
    </div>
  )
}

interface SegmentedProps<T extends string> {
  label: string
  options: readonly { value: T; label: string; icon?: IconName }[]
  value: T
  onChange: (value: T) => void
}

// Control segmentado con radios nativos (flechas y foco los da el navegador). El fondo se traslada por CSS.
export function Segmented<T extends string>({ label, options, value, onChange }: SegmentedProps<T>) {
  const name = useId()
  const index = Math.max(0, options.findIndex((option) => option.value === value))
  return (
    <fieldset className="segmented" style={{ '--n': options.length, '--i': index } as CSSProperties}>
      <legend className="sr-only">{label}</legend>
      {options.map((option) => (
        <label key={option.value} title={option.icon ? option.label : undefined}>
          <input type="radio" className="sr-only" name={name} value={option.value} checked={option.value === value} onChange={() => onChange(option.value)} />
          {option.icon ? (
            <>
              <Icon name={option.icon} size={14} />
              <span className="sr-only">{option.label}</span>
            </>
          ) : (
            option.label
          )}
        </label>
      ))}
    </fieldset>
  )
}
