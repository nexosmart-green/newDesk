import type { ComponentKind } from '../api/types'

export const kindLabels: Record<ComponentKind, string> = {
  http: 'Sitio web (HTTP)',
  health: 'API (/health)',
  pm2: 'App de PM2',
  supervisor: 'Programa de Supervisor',
  timer: 'Backup (timer de systemd)',
}
