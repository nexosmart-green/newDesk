import { fireEvent, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import { HoursReportPage } from './HoursReportPage'
import { ProjectHoursReportPage } from './ProjectHoursReportPage'
import * as reportsApi from './reportsApi'
import type { HoursReport, ProjectHoursReport } from './types'

const report: HoursReport = {
  period: { from: '2026-11-01', to: '2026-11-06' },
  projects: [
    {
      project: { id: 9, name: 'Bahía Web', prefix: 'bhn' },
      client: 'Bahía',
      type: 'bag',
      allocated_seconds: 36000,
      consumed_total_seconds: 32400,
      consumed_in_period_seconds: 7200,
      percent_consumed: 90,
    },
    {
      project: { id: 10, name: 'Interno', prefix: 'int' },
      client: null,
      type: 'internal',
      allocated_seconds: null,
      consumed_total_seconds: 5400,
      consumed_in_period_seconds: 1800,
      percent_consumed: null,
    },
  ],
}

const projectReport: ProjectHoursReport = {
  ...report.projects[0]!,
  period: report.period,
  by_person: [{ user_id: 1, name: 'Ana García', seconds: 5400 }],
  by_task: [{ code: 'bhn-60', title: 'Arreglar el login', seconds: 7200 }],
}

function renderReport() {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ permissions: ['projects.manage'] }))
  const fetch = vi.spyOn(reportsApi, 'fetchHoursReport').mockResolvedValue(report)
  const fetchProject = vi.spyOn(reportsApi, 'fetchProjectHoursReport').mockResolvedValue(projectReport)
  renderRoutes(
    [
      { path: '/reportes/horas', element: <HoursReportPage /> },
      { path: '/reportes/horas/proyectos/:projectId', element: <ProjectHoursReportPage /> },
    ],
    '/reportes/horas',
  )
  return { fetch, fetchProject }
}

describe('HoursReportPage', () => {
  it('shows sold / consumed / period / % per project for the default period (RF-10.01)', async () => {
    const { fetch } = renderReport()

    const row = (await screen.findByRole('rowheader', { name: 'Bahía Web' })).closest('tr')!
    expect(fetch).toHaveBeenCalledWith({})
    expect(within(row).getByText('Bahía')).toBeInTheDocument()
    expect(within(row).getByText('10h 00m')).toBeInTheDocument()
    expect(within(row).getByText('9h 00m')).toBeInTheDocument()
    expect(within(row).getByText('2h 00m')).toBeInTheDocument()
    expect(within(row).getByText('90% consumido')).toBeInTheDocument()
    expect(within(row).getByRole('meter')).toHaveAttribute('data-level', 'warning')
    const internal = screen.getByRole('rowheader', { name: 'Interno' }).closest('tr')!
    expect(within(internal).getByText('Sin tope')).toBeInTheDocument()
    expect(screen.getByLabelText('Desde')).toHaveValue('2026-11-01')
  })

  it('refetches for the chosen period', async () => {
    const { fetch } = renderReport()
    await screen.findByRole('rowheader', { name: 'Bahía Web' })

    fireEvent.change(screen.getByLabelText('Desde'), { target: { value: '2026-10-01' } })

    await vi.waitFor(() => expect(fetch).toHaveBeenCalledWith({ from: '2026-10-01' }))
  })

  it('drills into a project with hours by person and by task', async () => {
    const { fetchProject } = renderReport()

    await userEvent.click(await screen.findByRole('link', { name: 'Bahía Web' }))

    expect(await screen.findByRole('heading', { name: 'Horas de Bahía Web' })).toBeInTheDocument()
    expect(fetchProject).toHaveBeenCalledWith(9, {})
    const byPerson = screen.getByRole('table', { name: 'Por persona' })
    expect(within(byPerson).getByText('Ana García')).toBeInTheDocument()
    expect(within(byPerson).getByText('1h 30m')).toBeInTheDocument()
    const byTask = screen.getByRole('table', { name: 'Por tarea' })
    expect(within(byTask).getByRole('link', { name: 'bhn-60' })).toHaveAttribute('href', '/tareas/bhn-60')
  })
})
