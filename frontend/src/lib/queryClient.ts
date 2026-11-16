import { MutationCache, QueryCache, QueryClient, type Mutation } from '@tanstack/react-query'
import { toast } from '@/components/ui/toast/toastStore'
import { ApiError } from './api'
import { errorTextOf, hiddenFieldErrorTextOf, isSessionLost, isUnexpectedError, SESSION_LOST_TEXT } from './errorText'
import { hasClaimedFieldErrors } from './fieldErrors'
import { currentUserKey } from './queryKeys'

const TWO_FACTOR_SETUP_REQUIRED = 'two_factor_setup_required'

/**
 * Field errors are claimed by the inputs that render them (`fieldErrorOf`). The form re-renders a couple
 * of ticks after `onError` (React Query notifies through `setTimeout`), so the check waits a beat.
 */
const FIELD_RENDER_GRACE_MS = 100

/**
 * Mutation feedback convention (one place, so screens don't each invent theirs):
 * - `meta.successMessage`: toast on success. Dynamic texts (e.g. the backend's "Tarea bhn-3 creada.")
 *   call `toast.success()` from the hook's own `onSuccess` instead.
 * - Errors toast by default: business rules with the backend message, network/5xx with a fixed text.
 * - Field validation errors render under their inputs and focus the first one; a key no input shows
 *   (`assignee_ids.3`, `ids.0`…) toasts its message so it never gets lost.
 * - `meta.silentError: true`: the component explains the error itself (timer blocks, auth forms…).
 * - A 401 while signed in means the session ended: one toast and back to /login (RequireAuth keeps the path).
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

function showError(error: unknown) {
  const text = errorTextOf(error)
  toast.error(text.message, { title: text.title })
}

/** The form (or dialog) the person submitted from, to move focus to its first invalid field. */
const originOf = new WeakMap<Mutation<unknown, unknown, unknown>, Element>()

function rememberOrigin(mutation: Mutation<unknown, unknown, unknown>) {
  const origin = typeof document === 'undefined' ? null : document.activeElement?.closest('form, [role="dialog"]')
  if (origin) originOf.set(mutation, origin)
}

function reportFieldErrors(error: ApiError, mutation: Mutation<unknown, unknown, unknown>) {
  setTimeout(() => {
    const hidden = hiddenFieldErrorTextOf(error)
    if (hidden) toast.error(hidden.message, { title: hidden.title })
    const origin = originOf.get(mutation)
    if (hasClaimedFieldErrors(error) && origin?.isConnected) {
      origin.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus()
    }
  }, FIELD_RENDER_GRACE_MS)
}

export function createQueryClient({ retry = true }: CreateQueryClientOptions = {}): QueryClient {
  /** Returns true when the error was a lost session (handled here: toast + sign out locally). */
  function handleSessionLost(error: unknown): boolean {
    if (!isSessionLost(error) || !client.getQueryData(currentUserKey)) return false
    toast.error(SESSION_LOST_TEXT.message, { title: SESSION_LOST_TEXT.title })
    client.removeQueries({ predicate: (query) => query.queryKey[0] !== currentUserKey[0] })
    // RequireAuth sees no user and sends them to /login with the current path to come back to.
    client.setQueryData(currentUserKey, null)
    return true
  }

  const client: QueryClient = new QueryClient({
    queryCache: new QueryCache({
      onError: (error, query) => {
        if (handleSessionLost(error)) return
        // A stale /me may still claim 2FA is fine; refetching it lets RequireAuth force the setup page.
        if (error instanceof ApiError && error.code === TWO_FACTOR_SETUP_REQUIRED) {
          void client.invalidateQueries({ queryKey: currentUserKey })
          return
        }
        // First loads show their own error state; a failing background refresh would go unnoticed.
        if (query.state.data !== undefined && isUnexpectedError(error)) showError(error)
      },
    }),
    mutationCache: new MutationCache({
      onMutate: (_variables, mutation) => rememberOrigin(mutation),
      onSuccess: (_data, _variables, _context, mutation) => {
        if (mutation.meta?.successMessage) toast.success(mutation.meta.successMessage)
      },
      onError: (error, _variables, _context, mutation) => {
        if (handleSessionLost(error)) return
        if (mutation.meta?.silentError) return
        if (error instanceof ApiError && error.hasFieldErrors()) return reportFieldErrors(error, mutation)
        showError(error)
      },
    }),
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        retry: retry
          ? (failureCount, error) => !(error instanceof ApiError && error.status >= 400 && error.status < 500) && failureCount < 2
          : false,
      },
      // Offline, a paused mutation would spin until the network came back: fail and say so instead.
      mutations: { retry: false, networkMode: 'always' },
    },
  })
  return client
}

export const queryClient: QueryClient = createQueryClient()
