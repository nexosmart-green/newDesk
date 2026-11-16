import { screen } from '@testing-library/react'
import { renderAt } from '@/test/renderWithProviders'
import { TaskCode } from './TaskCode'

describe('TaskCode', () => {
  it('shows the code as stored: lowercase, quiet, no caps or monospace', () => {
    renderAt(<TaskCode code="bhn-1" />)

    const code = screen.getByText('bhn-1')
    expect(code.className).toContain('text-muted')
    expect(code.className).toContain('font-medium')
    expect(code.className).not.toMatch(/uppercase|font-mono/)
  })

  it('links to the task when asked', () => {
    renderAt(<TaskCode code="bhn-1" link />)

    expect(screen.getByRole('link', { name: 'bhn-1' })).toHaveAttribute('href', '/tareas/bhn-1')
  })
})
