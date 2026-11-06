import { daysUntil, formatBytes, formatDuration, formatPercent, formatRelative, hostPort, levelOf, sshPortsLabel } from './format'

describe('formatBytes', () => {
  it('usa unidades binarias con coma decimal', () => {
    expect(formatBytes(0)).toBe('0 B')
    expect(formatBytes(512)).toBe('512 B')
    expect(formatBytes(1536)).toBe('1,5 KB')
    expect(formatBytes(3.7 * 1024 ** 3)).toBe('3,7 GB')
    expect(formatBytes(261 * 1024 ** 2)).toBe('261 MB')
  })
  it('devuelve raya para valores inválidos', () => {
    expect(formatBytes(null)).toBe('—')
    expect(formatBytes(undefined)).toBe('—')
    expect(formatBytes(-1)).toBe('—')
    expect(formatBytes(Number.NaN)).toBe('—')
  })
})

describe('formatDuration', () => {
  it('compacta días, horas y minutos', () => {
    expect(formatDuration(45)).toBe('45 s')
    expect(formatDuration(300)).toBe('5 min')
    expect(formatDuration(3 * 3600 + 12 * 60)).toBe('3 h 12 min')
    expect(formatDuration(82 * 86_400 + 4 * 3600)).toBe('82 d 4 h')
    expect(formatDuration(2 * 86_400)).toBe('2 d')
    expect(formatDuration(null)).toBe('—')
  })
})

describe('formatPercent', () => {
  it('redondea a un decimal', () => {
    expect(formatPercent(65)).toBe('65 %')
    expect(formatPercent(66.78)).toBe('66,8 %')
    expect(formatPercent(undefined)).toBe('—')
  })
})

describe('formatRelative', () => {
  const now = new Date('2026-09-30T13:20:00Z')
  it('describe tiempos pasados y futuros', () => {
    expect(formatRelative('2026-09-30T13:19:58Z', now)).toBe('recién')
    expect(formatRelative('2026-09-30T13:17:00Z', now)).toBe('hace 3 min')
    expect(formatRelative('2026-09-30T11:20:00Z', now)).toBe('hace 2 h')
    expect(formatRelative('2026-10-03T13:20:00Z', now)).toBe('en 3 d')
    expect(formatRelative(null, now)).toBe('nunca')
  })
})

describe('otros', () => {
  it('arma IP:puerto', () => {
    expect(hostPort('51.222.205.245', 1080)).toBe('51.222.205.245:1080')
    expect(hostPort('2001:db8::1', 22)).toBe('[2001:db8::1]:22')
    expect(hostPort('10.0.0.1', null)).toBe('10.0.0.1')
  })
  it('clasifica niveles', () => {
    expect(levelOf(50)).toBe('ok')
    expect(levelOf(85)).toBe('warn')
    expect(levelOf(95)).toBe('crit')
  })
  it('calcula días restantes', () => {
    expect(daysUntil('2026-10-14T00:00:00Z', new Date('2026-09-30T00:00:00Z'))).toBe(14)
    expect(daysUntil(null)).toBeNull()
  })
})

describe('sshPortsLabel', () => {
  it('muestra el preferido y los demás puertos de sshd', () => {
    expect(sshPortsLabel({ ssh_port: 1080, ssh_ports: [22, 1080] })).toBe('1080 (también escucha en 22)')
    expect(sshPortsLabel({ ssh_port: 1080, ssh_ports: [1080] })).toBe('1080')
    expect(sshPortsLabel({ ssh_port: 22 })).toBe('22')
    expect(sshPortsLabel({ ssh_port: 0 })).toBe('no detectado')
  })
})
