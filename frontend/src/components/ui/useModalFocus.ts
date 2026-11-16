import { useEffect, useRef, type RefObject } from 'react'
import { useDialogStack } from './useDialogStack'

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

function focusablesOf(panel: HTMLElement): HTMLElement[] {
  return Array.from(panel.querySelectorAll<HTMLElement>(FOCUSABLE)).filter((element) => !element.hasAttribute('data-focus-guard'))
}

/**
 * Modal keyboard contract (Dialog, Lightbox): focus moves in (to `[data-autofocus]`, else the first field,
 * else the panel), Tab cycles inside, Escape closes the topmost one, focus returns to the trigger.
 * `onKeyDown` gets every other key while this modal is on top.
 */
export function useModalFocus(panelRef: RefObject<HTMLElement | null>, onClose: () => void, onKeyDown?: (event: KeyboardEvent) => void) {
  const isTopmost = useDialogStack()
  const handlers = useRef({ onClose, onKeyDown })
  useEffect(() => {
    handlers.current = { onClose, onKeyDown }
  })

  useEffect(() => {
    const previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null
    const panel = panelRef.current
    const firstField =
      panel?.querySelector<HTMLElement>('[data-autofocus]') ??
      panel?.querySelector<HTMLElement>('input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled])')
    ;(firstField ?? panel)?.focus()
    return () => {
      if (previouslyFocused?.isConnected) previouslyFocused.focus()
    }
  }, [panelRef])

  useEffect(() => {
    function handleKeyDown(event: KeyboardEvent) {
      const panel = panelRef.current
      if (!isTopmost() || !panel) return
      if (event.key === 'Escape') {
        event.stopPropagation()
        handlers.current.onClose()
        return
      }
      if (event.key !== 'Tab') {
        handlers.current.onKeyDown?.(event)
        return
      }
      const focusables = focusablesOf(panel)
      const first = focusables[0]
      const last = focusables[focusables.length - 1]
      if (!first || !last) return event.preventDefault()
      const active = document.activeElement
      if (event.shiftKey && (active === first || !panel.contains(active))) {
        event.preventDefault()
        last.focus()
      } else if (!event.shiftKey && (active === last || !panel.contains(active))) {
        event.preventDefault()
        first.focus()
      }
    }
    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [isTopmost, panelRef])
}
