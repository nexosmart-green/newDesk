import { render, screen } from '@testing-library/react'
import { Inbox } from 'lucide-react'
import { Button } from './Button'
import { EmptyState } from './EmptyState'

describe('EmptyState', () => {
  it('says it in one sentence and offers the one way out', () => {
    const { container } = render(
      <EmptyState icon={Inbox} action={<Button>Crear la primera</Button>}>
        Todavía no hay nada acá.
      </EmptyState>,
    )

    expect(screen.getByText('Todavía no hay nada acá.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Crear la primera' })).toBeInTheDocument()
    // A line in the log, not a poster: no dashed frame, the icon is decoration.
    expect(container.innerHTML).not.toContain('border-dashed')
    expect(container.querySelector('svg')).toHaveAttribute('aria-hidden', 'true')
  })

  it('without an action renders only the sentence', () => {
    render(<EmptyState icon={Inbox}>Nada por ahora.</EmptyState>)

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})
