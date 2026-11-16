import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useEditInPlace } from './useEditInPlace'

function Editable() {
  const { containerRef, editing, start, stop } = useEditInPlace()
  return (
    <div ref={containerRef}>
      {editing ? (
        <form>
          <label>
            Título
            <input defaultValue="Prueba" />
          </label>
          <button type="button" onClick={stop}>
            Cancelar
          </button>
        </form>
      ) : (
        <button type="button" onClick={start} data-edit-trigger>
          Editar detalles
        </button>
      )}
    </div>
  )
}

describe('useEditInPlace', () => {
  it('focuses the first field on entering and the trigger again on leaving', async () => {
    render(<Editable />)

    await userEvent.click(screen.getByRole('button', { name: 'Editar detalles' }))
    expect(screen.getByLabelText('Título')).toHaveFocus()

    await userEvent.click(screen.getByRole('button', { name: 'Cancelar' }))
    expect(screen.getByRole('button', { name: 'Editar detalles' })).toHaveFocus()
  })

  it('does not steal focus on the first render', () => {
    render(<Editable />)

    expect(document.body).toHaveFocus()
  })
})
