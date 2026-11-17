// Formateadores de números, tamaños y tiempos. Locale es-AR.

const numberFormat = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 1 })
const integerFormat = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 })

export function formatNumber(value: number): string {
  return integerFormat.format(value)
}

const byteUnits = ['B', 'KB', 'MB', 'GB', 'TB', 'PB']

// Binario (1 KB = 1024 B), como lo muestran free/df -h.
export function formatBytes(bytes: number | null | undefined): string {
  if (bytes === null || bytes === undefined || !Number.isFinite(bytes) || bytes < 0) return '—'
  if (bytes < 1024) return `${Math.round(bytes)} B`
  let value = bytes
  let unit = 0
  while (value >= 1024 && unit < byteUnits.length - 1) {
    value /= 1024
    unit++
  }
  const digits = value >= 100 ? 0 : 1
  return `${value.toFixed(digits).replace('.', ',')} ${byteUnits[unit]}`
}

export function formatRate(bytesPerSecond: number | null | undefined): string {
  if (bytesPerSecond === null || bytesPerSecond === undefined) return '—'
  return `${formatBytes(bytesPerSecond)}/s`
}

export function formatPercent(value: number | null | undefined): string {
  if (value === null || value === undefined || !Number.isFinite(value)) return '—'
  return `${numberFormat.format(value)} %`
}

// Uptime compacto: "82 d 4 h", "3 h 12 min", "45 s".
export function formatDuration(totalSeconds: number | null | undefined): string {
  if (totalSeconds === null || totalSeconds === undefined || !Number.isFinite(totalSeconds) || totalSeconds < 0) return '—'
  const seconds = Math.floor(totalSeconds)
  const days = Math.floor(seconds / 86_400)
  const hours = Math.floor((seconds % 86_400) / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  if (days > 0) return hours > 0 ? `${days} d ${hours} h` : `${days} d`
  if (hours > 0) return minutes > 0 ? `${hours} h ${minutes} min` : `${hours} h`
  if (minutes > 0) return `${minutes} min`
  return `${seconds} s`
}

// "hace 5 s", "hace 3 min", "hace 2 h", "hace 4 d". Futuro: "en 3 d".
export function formatRelative(iso: string | null | undefined, now: Date = new Date()): string {
  if (!iso) return 'nunca'
  const time = new Date(iso).getTime()
  if (Number.isNaN(time)) return '—'
  const diff = Math.round((now.getTime() - time) / 1000)
  const future = diff < 0
  const text = formatDuration(Math.abs(diff)).split(' ').slice(0, 2).join(' ')
  if (Math.abs(diff) < 5) return 'recién'
  return future ? `en ${text}` : `hace ${text}`
}

const dateTimeFormat = new Intl.DateTimeFormat('es-AR', {
  day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false,
})

// "hoy", "ayer", "hace 3 días": para fechas que al cliente le importan en días (la última actualización).
export function sinceDays(iso: string, now: Date = new Date()): string {
  const days = Math.floor((now.getTime() - new Date(iso).getTime()) / 86_400_000)
  if (days <= 0) return 'hoy'
  if (days === 1) return 'ayer'
  return `hace ${days} días`
}

export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return '—'
  const date = new Date(iso)
  return Number.isNaN(date.getTime()) ? '—' : dateTimeFormat.format(date)
}

export function daysUntil(iso: string | null | undefined, now: Date = new Date()): number | null {
  if (!iso) return null
  const time = new Date(iso).getTime()
  if (Number.isNaN(time)) return null
  return Math.floor((time - now.getTime()) / 86_400_000)
}

// Identificador estilo Xshell: "51.222.205.245:1080". IPv6 entre corchetes.
export function hostPort(ip: string, port: number | null | undefined): string {
  const host = ip.includes(':') ? `[${ip}]` : ip
  return port ? `${host}:${port}` : host
}

// Nivel de un porcentaje para colorear medidores.
export type Level = 'ok' | 'warn' | 'crit'
export function levelOf(percent: number, warn = 80, crit = 90): Level {
  if (percent >= crit) return 'crit'
  if (percent >= warn) return 'warn'
  return 'ok'
}

// ssh_port identifica al server (IP:puerto); si sshd escucha en varios, se listan todos.
export function sshPortsLabel(system: { ssh_port: number; ssh_ports?: number[] }): string {
  if (!system.ssh_port) return 'no detectado'
  const others = (system.ssh_ports ?? []).filter((port) => port !== system.ssh_port)
  return others.length ? `${system.ssh_port} (también escucha en ${others.join(', ')})` : String(system.ssh_port)
}
