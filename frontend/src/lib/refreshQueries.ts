import { hashKey, type InvalidateQueryFilters, type QueryClient } from '@tanstack/react-query'

type Refresh = InvalidateQueryFilters | readonly unknown[]

const toFilters = (refresh: Refresh): InvalidateQueryFilters =>
  Array.isArray(refresh) ? { queryKey: refresh } : (refresh as InvalidateQueryFilters)

/** Everything under `root` except `written`: a query the mutation already filled with `setQueryData`. */
export function allExcept(root: readonly unknown[], written: readonly unknown[]): InvalidateQueryFilters {
  const writtenHash = hashKey(written)
  return { queryKey: root, predicate: (query) => query.queryHash !== writtenHash }
}

/**
 * What a mutation refreshes, split by who waits for it:
 * - `now`: what the screen in front of the person shows. The returned promise (what `onSuccess` returns,
 *   so the button/dialog stays pending) resolves once it is fresh. Leave it empty when the mutation already
 *   wrote its result with `setQueryData`.
 * - `later`: everything else that may have changed, refetched in the background: nothing waits or locks.
 * Overlapping keys share one request (`later` joins a fetch `now` already started).
 */
export function refreshQueries(
  queryClient: QueryClient,
  { now = [], later = [] }: { now?: readonly Refresh[]; later?: readonly Refresh[] },
): Promise<void> {
  const awaited = Promise.all(now.map((refresh) => queryClient.invalidateQueries(toFilters(refresh))))
  for (const refresh of later) void queryClient.invalidateQueries(toFilters(refresh), { cancelRefetch: false })
  return awaited.then(() => undefined)
}
