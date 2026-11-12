import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import { ProjectPage } from '@/features/projects/ProjectPage'
import * as projectsApi from '@/features/projects/projectsApi'
import * as tasksApi from '@/features/tasks/tasksApi'
import { buildHourBag, buildProject, buildTimeEntry, page } from '@/test/fixtures'
import { buildUser, renderRoutes } from '@/test/renderWithProviders'
import * as hoursApi from './hoursApi'
import { ProjectHoursTab } from './ProjectHoursTab'
import type { ExtensionRequest } from './types'

const pendingRequest: ExtensionRequest = {
  id: 4,
  status: 'pending',
  message: 'Faltan las pruebas',
  project: { id: 9, name: 'Bahía Web' },
  requested_by: { id: 2, full_name: 'Juan Pérez' },
  created_at: '2026-11-06T12:00:00+00:00',
}

function renderHours(permissions: string[], requests: ExtensionRequest[] = []) {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ permissions }))
  vi.spyOn(projectsApi, 'fetchProject').mockResolvedValue(buildProject())
  vi.spyOn(hoursApi, 'fetchProjectHours').mockResolvedValue({ ...buildHourBag(), allocations: [] })
  vi.spyOn(hoursApi, 'fetchProjectEntries').mockResolvedValue(
    page([buildTimeEntry({ is_running: false, ended_at: '2026-11-06T15:00:00+00:00', comment: 'Revisé el formulario de login' })]),
  )
  vi.spyOn(hoursApi, 'fetchExtensionRequests').mockResolvedValue(requests)
  vi.spyOn(tasksApi, 'fetchProjectTasks').mockResolvedValue(page([]))
  renderRoutes(
    [{ path: '/proyectos/:projectId', element: <ProjectPage />, children: [{ path: 'horas', element: <ProjectHoursTab /> }] }],
    '/proyectos/9/horas',
  )
}

// Typing several fields is slow when the whole suite shares the CPU.
describe('ProjectHoursTab', () => {
  it('loads decimal hours as seconds: "2,5" → 9000 (RF-05.04), with a success toast', async () => {
    const allocate = vi.spyOn(hoursApi, 'allocateHours').mockResolvedValue(buildHourBag())
    renderHours(['hours.allocate'])

    await userEvent.click(await screen.findByRole('button', { name: 'Cargar horas' }))
    expect(screen.queryByLabelText('Minutos')).not.toBeInTheDocument()
    await userEvent.type(screen.getByLabelText('Horas'), '2,5')
    expect(screen.getByText(/Se suman 2h 30m/)).toBeInTheDocument()
    await userEvent.type(screen.getByLabelText('Motivo'), 'Presupuesto #123')
    await userEvent.click(screen.getByRole('button', { name: 'Cargar' }))

    expect(allocate).toHaveBeenCalledWith(9, { seconds: 9000, reason: 'Presupuesto #123', reference: null })
    expect(await screen.findByText('Horas cargadas en la bolsa.')).toBeInTheDocument()
  })

  it('corrects the bag with a negative amount', async () => {
    const allocate = vi.spyOn(hoursApi, 'allocateHours').mockResolvedValue(buildHourBag())
    renderHours(['hours.allocate'])

    await userEvent.click(await screen.findByRole('button', { name: 'Cargar horas' }))
    await userEvent.type(screen.getByLabelText('Horas'), '-1.25')
    await userEvent.type(screen.getByLabelText('Motivo'), 'Carga duplicada')
    await userEvent.click(screen.getByRole('button', { name: 'Cargar' }))

    expect(allocate).toHaveBeenCalledWith(9, { seconds: -4500, reason: 'Carga duplicada', reference: null })
  })

  it('hides "Cargar horas" without hours.allocate', async () => {
    renderHours([])

    expect(await screen.findByText('Revisé el formulario de login')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Cargar horas' })).not.toBeInTheDocument()
  })

  it('approves a pending extension request loading its hours (RF-05.08)', async () => {
    const approve = vi.spyOn(hoursApi, 'approveExtension').mockResolvedValue({ ...pendingRequest, status: 'approved' })
    renderHours(['hours.allocate'], [pendingRequest])

    expect(await screen.findByText('Faltan las pruebas')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Aprobar' }))
    await userEvent.type(screen.getByLabelText('Horas'), '5')
    await userEvent.type(screen.getByLabelText('Motivo'), 'Ampliación aprobada')
    await userEvent.click(screen.getByRole('button', { name: 'Aprobar y cargar horas' }))

    expect(approve).toHaveBeenCalledWith(4, { seconds: 18000, reason: 'Ampliación aprobada', reference: null })
  })

  it('rejects a pending extension request', async () => {
    const reject = vi.spyOn(hoursApi, 'rejectExtension').mockResolvedValue({ ...pendingRequest, status: 'rejected' })
    renderHours(['hours.allocate'], [pendingRequest])

    await userEvent.click(await screen.findByRole('button', { name: 'Rechazar' }))
    await userEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Rechazar pedido' }))

    expect(reject).toHaveBeenCalledWith(4)
  })
})
