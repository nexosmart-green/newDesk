import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { GITHUB_MOCK_TOKEN, stubGitHub } from '../test/github'
import { mockMonitoringApi } from '../test/mockApi'
import { renderWithProviders } from '../test/render'
import { DailyReportButton } from './DailyReport'

const REASON = 'Claude de root del server 5 no está conectado al panel (falta monitor-claude.socket).'

// El back de "Generar reporte": con Claude conectado arma los títulos a partir de los mensajes que le llegan.
function mockPanel(available = true) {
  return mockMonitoringApi({
    'GET daily-report/status': { available, reason: available ? '' : REASON },
    'POST daily-report': ({ body }: { body: unknown }) => {
      const { repos } = body as { repos: { repo: string; commits: string[] }[] }
      return {
        markdown: 'Tennis Open League:\n- Se agregó el ranking semanal de jugadores.\n- Se corrigió el cálculo de puntos de los partidos.',
        projects: repos.map((repo) => ({ name: repo.repo.split('/')[1] ?? repo.repo, commits: repo.commits.length })),
        empty: false,
      }
    },
  })
}

async function openDialog(user: ReturnType<typeof userEvent.setup>) {
  const button = screen.getByRole('button', { name: 'Generar reporte' })
  await waitFor(() => expect(button).toBeEnabled())
  await user.click(button)
  return screen.getByRole('dialog', { name: 'Reporte de hoy' })
}

afterEach(() => {
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})

describe('generar reporte del día', () => {
  it('sin el Claude de root conectado al panel, el botón queda deshabilitado y dice por qué', async () => {
    mockPanel(false)
    renderWithProviders(<DailyReportButton />)
    const button = screen.getByRole('button', { name: 'Generar reporte' })
    await waitFor(() => expect(button.closest('[title]')).toHaveAttribute('title', REASON))
    expect(button).toBeDisabled()
    expect(button).toHaveAccessibleDescription(REASON)
  })

  it('pide el token, lee GitHub desde el navegador y al panel solo le llegan los mensajes', async () => {
    const user = userEvent.setup()
    const writeText = vi.fn().mockResolvedValue(undefined)
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
    Object.defineProperty(window, 'isSecureContext', { value: true, configurable: true })
    stubGitHub()
    const panel = mockPanel()
    renderWithProviders(<DailyReportButton />)

    const dialog = await openDialog(user)
    expect(dialog).toHaveTextContent('No se guarda y nunca llega al panel ni a Claude')
    const field = within(dialog).getByLabelText('Tu token de GitHub')
    expect(field).toHaveAttribute('type', 'password')
    await user.type(field, GITHUB_MOCK_TOKEN)
    await user.click(within(dialog).getByRole('button', { name: 'Generar' }))

    const report = await within(dialog).findByLabelText('Reporte')
    expect(report).toHaveTextContent('- Se agregó el ranking semanal de jugadores.')
    expect(dialog).toHaveTextContent('Basado en tennisopenleague_backend (2 commits).')
    expect(dialog).toHaveTextContent('maxirodr/tfp-api (GitHub respondió 404)')

    // El token no salió nunca hacia el panel: ni en el cuerpo, ni en la URL, ni en la config del pedido.
    const sent = JSON.stringify([panel.get.mock.calls, panel.post.mock.calls, panel.put.mock.calls, panel.delete.mock.calls])
    expect(panel.post).toHaveBeenCalledWith('/monitoring/daily-report', {
      repos: [{ repo: 'maxirodr/tennisopenleague_backend', commits: ['Agrega el ranking semanal', 'Arregla el cálculo de puntos\n\nEn los partidos con tie-break'] }],
    })
    expect(sent).not.toContain(GITHUB_MOCK_TOKEN)
    // Ni quedó guardado en el navegador.
    for (const storage of [localStorage, sessionStorage]) {
      for (let index = 0; index < storage.length; index++) expect(storage.getItem(storage.key(index) ?? '')).not.toContain(GITHUB_MOCK_TOKEN)
    }

    await user.click(within(dialog).getByRole('button', { name: 'Copiar' }))
    expect(writeText).toHaveBeenCalledWith(expect.stringContaining('Tennis Open League:\n- Se agregó'))

    // "Generar de nuevo" vuelve a pedir el token: no quedó guardado.
    await user.click(within(dialog).getByRole('button', { name: 'Generar de nuevo' }))
    expect(within(dialog).getByLabelText('Tu token de GitHub')).toHaveValue('')
  })

  it('un token inválido se avisa y se puede volver a intentar', async () => {
    const user = userEvent.setup()
    stubGitHub()
    mockPanel()
    renderWithProviders(<DailyReportButton />)
    const dialog = await openDialog(user)
    await user.type(within(dialog).getByLabelText('Tu token de GitHub'), 'ghp_vencido')
    await user.click(within(dialog).getByRole('button', { name: 'Generar' }))
    expect(await within(dialog).findByRole('alert')).toHaveTextContent('GitHub rechazó el token')
    await user.click(within(dialog).getByRole('button', { name: 'Reintentar' }))
    expect(within(dialog).getByLabelText('Tu token de GitHub')).toHaveValue('')
  })
})
