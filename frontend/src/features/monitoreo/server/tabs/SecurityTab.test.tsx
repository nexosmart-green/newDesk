import { screen, within } from '@testing-library/react'
import type { Security } from '../../api/contract'
import { failedTotal } from '../../api/contract'
import { security } from '../../test/fixtures/clientes'
import { renderWithProviders } from '../../test/render'
import { SecurityTab } from './SecurityTab'

describe('SecurityTab', () => {
  it('muestra la fila de máximo de intentos y la suma al total', () => {
    renderWithProviders(<SecurityTab data={security} />)
    const row = screen.getByRole('row', { name: /Máximo de intentos/ })
    expect(row).toHaveTextContent('12')
    expect(failedTotal(security.failed_logins.window_7d)).toBe(3167 + 4623 + 12)
  })

  // NV-12: phpMyAdmin y auth.log los puede escribir cualquier usuario del server.
  it('marca los eventos que no se pueden verificar', () => {
    const data: Security = {
      ...security,
      failed_logins: {
        ...security.failed_logins,
        recent: [
          { time: '2026-09-30T14:41:07Z', kind: 'invalid_user', service: 'ssh', user: 'admin', ip: '203.0.113.149' },
          { time: '2026-09-30T14:42:07Z', kind: 'phpmyadmin_denied', service: 'phpmyadmin', user: 'demo', ip: '198.51.100.9', unverified: true },
        ],
      },
    }
    renderWithProviders(<SecurityTab data={data} />)
    expect(within(screen.getByRole('row', { name: /phpmyadmin/ })).getByText('no verificado')).toBeInTheDocument()
    expect(within(screen.getByRole('row', { name: /203\.0\.113\.149/ })).queryByText('no verificado')).not.toBeInTheDocument()
    expect(screen.queryByText(/no se pueden verificar/)).not.toBeInTheDocument()
  })

  it('avisa que los números de auth.log no se pueden verificar', () => {
    const data: Security = { ...security, failed_logins: { ...security.failed_logins, source: '/var/log/auth.log' } }
    renderWithProviders(<SecurityTab data={data} />)
    expect(screen.getByText(/no se pueden verificar/)).toBeInTheDocument()
  })
})
