import { MutationCache, QueryCache, QueryClient } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import { ApiError } from './api'
import { errorTextOf, isUnexpectedError } from './errorText'

export const TWO_FACTOR_SETUP_REQUIRED = 'two_factor_setup_required'

/**
 * Mutation feedback convention (one place, so screens don't each invent theirs):
 * - `meta.successMessage`: toast on success. Dynamic texts (e.g. the backend's "Tarea bhn-3 creada.")
 *   call `toast.success()` from the hook's own `onSuccess` instead.
 * - Errors toast by default: business rules with the backend message, network/5xx with a fixed text.
 *   Field validation errors never toast: they render under their inputs.
 * - `meta.silentError: true`: the component explains the error itself (timer blocks, auth forms…).
 */
declare module '@tanstack/react-query' {
  interface Register {
    mutationMeta: { successMessage?: string; silentError?: boolean }
  }
}

interface CreateQueryClientOptions {
  /** Tests: no retries, so failures surface at once. */
  retry?: boolean
}

export function createQueryClient({ retry = true }: CreateQueryClientOptions = {}): QueryClient {
  const client: QueryClient = new QueryClient({
    queryCache: new QueryCache({
      onError: (error, query) => {
        // A stale /me may still claim 2FA is fine; refetching it lets RequireAuth force the setup page.
        if (error instanceof ApiError && error.code === TWO_FACTOR_SETUP_REQUIRED) {
          void client.invalidateQueries({ queryKey: ['me'] })
          return
        }
        // First loads show their own error state; a failing background refresh would go unnoticed.
        if (query.state.data !== undefined && isUnexpectedError(error)) {
          const text = errorTextOf(error)
          toast.error(text.message, { title: text.title })
        }
      },
    }),
    mutationCache: new MutationCache({
      onSuccess: (_data, _variables, _context, mutation) => {
        if (mutation.meta?.successMessage) toast.success(mutation.meta.successMessage)
      },
      onError: (error, _variables, _context, mutation) => {
        if (mutation.meta?.silentError) return
        if (error instanceof ApiError && error.hasFieldErrors()) return
        const text = errorTextOf(error)
        toast.error(text.message, { title: text.title })
      },
    }),
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        retry: retry
          ? (failureCount, error) => !(error instanceof ApiError && error.status >= 400 && error.status < 500) && failureCount < 2
          : false,
      },
      mutations: { retry: false },
    },
  })
  return client
}

export const queryClient: QueryClient = createQueryClient()
