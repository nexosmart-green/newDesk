import type { ApiError } from './api'

/**
 * Which validation keys some input on screen is showing. `fieldErrorOf` claims them while the form
 * renders; whatever nobody claims (`assignee_ids.3`, `permissions.5`… with no input of their own)
 * would otherwise vanish, so `lib/queryClient.ts` toasts it.
 */
const claimedKeys = new WeakMap<ApiError, Set<string>>()

function claim(error: ApiError, key: string) {
  const keys = claimedKeys.get(error) ?? new Set<string>()
  keys.add(key)
  claimedKeys.set(error, keys)
}

/** `field` matches its own key and its array items (`ids` → `ids`, `ids.0`, `ids.3.name`). */
function keysOf(error: ApiError, field: string): string[] {
  return Object.keys(error.fieldErrors).filter((key) => key === field || key.startsWith(`${field}.`))
}

export function claimFieldError(error: ApiError, field: string): string | undefined {
  const keys = keysOf(error, field)
  keys.forEach((key) => claim(error, key))
  const first = keys[0]
  return first ? error.fieldErrors[first]?.[0] : undefined
}

/** For alerts that show the error list themselves (confirm dialogs, inline row errors). */
export function claimAllFieldErrors(error: ApiError) {
  Object.keys(error.fieldErrors).forEach((key) => claim(error, key))
}

/** The first message no input is showing, if any. */
export function unclaimedFieldMessage(error: ApiError): string | undefined {
  const claimed = claimedKeys.get(error)
  const key = Object.keys(error.fieldErrors).find((candidate) => !claimed?.has(candidate))
  return key ? error.fieldErrors[key]?.[0] : undefined
}

export function hasClaimedFieldErrors(error: ApiError): boolean {
  return (claimedKeys.get(error)?.size ?? 0) > 0
}
