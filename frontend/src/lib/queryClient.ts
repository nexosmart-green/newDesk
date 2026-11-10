import { QueryCache, QueryClient } from '@tanstack/react-query'
import { ApiError } from './api'

export const TWO_FACTOR_SETUP_REQUIRED = 'two_factor_setup_required'

export const queryClient: QueryClient = new QueryClient({
  queryCache: new QueryCache({
    // A stale /me may still claim 2FA is fine; refetching it lets RequireAuth force the setup page.
    onError: (error) => {
      if (error instanceof ApiError && error.code === TWO_FACTOR_SETUP_REQUIRED) {
        void queryClient.invalidateQueries({ queryKey: ['me'] })
      }
    },
  }),
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      retry: (failureCount, error) =>
        !(error instanceof ApiError && error.status >= 400 && error.status < 500) && failureCount < 2,
    },
  },
})
