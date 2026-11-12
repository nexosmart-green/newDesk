import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import * as peopleApi from '@/features/people/peopleApi'
import * as projectsApi from '@/features/projects/projectsApi'
import { buildPerson, buildProject, buildTimeEntry, entryPage, page } from '@/test/fixtures'
import { buildUser, renderAt } from '@/test/renderWithProviders'
import * as hoursApi from './hoursApi'
import { MyHoursPage } from './MyHoursPage'
import { emptyMyEntryFilters } from './types'

const recent = buildTimeEntry({ id: 301, is_running: false, ended_at: '2026-11-06T15:00:00+00:00', comment: 'Revisé el formulario' })
const old = buildTimeEntry({
  id: 302,
  is_running: false,
  started_at: '2026-10-01T12:00:00+00:00',
  ended_at: '2026-10-01T13:00:00+00:00',
  task: { id: 61, code: 'bhn-61', title: 'Viejo' },
})

function renderPage(permissions: string[]) {
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser({ permissions }))
  vi.spyOn(projectsApi, 'fetchProjects').mockResolvedValue(page([buildProject()]))
  vi.spyOn(peopleApi, 'fetchPeople').mockResolvedValue(page([buildPerson(), buildPerson({ id: 2, first_name: 'Juan', last_name: 'Pérez' })]))
  const fetchEntries = vi.spyOn(hoursApi, 'fetchEntries').mockResolvedValue(entryPage([recent, old], '2026-11-01T03:00:00Z'))
  renderAt(<MyHoursPage />)
  return { fetchEntries }
}

describe('MyHoursPage', () => {
  it('lists my entries with their project, and no "Persona" filter without time.manage', async () => {
    const { fetchEntries } = renderPage([])

    expect(await screen.findByText('Revisé el formulario')).toBeInTheDocument()
    expect(screen.getAllByText(/Bahía Web ·/)).toHaveLength(2)
    expect(screen.queryByLabelText('Persona')).not.toBeInTheDocument()
    expect(fetchEntries).toHaveBeenCalledWith(emptyMyEntryFilters)
  })

  it('locks entries older than the window: no Editar/Borrar, a note instead', async () => {
    renderPage([])

    expect(await screen.findByRole('button', { name: 'Editar registro de bhn-60' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Editar registro de bhn-61' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Borrar registro de bhn-61' })).not.toBeInTheDocument()
    expect(screen.getByText(/Pasaron más días de los permitidos/)).toBeInTheDocument()
  })

  it('lets time.manage pick another person', async () => {
    const { fetchEntries } = renderPage(['time.manage'])

    await userEvent.selectOptions(await screen.findByLabelText('Persona'), await screen.findByRole('option', { name: 'Juan Pérez' }))

    expect(fetchEntries).toHaveBeenLastCalledWith({ ...emptyMyEntryFilters, user_id: 2 })
  })
})
