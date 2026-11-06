import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { ServerTargets } from '../api/types'
import { renderWithProviders } from '../test/render'
import { ComponentForm } from './ComponentForm'
import { logoProblem } from './logo'

const targets: ServerTargets[] = [
  {
    server_id: 5, server_name: 'clientes', pm2: [{ name: 'api-b', owner: 'clientb' }, { name: 'api-b', owner: 'eve' }, { name: 'TOL', owner: 'deploy' }],
    supervisor: ['playinn-queue', 'playinn-scheduler'], timers: ['backup-playinn.timer'],
  },
]

describe('ComponentForm', () => {
  it('pide URL para sitio y API, y servidor + objetivo para lo que mira el agente', async () => {
    const user = userEvent.setup()
    const onSubmit = vi.fn()
    renderWithProviders(<ComponentForm projectParts={['app', 'cms']} targets={targets} pending={false} error={null} onSubmit={onSubmit} onCancel={() => {}} />)

    expect(screen.getByLabelText('URL')).toBeInTheDocument()
    expect(screen.queryByLabelText('Servidor')).not.toBeInTheDocument()

    await user.selectOptions(screen.getByLabelText('Tipo'), 'supervisor')
    expect(screen.queryByLabelText('URL')).not.toBeInTheDocument()
    const program = screen.getByLabelText('Programa o grupo')
    expect(program).toBeDisabled()

    await user.selectOptions(screen.getByLabelText('Servidor'), 'clientes')
    expect(program).toBeEnabled()
    await user.selectOptions(program, 'playinn-queue')
    await user.type(screen.getByLabelText('Nombre interno'), 'Workers')
    await user.click(screen.getByRole('button', { name: 'Agregar componente' }))

    // Un proceso viene tildado en las partes que ya tiene el proyecto.
    expect(onSubmit).toHaveBeenCalledWith({ kind: 'supervisor', label: 'Workers', parts: ['app', 'cms'], position: 0, server_id: 5, target: 'playinn-queue' })
  })

  it('tilda la parte que nombra la URL hasta que el equipo elige otra cosa (D-045)', async () => {
    const user = userEvent.setup()
    const onSubmit = vi.fn()
    renderWithProviders(<ComponentForm targets={targets} pending={false} error={null} onSubmit={onSubmit} onCancel={() => {}} />)
    const parts = screen.getByRole('group', { name: 'Afecta a' })
    const checked = () => within(parts).getAllByRole('checkbox').filter((box) => (box as HTMLInputElement).checked).map((box) => box.parentElement?.textContent)

    expect(checked()).toEqual(['Web'])
    await user.type(screen.getByLabelText('URL'), 'https://cms.playinn.com')
    expect(checked()).toEqual(['CMS'])
    await user.click(within(parts).getByRole('checkbox', { name: 'App' }))
    await user.clear(screen.getByLabelText('URL'))
    await user.type(screen.getByLabelText('URL'), 'https://admin.playinn.com')
    expect(checked()).toEqual(['App', 'CMS']) // ya tocado: la URL no lo cambia
    await user.type(screen.getByLabelText('Nombre interno'), 'CMS')
    await user.click(screen.getByRole('button', { name: 'Agregar componente' }))
    expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({ kind: 'http', parts: ['app', 'cms'], url: 'https://admin.playinn.com' }))

    // Los backups no son una parte: tienen su propia tarjeta.
    await user.selectOptions(screen.getByLabelText('Tipo'), 'timer')
    expect(within(parts).queryAllByRole('checkbox')).toHaveLength(0)
    expect(parts).toHaveTextContent('A ninguna parte')
  })

  it('al editar parte de las partes guardadas', () => {
    const initial = {
      id: 2, project_id: 1, kind: 'health' as const, label: 'API', parts: ['app' as const], position: 3, url: 'https://api.playinn.com/health', server_name: null,
      status: 'operational' as const, status_since: null, detail: null, last_checked_at: null, last_ok_at: null, latency_ms: null, status_code: null, ssl_expires_at: null,
    }
    renderWithProviders(<ComponentForm initial={initial} projectParts={['web', 'app', 'cms']} targets={targets} pending={false} error={null} onSubmit={() => {}} onCancel={() => {}} />)
    const parts = screen.getByRole('group', { name: 'Afecta a' })
    expect(within(parts).getByRole('checkbox', { name: 'App' })).toBeChecked()
    expect(within(parts).getByRole('checkbox', { name: 'Web' })).not.toBeChecked()
    expect(within(parts).getByRole('checkbox', { name: 'CMS' })).not.toBeChecked()
  })

  it('una app de PM2 se elige con su usuario y se guarda con los dos (NV-10)', async () => {
    const user = userEvent.setup()
    const onSubmit = vi.fn()
    renderWithProviders(<ComponentForm targets={targets} pending={false} error={null} onSubmit={onSubmit} onCancel={() => {}} />)
    await user.selectOptions(screen.getByLabelText('Tipo'), 'pm2')
    await user.selectOptions(screen.getByLabelText('Servidor'), 'clientes')
    const app = screen.getByLabelText('App de PM2')
    expect(within(app).getAllByRole('option').map((option) => option.textContent)).toEqual(['Elegí uno', 'api-b (clientb)', 'api-b (eve)', 'TOL (deploy)'])
    await user.selectOptions(app, 'api-b (eve)')
    await user.type(screen.getByLabelText('Nombre interno'), 'API')
    await user.click(screen.getByRole('button', { name: 'Agregar componente' }))
    expect(onSubmit).toHaveBeenCalledWith(expect.objectContaining({ kind: 'pm2', server_id: 5, target: 'api-b', target_owner: 'eve' }))
  })

  it('una app guardada sin usuario se sigue ofreciendo hasta que el equipo elija uno', async () => {
    const user = userEvent.setup()
    const onSubmit = vi.fn()
    const initial = {
      id: 3, project_id: 1, kind: 'pm2' as const, label: 'API', parts: ['web' as const], position: 0, server_id: 5, server_name: 'clientes',
      target: 'api-b', target_owner: null, status: 'unknown' as const, status_since: null, detail: null, last_checked_at: null, last_ok_at: null,
      latency_ms: null, status_code: null, ssl_expires_at: null,
    }
    renderWithProviders(<ComponentForm initial={initial} targets={targets} pending={false} error={null} onSubmit={onSubmit} onCancel={() => {}} />)
    const app = screen.getByLabelText('App de PM2')
    expect(app).toHaveDisplayValue('api-b')
    await user.click(screen.getByRole('button', { name: 'Guardar componente' }))
    expect(onSubmit).toHaveBeenLastCalledWith(expect.objectContaining({ target: 'api-b', target_owner: null }))
    await user.selectOptions(app, 'api-b (clientb)')
    await user.click(screen.getByRole('button', { name: 'Guardar componente' }))
    expect(onSubmit).toHaveBeenLastCalledWith(expect.objectContaining({ target: 'api-b', target_owner: 'clientb' }))
  })

  it('el backup pide además las horas para darlo por atrasado', async () => {
    const user = userEvent.setup()
    renderWithProviders(<ComponentForm targets={targets} pending={false} error={null} onSubmit={() => {}} onCancel={() => {}} />)
    await user.selectOptions(screen.getByLabelText('Tipo'), 'timer')
    expect(screen.getByLabelText('Atrasado después de (horas)')).toHaveValue('26')
    expect(screen.getByLabelText('Timer')).toBeInTheDocument()
  })
})

describe('logo', () => {
  it('acepta PNG, JPG y WebP de hasta 256 KB', () => {
    expect(logoProblem({ type: 'image/png', size: 40_000 })).toBeNull()
    expect(logoProblem({ type: 'image/webp', size: 256 * 1024 })).toBeNull()
    expect(logoProblem({ type: 'image/svg+xml', size: 2_000 })).toMatch(/PNG, JPG o WebP/)
    expect(logoProblem({ type: 'image/jpeg', size: 256 * 1024 + 1 })).toMatch(/256 KB/)
  })
})
