import { useSyncExternalStore } from 'react'

// Escritorio = el breakpoint lg de Tailwind (64rem), el mismo del sidebar.
export const DESKTOP = '(min-width: 64rem)'

export function useMediaQuery(query: string): boolean {
  return useSyncExternalStore(
    (onChange) => {
      const list = window.matchMedia(query)
      list.addEventListener('change', onChange)
      return () => list.removeEventListener('change', onChange)
    },
    () => window.matchMedia(query).matches,
  )
}
