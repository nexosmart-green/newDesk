import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import * as catalogsApi from '@/features/catalogs/catalogsApi'
import { buildPerson, page } from '@/test/fixtures'
import { buildUser, renderAt } from '@/test/renderWithProviders'
import { directoryQueryParams } from './peopleApi'
import * as peopleApi from './peopleApi'
import { PeopleDirectoryPage } from './PeopleDirectoryPage'
import { emptyDirectoryFilters } from './types'

describe('PeopleDirectoryPage', () => {
  it('answers "¿quién sabe React?" with a skill and a minimum level (RF-01.06)', async () => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser())
    vi.spyOn(catalogsApi, 'fetchCatalog').mockImplementation(async (type) =>
      type === 'skills' ? [{ id: 3, name: 'React' }] : [{ id: 7, name: 'Dev' }],
    )
    const fetchPeople = vi.spyOn(peopleApi, 'fetchPeople').mockResolvedValue(
      page([buildPerson({ skills: [{ id: 3, name: 'React', level: 80 }] })]),
    )
    renderAt(<PeopleDirectoryPage />)

    await screen.findByRole('option', { name: 'React' })
    await userEvent.selectOptions(screen.getByLabelText('Habilidad'), 'React')
    await userEvent.selectOptions(screen.getByLabelText('Nivel mínimo'), '75 o más')

    await vi.waitFor(() =>
      expect(fetchPeople).toHaveBeenLastCalledWith({ ...emptyDirectoryFilters, skill_id: 3, min_level: 75 }),
    )
    expect(await screen.findByText('React: 80/100')).toBeInTheDocument()
  })
})

describe('directoryQueryParams', () => {
  it('sends only the chosen filters, and min_level only with a skill', () => {
    expect(directoryQueryParams({ ...emptyDirectoryFilters, skill_id: 3, min_level: 75 })).toEqual({
      page: 1,
      skill_id: 3,
      min_level: 75,
    })
    expect(directoryQueryParams({ ...emptyDirectoryFilters, search: ' ana ', min_level: 75 })).toEqual({ page: 1, search: 'ana' })
  })
})
