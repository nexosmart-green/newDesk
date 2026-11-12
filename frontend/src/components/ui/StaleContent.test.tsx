import { render, screen } from '@testing-library/react'
import { StaleContent } from './StaleContent'

describe('StaleContent', () => {
  it('dims the previous results and marks them busy while new ones load', () => {
    render(
      <StaleContent stale className="space-y-4">
        <p>Resultados</p>
      </StaleContent>,
    )

    const wrapper = screen.getByText('Resultados').parentElement
    expect(wrapper).toHaveAttribute('aria-busy', 'true')
    expect(wrapper).toHaveClass('opacity-60', 'space-y-4')
  })

  it('shows fresh results as they are', () => {
    render(
      <StaleContent stale={false}>
        <p>Resultados</p>
      </StaleContent>,
    )

    const wrapper = screen.getByText('Resultados').parentElement
    expect(wrapper).not.toHaveAttribute('aria-busy')
    expect(wrapper).not.toHaveClass('opacity-60')
  })
})
