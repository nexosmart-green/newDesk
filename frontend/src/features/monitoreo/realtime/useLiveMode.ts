import { useEffect } from 'react'
import { monitoringApi } from '../api/monitoringApi'

const beatMs = 20_000

// Modo en vivo (ADR-0002 punto 4): mientras se ve el detalle de un server, un latido cada 20 s le pide al
// back métricas a 5 s. El back lo apaga solo cuando el latido deja de llegar (RF-13, diferencia 8).
export function useLiveMode(serverId: number): void {
  useEffect(() => {
    if (!Number.isFinite(serverId)) return
    const beat = () => {
      if (!document.hidden) monitoringApi.post<null>(`servers/${serverId}/live`).catch(() => {})
    }
    beat()
    const timer = window.setInterval(beat, beatMs)
    document.addEventListener('visibilitychange', beat)
    return () => {
      window.clearInterval(timer)
      document.removeEventListener('visibilitychange', beat)
    }
  }, [serverId])
}
