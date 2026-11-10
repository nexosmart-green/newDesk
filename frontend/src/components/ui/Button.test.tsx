import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Button } from './Button'

describe('Button', () => {
  it('while loading: disabled, busy, same accessible name, no double submit', async () => {
    const onClick = vi.fn()
    render(
      <Button loading onClick={onClick}>
        Guardar
      </Button>,
    )
    const button = screen.getByRole('button', { name: 'Guardar' })

    expect(button).toBeDisabled()
    expect(button).toHaveAttribute('aria-busy', 'true')
    await userEvent.click(button)
    expect(onClick).not.toHaveBeenCalled()
  })

  it('is a normal button otherwise', () => {
    render(<Button>Guardar</Button>)

    expect(screen.getByRole('button', { name: 'Guardar' })).toBeEnabled()
    expect(screen.getByRole('button', { name: 'Guardar' })).not.toHaveAttribute('aria-busy')
  })
})
