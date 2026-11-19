import { useSyncExternalStore } from 'react'

/** While the socket tells us about changes, polling only guards against a missed signal. */
export const SAFETY_NET_POLL_MS = 5 * 60_000

let live = false
const listeners = new Set<() => void>()

/** True while subscribed to the person's channel (RNF-12); false without Reverb, while connecting or after a drop. */
export function setRealtimeLive(next: boolean) {
  if (next === live) return
  live = next
  for (const listener of listeners) listener()
}

function subscribe(listener: () => void) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

const isLive = () => live

export function useRealtimeLive(): boolean {
  return useSyncExternalStore(subscribe, isLive, isLive)
}

/** The usual poll without the socket; the slow safety net with it. */
export function usePollInterval(usualMs: number): number {
  return useRealtimeLive() ? Math.max(usualMs, SAFETY_NET_POLL_MS) : usualMs
}
