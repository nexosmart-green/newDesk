import { Check, type LucideIcon } from 'lucide-react'
import { useEffect, useId, useRef, useState, type KeyboardEvent, type ReactNode } from 'react'

export interface MenuItem {
  label: string
  icon?: LucideIcon
  onSelect: () => void
  /** Choice menus (a status): the current one is marked and announced as checked. */
  checked?: boolean
  disabled?: boolean
}

interface MenuProps {
  /** Accessible name of the trigger when its content alone doesn't say it (an icon, a badge). */
  label: string
  /** What the trigger shows. */
  trigger: ReactNode
  triggerClassName: string
  items: MenuItem[]
  /** Which edge of the trigger the menu lines up with. */
  align?: 'start' | 'end'
}

const itemClassName =
  'flex min-h-9 w-full cursor-pointer items-center gap-2.5 px-3 text-left text-sm text-text coarse:min-h-11 hover:bg-text/5 focus-visible:bg-text/5 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50'

/**
 * A small action menu (WAI-ARIA menu button): opens on click, first item focused, arrows move, Escape or a
 * click outside closes it and focus goes back to the trigger. Choosing an item closes the menu first, so a
 * dialog it opens takes focus cleanly.
 */
export function Menu({ label, trigger, triggerClassName, items, align = 'end' }: MenuProps) {
  const [open, setOpen] = useState(false)
  const menuId = useId()
  const containerRef = useRef<HTMLDivElement>(null)
  const triggerRef = useRef<HTMLButtonElement>(null)
  const itemRefs = useRef<(HTMLButtonElement | null)[]>([])

  useEffect(() => {
    if (!open) return
    itemRefs.current.find((item) => item && !item.disabled)?.focus()
    function closeOnOutsideClick(event: PointerEvent) {
      if (event.target instanceof Node && !containerRef.current?.contains(event.target)) setOpen(false)
    }
    document.addEventListener('pointerdown', closeOnOutsideClick)
    return () => document.removeEventListener('pointerdown', closeOnOutsideClick)
  }, [open])

  function close() {
    setOpen(false)
    triggerRef.current?.focus()
  }

  function moveFocus(event: KeyboardEvent<HTMLDivElement>) {
    const enabled = itemRefs.current.filter((item): item is HTMLButtonElement => item !== null && !item.disabled)
    const index = enabled.indexOf(document.activeElement as HTMLButtonElement)
    const next = { ArrowDown: index + 1, ArrowUp: index - 1, Home: 0, End: enabled.length - 1 }[event.key]
    if (next === undefined || enabled.length === 0) return
    event.preventDefault()
    enabled[(next + enabled.length) % enabled.length]?.focus()
  }

  return (
    <div
      ref={containerRef}
      className="relative inline-flex"
      onKeyDown={(event) => {
        if (event.key === 'Escape' && open) {
          event.stopPropagation()
          close()
        }
      }}
    >
      <button
        ref={triggerRef}
        type="button"
        aria-label={label}
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        onClick={() => setOpen(!open)}
        className={`cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent ${triggerClassName}`}
      >
        {trigger}
      </button>
      {open && (
        <div
          id={menuId}
          role="menu"
          aria-label={label}
          onKeyDown={moveFocus}
          className={`absolute top-full z-40 mt-1.5 min-w-52 overflow-hidden rounded-xl border border-border bg-raised py-1 shadow-lg ${align === 'end' ? 'right-0' : 'left-0'}`}
        >
          {items.map((item, index) => {
            const Icon = item.icon
            const choice = item.checked !== undefined
            return (
              <button
                key={item.label}
                ref={(element) => {
                  itemRefs.current[index] = element
                }}
                type="button"
                role={choice ? 'menuitemradio' : 'menuitem'}
                aria-checked={choice ? item.checked : undefined}
                tabIndex={-1}
                disabled={item.disabled}
                onClick={() => {
                  close()
                  item.onSelect()
                }}
                className={itemClassName}
              >
                {choice ? (
                  <Check aria-hidden="true" className={`size-4 shrink-0 text-accent ${item.checked ? '' : 'invisible'}`} />
                ) : (
                  Icon && <Icon aria-hidden="true" className="size-4 shrink-0 text-muted" strokeWidth={1.75} />
                )}
                {item.label}
              </button>
            )
          })}
        </div>
      )}
    </div>
  )
}
