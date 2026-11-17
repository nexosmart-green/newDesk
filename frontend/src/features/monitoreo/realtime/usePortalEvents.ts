import { useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { currentUserKey, useCurrentUser } from '@/features/auth/useAuth'
import { createEcho } from '@/lib/echo'
import { keys } from '../api/useMonitoring'
import { parseEvent } from './reducer'

const eventTypes = ['report', 'report.message'] as const

// El portal escucha solo los reportes de su cliente (un mensaje nuevo, un cambio de estado): canal privado
// `portal.client.{clientId}`, con los mismos eventos que el SSE del portal de monitoreo (solo ids).
export function usePortalEvents(): void {
  const client = useQueryClient()
  const clientId = useCurrentUser().data?.client?.id

  useEffect(() => {
    const echo = clientId === undefined ? null : createEcho()
    if (!echo) return
    const channel = echo.private(`portal.client.${clientId}`)
    for (const type of eventTypes) {
      // El punto inicial: el back usa broadcastAs(), sin el namespace App.Events.
      channel.listen(`.${type}`, (payload: unknown) => {
        const event = parseEvent(type, payload)
        if ((event?.type === 'report' || event?.type === 'report.message') && event.payload.project_slug) {
          void client.invalidateQueries({ queryKey: keys.portalReports(event.payload.project_slug) })
        }
      })
    }
    // 401/403 al autorizar el canal: puede ser la sesión vencida; RequireClient decide.
    channel.error(() => void client.invalidateQueries({ queryKey: currentUserKey }))
    return () => echo.disconnect()
  }, [client, clientId])
}
