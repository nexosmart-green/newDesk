import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { FilterBar } from './FilterBar'
import { FilterCheck } from './FilterCheck'
import { FilterSelect } from './FilterSelect'
import { SearchInput } from './SearchInput'

const statusOptions = [
  { value: '', label: 'Todos' },
  { value: 'active', label: 'Activos' },
]

function Bar({ categoryActive = false }: { categoryActive?: boolean }) {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [overdue, setOverdue] = useState(false)
  return (
    <FilterBar
      active={(status ? 1 : 0) + (overdue ? 1 : 0) + (categoryActive ? 1 : 0)}
      moreActive={categoryActive ? 1 : 0}
      search={<SearchInput placeholder="Buscar por nombre" value={search} onChange={setSearch} />}
      more={
        <FilterSelect
          label="Categoría"
          value={categoryActive ? '1' : ''}
          options={[{ value: '', label: 'Todas' }, { value: '1', label: 'Diseño' }]}
          onChange={() => {}}
        />
      }
    >
      <FilterSelect label="Estado" value={status} options={statusOptions} onChange={setStatus} />
      <FilterCheck label="Solo vencidas" checked={overdue} onChange={setOverdue} />
    </FilterBar>
  )
}

describe('FilterBar', () => {
  it('every control has a name without a visible label; the select shows its dimension', async () => {
    render(<Bar />)

    expect(screen.getByRole('searchbox', { name: 'Buscar' })).toHaveAttribute('placeholder', 'Buscar por nombre')
    expect(screen.getByText('Estado:')).toHaveTextContent('Estado: Todos')
    await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Estado' }), 'Activos')
    expect(screen.getByText('Estado:')).toHaveTextContent('Estado: Activos')
    expect(screen.getByRole('checkbox', { name: 'Solo vencidas' })).not.toBeChecked()
  })

  it('"Más filtros" is a disclosure with the count of the ones in use', async () => {
    render(<Bar categoryActive />)

    const toggle = screen.getByRole('button', { name: 'Menos filtros (1)' })
    expect(toggle).toHaveAttribute('aria-expanded', 'true')
    await userEvent.click(toggle)
    expect(screen.getByRole('button', { name: 'Más filtros (1)' })).toHaveAttribute('aria-expanded', 'false')
  })

  it('on a phone "Filtros (n)" opens every filter in a sheet', async () => {
    render(<Bar categoryActive />)

    await userEvent.click(screen.getByRole('button', { name: 'Filtros (1)' }))
    const sheet = await screen.findByRole('dialog', { name: 'Filtros' })
    expect(within(sheet).getByRole('combobox', { name: 'Estado' })).toBeInTheDocument()
    expect(within(sheet).getByRole('combobox', { name: 'Categoría' })).toBeInTheDocument()
    await userEvent.click(within(sheet).getByRole('button', { name: 'Listo' }))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})
