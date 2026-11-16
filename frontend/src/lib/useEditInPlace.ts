import { useEffect, useRef, useState } from 'react'

const FIELD = 'input:not([type=hidden]):not([disabled]), textarea:not([disabled]), select:not([disabled])'
const TRIGGER = '[data-edit-trigger]'

/**
 * View ↔ form swapped in place. Swapping unmounts whatever had focus, so keyboard and screen-reader users
 * would land on <body>: entering focuses the form's first field, leaving focuses the trigger again
 * (the element marked `data-edit-trigger` inside `containerRef`).
 */
export function useEditInPlace<T extends HTMLElement = HTMLDivElement>() {
  const containerRef = useRef<T>(null)
  const [editing, setEditing] = useState(false)
  const swapped = useRef(false)

  useEffect(() => {
    if (!swapped.current) return
    swapped.current = false
    const target = containerRef.current?.querySelector<HTMLElement>(editing ? FIELD : TRIGGER)
    target?.focus()
  }, [editing])

  const swap = (next: boolean) => {
    swapped.current = true
    setEditing(next)
  }

  return { containerRef, editing, start: () => swap(true), stop: () => swap(false) }
}
