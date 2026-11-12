import { useEffect, useState } from 'react'

const MINUTE_MS = 60_000

/** The current time, refreshed once a minute: "trabajando ahora" and "hoy" move on by themselves. */
export function useNow(): Date {
  const [now, setNow] = useState(() => new Date())
  useEffect(() => {
    const interval = setInterval(() => setNow(new Date()), MINUTE_MS)
    return () => clearInterval(interval)
  }, [])
  return now
}
