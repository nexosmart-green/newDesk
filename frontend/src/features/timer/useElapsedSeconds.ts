import { useEffect, useState } from 'react'

/** Seconds elapsed = server's figure + how long ago (locally) that figure arrived. Pure for testing. */
export function elapsedSince(serverElapsedSeconds: number, receivedAtMs: number, nowMs: number): number {
  return serverElapsedSeconds + Math.max(0, Math.floor((nowMs - receivedAtMs) / 1000))
}

/**
 * RF-05.03 / RNF-04: the base is always the server's `elapsed_seconds` (computed at `server_time`);
 * the browser clock only measures the delta since the response arrived, so a wrong local clock
 * can't shift the timer. Ticks once per second while there is something to show.
 */
export function useElapsedSeconds(serverElapsedSeconds: number | null, receivedAtMs: number): number | null {
  const [nowMs, setNowMs] = useState(() => Date.now())
  const running = serverElapsedSeconds !== null

  useEffect(() => {
    if (!running) return
    const interval = setInterval(() => setNowMs(Date.now()), 1000)
    return () => clearInterval(interval)
  }, [running, receivedAtMs])

  return serverElapsedSeconds === null ? null : elapsedSince(serverElapsedSeconds, receivedAtMs, nowMs)
}
