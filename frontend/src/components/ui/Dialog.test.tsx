import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { Dialog, DialogFooter } from './Dialog'

function Harness({ onClose = () => {} }: { onClose?: () => void }) {
  const [open, setOpen] = useState(false)
  return (
    // A transformed/blurred ancestor is what clipped dialogs opened from the header.
    <header data-testid="header" style={{ backdropFilter: 'blur(4px)' }}>
      <button type="button" onClick={() => setOpen(true)}>
        Abrir
      </button>
      {open && (
        <Dialog
          title="Detener cronómetro"
          onClose={() => {
            onClose()
            setOpen(false)
          }}
        >
          <textarea aria-label="Comentario" />
          <DialogFooter>
            <button type="button">Guardar</button>
          </DialogFooter>
        </Dialog>
      )}
    </header>
  )
}

describe('Dialog', () => {
  it('renders in a portal on document.body, outside the header that opened it', async () => {
    render(<Harness />)
    await userEvent.click(screen.getByRole('button', { name: 'Abrir' }))

    const dialog = screen.getByRole('dialog', { name: 'Detener cronómetro' })
    expect(screen.getByTestId('header')).not.toContainElement(dialog)
    expect(dialog.parentElement?.parentElement).toBe(document.body)
  })

  it('focuses the first field, closes with Escape and returns focus to the trigger', async () => {
    const onClose = vi.fn()
    render(<Harness onClose={onClose} />)
    const trigger = screen.getByRole('button', { name: 'Abrir' })
    await userEvent.click(trigger)

    expect(screen.getByLabelText('Comentario')).toHaveFocus()
    await userEvent.keyboard('{Escape}')

    expect(onClose).toHaveBeenCalledTimes(1)
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(trigger).toHaveFocus()
  })

  it('closes from the X button and keeps Tab inside the dialog', async () => {
    const onClose = vi.fn()
    render(<Harness onClose={onClose} />)
    await userEvent.click(screen.getByRole('button', { name: 'Abrir' }))

    await userEvent.tab() // textarea → Guardar
    await userEvent.tab() // Guardar → wraps to the first focusable (X)
    expect(screen.getByRole('dialog')).toContainElement(document.activeElement as HTMLElement)

    await userEvent.click(screen.getByRole('button', { name: 'Cerrar' }))
    expect(onClose).toHaveBeenCalled()
  })
})

function FormHarness({ onClose }: { onClose: () => void }) {
  const [note, setNote] = useState('')
  return (
    <Dialog title="Nueva nota" dirty={note !== ''} onClose={onClose}>
      <textarea aria-label="Nota" value={note} onChange={(event) => setNote(event.target.value)} />
    </Dialog>
  )
}

const backdropOf = (dialog: HTMLElement) => dialog.parentElement as HTMLElement

describe('Dialog with unsaved changes (dirty)', () => {
  it('closes at once from Escape or the backdrop while the form is clean', async () => {
    const onClose = vi.fn()
    render(<FormHarness onClose={onClose} />)

    await userEvent.keyboard('{Escape}')
    await userEvent.click(backdropOf(screen.getByRole('dialog', { name: 'Nueva nota' })))

    expect(onClose).toHaveBeenCalledTimes(2)
    expect(screen.queryByRole('dialog', { name: '¿Descartar los cambios?' })).not.toBeInTheDocument()
  })

  it('asks before discarding on Escape; "Seguir editando" keeps what was typed', async () => {
    const onClose = vi.fn()
    render(<FormHarness onClose={onClose} />)
    await userEvent.type(screen.getByLabelText('Nota'), 'Llamar al cliente')

    await userEvent.keyboard('{Escape}')
    const ask = screen.getByRole('dialog', { name: '¿Descartar los cambios?' })
    expect(within(ask).getByRole('button', { name: 'Seguir editando' })).toHaveFocus()
    await userEvent.click(within(ask).getByRole('button', { name: 'Seguir editando' }))

    expect(onClose).not.toHaveBeenCalled()
    expect(screen.queryByRole('dialog', { name: '¿Descartar los cambios?' })).not.toBeInTheDocument()
    expect(screen.getByLabelText('Nota')).toHaveValue('Llamar al cliente')
  })

  it('asks before discarding on a backdrop click; "Descartar" closes the form', async () => {
    const onClose = vi.fn()
    render(<FormHarness onClose={onClose} />)
    await userEvent.type(screen.getByLabelText('Nota'), 'Llamar al cliente')

    await userEvent.click(backdropOf(screen.getByRole('dialog', { name: 'Nueva nota' })))
    expect(onClose).not.toHaveBeenCalled()
    await userEvent.click(within(screen.getByRole('dialog', { name: '¿Descartar los cambios?' })).getByRole('button', { name: 'Descartar' }))

    expect(onClose).toHaveBeenCalledTimes(1)
  })
})
