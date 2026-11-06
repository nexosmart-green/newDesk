import type { ServerStatus } from '../api/types'

export type Tone = 'ok' | 'warn' | 'crit' | 'info' | 'idle'

export const toneColor = (tone: Tone): string => `var(--${tone})`

export const serverStatusMeta: Record<ServerStatus, { label: string; tone: Tone }> = {
  online: { label: 'En línea', tone: 'ok' },
  agent_down: { label: 'Agente caído', tone: 'warn' },
  down: { label: 'Caído', tone: 'crit' },
  unknown: { label: 'Sin datos', tone: 'idle' },
  disabled: { label: 'Deshabilitado', tone: 'idle' },
}

// Estados de PM2, Supervisor y systemd a un tono.
export function processTone(state: string): Tone {
  const normalized = state.toLowerCase()
  if (['online', 'running', 'active', 'success'].includes(normalized)) return 'ok'
  if (['stopping', 'starting', 'launching', 'backoff', 'activating', 'reloading'].includes(normalized)) return 'warn'
  if (['errored', 'fatal', 'exited', 'failed', 'unknown', 'dead'].includes(normalized)) return 'crit'
  return 'idle'
}

// Supervisor: STOPPED con autostart=false es un programa estacionado a propósito (neutro, no error).
export function isParked(program: { state: string; autostart?: boolean }): boolean {
  return program.state === 'STOPPED' && program.autostart === false
}
