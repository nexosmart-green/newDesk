import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { currentUserKey, useCurrentUser } from '@/features/auth/useAuth'
import { createEcho, isRealtimeConfigured } from '@/lib/echo'
import { dispatchEvent, parseEvent } from './reducer'

export type Connection = 'connecting' | 'open' | 'retrying'

const eventTypes = ['server.status', 'metrics', 'snapshot', 'alert', 'report', 'report.message'] as const

// Una sola conexión a Reverb para todo el módulo (canal privado `monitoring.{companyId}`, ADR-0002 punto 4).
// 'open' = suscripto al canal; si se corta, Pusher reintenta solo y se ve 'retrying' hasta volver.
export function useEvents(): Connection {
  const client = useQueryClient()
  const companyId = useCurrentUser().data?.company.id
  const [connection, setConnection] = useState<Connection>(() => (isRealtimeConfigured() ? 'connecting' : 'retrying'))

  useEffect(() => {
    const echo = companyId === undefined ? null : createEcho()
    if (!echo) return
    const channel = echo.private(`monitoring.${companyId}`)
    for (const type of eventTypes) {
      // El punto inicial: el back usa broadcastAs(), sin el namespace App.Events.
      channel.listen(`.${type}`, (payload: unknown) => {
        const event = parseEvent(type, payload)
        if (event) dispatchEvent(client, event)
      })
    }
    channel.subscribed(() => setConnection('open'))
    // 401/403 al autorizar el canal: puede ser la sesión vencida; RequireAuth decide.
    channel.error(() => {
      setConnection('retrying')
      void client.invalidateQueries({ queryKey: currentUserKey })
    })
    const stopWatching = echo.connector.onConnectionChange((status) => {
      if (status === 'connected') return
      setConnection((current) => (status === 'failed' || current === 'open' ? 'retrying' : current))
    })
    return () => {
      stopWatching()
      echo.disconnect()
    }
  }, [client, companyId])

  return connection
}
