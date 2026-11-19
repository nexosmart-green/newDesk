import { useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { useCurrentUser } from '@/features/auth/useAuth'
import { refreshTimeData } from '@/features/timer/useTimer'
import { createEcho } from '@/lib/echo'
import { notificationsKey } from '@/lib/queryKeys'
import { setRealtimeLive } from './realtimeStatus'

/**
 * RNF-12. Mount once (app shell): listens to the person's private channel `user.{id}` and refreshes what each signal
 * names, so a timer started from the phone, the MCP or the assistant shows up here at once. Only the kind travels:
 * the data comes back through the API with the person's permissions. Without Reverb this does nothing and the
 * usual polling stays; after a drop it refreshes everything once, since signals may have been missed meanwhile.
 */
export function useUserSignals() {
  const queryClient = useQueryClient()
  const userId = useCurrentUser().data?.id

  useEffect(() => {
    const echo = userId === undefined ? null : createEcho()
    if (!echo) return
    const refreshNotifications = () => void queryClient.invalidateQueries({ queryKey: notificationsKey })
    let subscribedOnce = false
    let missedSome = false

    const channel = echo.private(`user.${userId}`)
    channel.listen('.timer', () => void refreshTimeData(queryClient))
    channel.listen('.notifications', refreshNotifications)
    channel.subscribed(() => {
      subscribedOnce = true
      setRealtimeLive(true)
      if (!missedSome) return
      missedSome = false
      void refreshTimeData(queryClient)
      refreshNotifications()
    })
    channel.error(() => setRealtimeLive(false))
    const stopWatching = echo.connector.onConnectionChange((status) => {
      if (status === 'connected') return
      missedSome = subscribedOnce
      setRealtimeLive(false)
    })

    return () => {
      stopWatching()
      echo.disconnect()
      setRealtimeLive(false)
    }
  }, [queryClient, userId])
}
