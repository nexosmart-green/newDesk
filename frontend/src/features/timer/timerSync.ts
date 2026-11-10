/**
 * RNF-12: when one tab starts/stops the timer, the user's other tabs refetch at once instead of
 * waiting for the 30 s poll. BroadcastChannel where available, the `storage` event otherwise.
 *
 * A BroadcastChannel delivers to every other channel object with the same name — including the
 * subscriber in the *sending* tab — so messages carry this tab's id and the sender ignores its own
 * (it already refreshed; a second invalidation would cancel and repeat that fetch).
 */
const CHANNEL_NAME = 'newdesk-timer'
const STORAGE_KEY = 'newdesk:timer-changed'
const TAB_ID = `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`

function openChannel(): BroadcastChannel | null {
  return typeof BroadcastChannel === 'undefined' ? null : new BroadcastChannel(CHANNEL_NAME)
}

export function broadcastTimerChanged(): void {
  const channel = openChannel()
  if (channel) {
    channel.postMessage(TAB_ID)
    channel.close()
    return
  }
  try {
    // `storage` events never fire in the tab that wrote, so no id is needed here.
    window.localStorage.setItem(STORAGE_KEY, String(Date.now()))
  } catch {
    // No storage: other tabs still catch up on focus or on the next poll.
  }
}

/** Calls `onChange` when another tab reports a timer change. Returns the unsubscribe function. */
export function subscribeTimerChanged(onChange: () => void): () => void {
  const channel = openChannel()
  if (channel) {
    channel.onmessage = (event: MessageEvent) => {
      if (event.data !== TAB_ID) onChange()
    }
    return () => channel.close()
  }
  const onStorage = (event: StorageEvent) => {
    if (event.key === STORAGE_KEY) onChange()
  }
  window.addEventListener('storage', onStorage)
  return () => window.removeEventListener('storage', onStorage)
}
