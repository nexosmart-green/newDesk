import { useId, type KeyboardEvent, type ReactNode } from 'react'

export interface TabItem<T extends string> {
  id: T
  label: string
  /** A counter next to the label (e.g. how many files); hidden when undefined or 0. */
  count?: number
}

interface TabsProps<T extends string> {
  label: string
  tabs: readonly TabItem<T>[]
  selected: T
  onSelect: (id: T) => void
  children: ReactNode
}

/** In-page tabs (WAI-ARIA tablist): arrow keys move between tabs, only the selected panel renders. */
export function Tabs<T extends string>({ label, tabs, selected, onSelect, children }: TabsProps<T>) {
  const baseId = useId()

  function handleKeyDown(event: KeyboardEvent<HTMLButtonElement>, index: number) {
    const step = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0
    if (step === 0) return
    event.preventDefault()
    const next = tabs[(index + step + tabs.length) % tabs.length]
    if (!next) return
    onSelect(next.id)
    document.getElementById(`${baseId}-tab-${next.id}`)?.focus()
  }

  return (
    <div className="space-y-4">
      <div role="tablist" aria-label={label} className="flex gap-1 overflow-x-auto border-b border-border">
        {tabs.map((tab, index) => {
          const isSelected = tab.id === selected
          return (
            <button
              key={tab.id}
              id={`${baseId}-tab-${tab.id}`}
              type="button"
              role="tab"
              aria-selected={isSelected}
              aria-controls={`${baseId}-panel`}
              tabIndex={isSelected ? 0 : -1}
              onClick={() => onSelect(tab.id)}
              onKeyDown={(event) => handleKeyDown(event, index)}
              className={`-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-sm ${isSelected ? 'border-accent text-accent' : 'border-transparent text-muted hover:text-text'}`}
            >
              {tab.label}
              {tab.count !== undefined && tab.count > 0 && (
                <span className="tabular ml-1.5 inline-grid min-w-5 place-items-center rounded-full bg-surface-2 px-1.5 text-xs font-medium text-muted">
                  {tab.count}
                </span>
              )}
            </button>
          )
        })}
      </div>
      <div id={`${baseId}-panel`} role="tabpanel" aria-labelledby={`${baseId}-tab-${selected}`}>
        {children}
      </div>
    </div>
  )
}
