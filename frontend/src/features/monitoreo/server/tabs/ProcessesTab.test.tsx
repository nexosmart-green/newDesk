import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { pm2, supervisor } from '../../test/fixtures/reaquila'
import { renderWithProviders } from '../../test/render'
import { Pm2Panel } from './ProcessesTab'
import { SupervisorPanel } from './SupervisorPanel'

describe('Pm2Panel', () => {
  it('copia el comando pm2 reload exacto del proceso', async () => {
    const user = userEvent.setup()
    const writeText = vi.fn().mockResolvedValue(undefined)
    Object.defineProperty(window, 'isSecureContext', { value: true, configurable: true })
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })

    renderWithProviders(<Pm2Panel data={pm2} />)
    const buttons = within(screen.getByRole('table')).getAllByRole('button', { name: 'pm2 reload farmaencuentro' })
    expect(buttons).toHaveLength(2) // cluster ×2: mismo nombre, dos pm_id
    await user.click(buttons[0] as HTMLElement)

    expect(writeText).toHaveBeenCalledWith('pm2 reload farmaencuentro')
    expect(await screen.findByText('Copiado')).toBeInTheDocument()
  })

  it('no ofrece reload para módulos de PM2', () => {
    renderWithProviders(<Pm2Panel data={pm2} />)
    expect(screen.queryByRole('button', { name: 'pm2 reload pm2-logrotate' })).not.toBeInTheDocument()
    expect(screen.getByText('Sin procesos en este daemon.')).toBeInTheDocument()
  })
})

describe('SupervisorPanel', () => {
  it('ordena primero los programas con problemas', () => {
    const data = {
      ...supervisor,
      programs: [...(supervisor.programs ?? []), { name: 'roto:roto_00', group: 'roto', state: 'FATAL', healthy: false }],
    }
    renderWithProviders(<SupervisorPanel data={data} />)
    expect(screen.getByText('1 con problemas')).toBeInTheDocument()
    const rows = screen.getAllByRole('row')
    expect(rows[1]).toHaveTextContent('FATAL')
  })
})

describe('SupervisorPanel en el server de clientes', () => {
  it('STOPPED con autostart=false se ve como detenido a propósito, sin alarma', async () => {
    const { supervisor: clientesSupervisor } = await import('../../test/fixtures/clientes')
    renderWithProviders(<SupervisorPanel data={clientesSupervisor} />)
    expect(screen.getByText('4 detenidos a propósito')).toBeInTheDocument()
    expect(screen.getByText('todo en orden')).toBeInTheDocument()
    expect(screen.queryByText(/con problemas/)).not.toBeInTheDocument()
    expect(screen.getAllByText('detenido a propósito')).toHaveLength(4)
  })

  it('STOPPED con autostart=true sí es una falla', () => {
    renderWithProviders(
      <SupervisorPanel data={{ available: true, programs: [{ name: 'teka-worker:teka-worker_00', group: 'teka-worker', state: 'STOPPED', healthy: false, autostart: true }] }} />,
    )
    expect(screen.getByText('1 con problemas')).toBeInTheDocument()
    expect(screen.getByText('STOPPED')).toBeInTheDocument()
  })
})

describe('SupervisorPanel: filtro por proyecto', () => {
  it('agrupa por proyecto del inventario, incluso desde una subapp del monorepo', async () => {
    const user = userEvent.setup()
    const { supervisor: clientesSupervisor, projects } = await import('../../test/fixtures/clientes')
    renderWithProviders(<SupervisorPanel data={clientesSupervisor} projects={projects.items} />)

    const select = screen.getByRole('combobox', { name: 'Filtrar por proyecto' })
    await user.selectOptions(select, screen.getByRole('option', { name: 'playinn (2)' }))

    expect(screen.getByText('Supervisor · 2 de 12 programas')).toBeInTheDocument()
    const rows = screen.getAllByRole('row').slice(1)
    expect(rows).toHaveLength(2)
    expect(rows.map((row) => row.textContent)).toEqual([expect.stringContaining('playinn-queue'), expect.stringContaining('playinn-scheduler')])
    expect(screen.getByText('/var/www/clientes.nexosmart.com.ar/playinn/back', { selector: '[title^="directory="]' })).toBeInTheDocument()

    await user.selectOptions(select, screen.getByRole('option', { name: 'Sin proyecto (6)' }))
    expect(screen.getAllByRole('row').slice(1)).toHaveLength(6)
    expect(screen.queryByText(/playinn-queue/)).not.toBeInTheDocument()
  })

  it('no muestra el filtro si el agente no manda proyectos', () => {
    renderWithProviders(
      <SupervisorPanel data={{ available: true, programs: [{ name: 'reverb:reverb_00', group: 'reverb', state: 'RUNNING', healthy: true }] }} />,
    )
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()
  })
})
