/**
 * RNF-12: when one tab starts/stops the timer, the user's other tabs refetch at once instead of
 * waiting for the 30 s poll. BroadcastChannel where available, the `storage` event otherwise.
 */
const CHANNEL_NAME = 'newdesk-timer'
const STORAGE_KEY = 'newdesk:timer-changed'

function openChannel(): BroadcastChannel | null {
  return typeof BroadcastChannel === 'undefined' ? null : new BroadcastChannel(CHANNEL_NAME)
}

export function broadcastTimerChanged(): void {
  const channel = openChannel()
  if (channel) {
    channel.postMessage('changed')
    channel.close()
    return
  }
  try {
    window.localStorage.setItem(STORAGE_KEY, String(Date.now()))
  } catch {
    // No storage: other tabs still catch up on focus or on the next poll.
  }
}

/** Calls `onChange` when another tab reports a timer change. Returns the unsubscribe function. */
export function subscribeTimerChanged(onChange: () => void): () => void {
  const channel = openChannel()
  if (channel) {
    channel.onmessage = () => onChange()
    return () => channel.close()
  }
  const onStorage = (event: StorageEvent) => {
    if (event.key === STORAGE_KEY) onChange()
  }
  window.addEventListener('storage', onStorage)
  return () => window.removeEventListener('storage', onStorage)
}
