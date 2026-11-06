import { screen } from '@testing-library/react'
import { ApiError } from '@/lib/api'
import { buildProject, page } from '@/test/fixtures'
import { renderRoutes } from '@/test/renderWithProviders'
import { describeActivity } from './activityMessages'
import { ProjectActivityTab } from './ProjectActivityTab'
import { ProjectPage } from './ProjectPage'
import * as projectsApi from './projectsApi'
import { ProjectSummaryTab } from './ProjectSummaryTab'
import type { ActivityEntry } from './types'

function renderProject(path: string) {
  return renderRoutes(
    [
      {
        path: '/proyectos/:projectId',
        element: <ProjectPage />,
        children: [
          { index: true, element: <ProjectSummaryTab /> },
          { path: 'actividad', element: <ProjectActivityTab /> },
        ],
      },
    ],
    path,
  )
}

const statusChanged: ActivityEntry = {
  id: 1,
  event: 'project.status_changed',
  actor: { id: 1, full_name: 'Ana García' },
  source: 'mcp',
  token_name: null,
  before: { status: 'active' },
  after: { status: 'finished' },
  created_at: '2026-11-06T13:00:00+00:00',
}

describe('ProjectPage', () => {
  it('says "Proyecto no encontrado" when the backend hides the project with a 404 (CA-03.03)', async () => {
    vi.spyOn(projectsApi, 'fetchProject').mockRejectedValue(new ApiError(404, 'No encontrado.'))
    renderProject('/proyectos/9')

    expect(await screen.findByRole('heading', { name: 'Proyecto no encontrado' })).toBeInTheDocument()
    expect(screen.queryByRole('navigation', { name: 'Secciones del proyecto' })).not.toBeInTheDocument()
  })

  it('shows the five tabs and the activity feed as readable sentences (RF-03.06)', async () => {
    vi.spyOn(projectsApi, 'fetchProject').mockResolvedValue(buildProject())
    vi.spyOn(projectsApi, 'fetchProjectActivity').mockResolvedValue(page([statusChanged]))
    renderProject('/proyectos/9/actividad')

    expect(await screen.findByText('Ana García cambió el estado de «Activo» a «Finalizado»')).toBeInTheDocument()
    expect(screen.getByText(/vía MCP/)).toBeInTheDocument()
    for (const tab of ['Resumen', 'Tareas', 'Horas', 'Miembros', 'Actividad']) {
      expect(screen.getByRole('link', { name: tab })).toBeInTheDocument()
    }
  })
})

describe('describeActivity', () => {
  it('names the changed fields of an update and falls back for unknown events', () => {
    const updated: ActivityEntry = {
      ...statusChanged,
      event: 'project.updated',
      before: { name: 'Viejo', code_prefix: 'bhn', ends_on: null },
      after: { name: 'Nuevo', code_prefix: 'bhn', ends_on: '2026-12-31' },
    }
    expect(describeActivity(updated)).toBe('Ana García cambió el nombre y la fecha de fin')
    expect(describeActivity({ ...statusChanged, actor: null, event: 'task.created' })).toBe('El sistema registró «task.created»')
  })
})
