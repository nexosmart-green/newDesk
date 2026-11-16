import { useIsFetching, useIsMutating } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useNavigation } from 'react-router'

/** Polls that run on their own (timer every 30 s, the bell) would make the bar blink: they don't count. */
const QUIET_KEYS = new Set(['timer', 'notifications', 'me'])
const SHOW_AFTER_MS = 250

/**
 * Thin teal line under the header while something loads or saves in the background, or while the next
 * page's code arrives (lazy routes keep the current page on screen meanwhile). Waits 250 ms so fast
 * responses never flash it.
 */
export function FetchingBar() {
  const fetching = useIsFetching({ predicate: (query) => !QUIET_KEYS.has(String(query.queryKey[0])) })
  const mutating = useIsMutating()
  const navigating = useNavigation().state !== 'idle'
  const busy = fetching + mutating > 0 || navigating
  const [delayPassed, setDelayPassed] = useState(false)
  const visible = busy && delayPassed

  useEffect(() => {
    if (!busy) return
    const timeout = setTimeout(() => setDelayPassed(true), SHOW_AFTER_MS)
    return () => {
      clearTimeout(timeout)
      setDelayPassed(false)
    }
  }, [busy])

  return (
    <div aria-hidden="true" data-state={visible ? 'busy' : 'idle'} className="pointer-events-none sticky top-16 z-30 h-0.5 overflow-hidden">
      {visible && (
        <div className="h-full w-1/4 rounded-full bg-brand motion-safe:animate-[progress-slide_1.1s_ease-in-out_infinite] motion-reduce:w-full motion-reduce:opacity-60" />
      )}
    </div>
  )
}
