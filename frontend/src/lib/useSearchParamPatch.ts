import { useCallback } from 'react'
import { useSearchParams } from 'react-router'

type SearchParamPatch = Record<string, string | null>

/**
 * The URL's search params plus `patch`, which sets (or, with `null`, removes) only the given keys and keeps
 * the rest. Replaces the history entry: filters and tabs shouldn't pile up behind "Atrás".
 */
export function useSearchParamPatch() {
  const [searchParams, setSearchParams] = useSearchParams()
  const patch = useCallback(
    (changes: SearchParamPatch) =>
      setSearchParams(
        (current) => {
          const next = new URLSearchParams(current)
          for (const [key, value] of Object.entries(changes)) {
            if (value === null) next.delete(key)
            else next.set(key, value)
          }
          return next
        },
        { replace: true },
      ),
    [setSearchParams],
  )
  return [searchParams, patch] as const
}

/**
 * A tab (or view) kept in `?<param>=` so a link or a reload lands on the same one. The default tab leaves the
 * param out; unknown values fall back to it.
 */
export function useTabParam<Tab extends string>(tabs: readonly Tab[], fallback: Tab, param = 'tab') {
  const [searchParams, patch] = useSearchParamPatch()
  const requested = searchParams.get(param)
  const selected = tabs.find((tab) => tab === requested) ?? fallback
  const select = (tab: Tab) => patch({ [param]: tab === fallback ? null : tab })
  return { selected, select }
}
