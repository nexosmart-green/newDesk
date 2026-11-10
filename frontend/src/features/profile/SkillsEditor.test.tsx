import { fireEvent, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as catalogsApi from '@/features/catalogs/catalogsApi'
import * as peopleApi from '@/features/people/peopleApi'
import { buildProfile } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import { SkillsEditor } from './SkillsEditor'

describe('SkillsEditor', () => {
  it('saves the whole set of skills with their levels (RF-01.06)', async () => {
    vi.spyOn(catalogsApi, 'fetchCatalog').mockResolvedValue([
      { id: 1, name: 'PHP' },
      { id: 3, name: 'React' },
    ])
    const save = vi.spyOn(peopleApi, 'saveMySkills').mockResolvedValue(buildProfile())
    renderAt(<SkillsEditor skills={[{ id: 1, name: 'PHP', level: 40 }]} />)

    await screen.findByRole('option', { name: 'React' })
    expect(screen.queryByRole('option', { name: 'PHP' })).not.toBeInTheDocument()
    await userEvent.selectOptions(screen.getByLabelText('Agregar habilidad'), 'React')
    await userEvent.click(screen.getByRole('button', { name: 'Agregar' }))
    fireEvent.change(screen.getByLabelText('Nivel de React'), { target: { value: '80' } })
    await userEvent.click(screen.getByRole('button', { name: 'Guardar habilidades' }))

    expect(save).toHaveBeenCalledWith([
      { id: 1, level: 40 },
      { id: 3, level: 80 },
    ])
  })
})
