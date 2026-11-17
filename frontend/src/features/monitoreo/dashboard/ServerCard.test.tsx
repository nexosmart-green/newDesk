import { screen, within } from '@testing-library/react'
import type { ServerStatus, ServerSummary } from '../api/types'
import { renderWithProviders } from '../test/render'
import { ServerCard } from './ServerCard'

const now = new Date(Date.now())
const base: ServerSummary = {
  id: 1, name: 'reaquila', ip: '51.222.205.245', ssh_port: 1080, agent_port: 9100, enabled: true, status: 'online',
  last_seen_at: new Date(now.getTime() - 12_000).toISOString(), hostname: 'mail.reaquila.com', os: 'Ubuntu 24.10', active_alerts: 2, agent_version: 'v0.1.0',
  metrics: { cpu_percent: 11.4, memory_percent: 67.6, swap_percent: 67.5, disk_percent: 65, load1: 0.3, uptime_seconds: 82 * 86_400, collected_at: new Date(now.getTime() - 12_000).toISOString() },
}

describe('ServerCard', () => {
  const cases: [ServerStatus, string][] = [
    ['online', 'En línea'],
    ['agent_down', 'Agente caído'],
    ['down', 'Caído'],
    ['unknown', 'Sin datos'],
    ['disabled', 'Deshabilitado'],
  ]

  it.each(cases)('muestra el estado %s con texto, no solo color', (status, label) => {
    renderWithProviders(<ServerCard server={{ ...base, status }} now={now} />)
    const card = screen.getByRole('article', { name: 'Servidor reaquila' })
    expect(within(card).getByText(label)).toBeInTheDocument()
  })

  it('muestra IP:puerto SSH copiable y las métricas', () => {
    renderWithProviders(<ServerCard server={base} now={now} />)
    expect(screen.getByRole('button', { name: /51\.222\.205\.245:1080/ })).toBeInTheDocument()
    expect(screen.getByRole('meter', { name: 'CPU' })).toHaveAttribute('aria-valuenow', '11.4')
    expect(screen.getByText('2 alertas')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'reaquila' })).toHaveAttribute('href', '/monitoreo/servers/1')
  })

  it('sin métricas muestra rayas', () => {
    renderWithProviders(<ServerCard server={{ ...base, status: 'down', metrics: null, active_alerts: 0 }} now={now} />)
    expect(screen.getAllByText('—').length).toBeGreaterThanOrEqual(4)
    expect(screen.queryByText(/alerta/)).not.toBeInTheDocument()
  })

  it('un server deshabilitado no muestra antigüedad del dato', () => {
    renderWithProviders(<ServerCard server={{ ...base, status: 'disabled' }} now={now} />)
    expect(screen.getByText('sin polling')).toBeInTheDocument()
  })
})
