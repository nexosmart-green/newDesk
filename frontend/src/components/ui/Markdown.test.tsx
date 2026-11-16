import { render, screen } from '@testing-library/react'
import { MarkdownView } from './Markdown'

describe('MarkdownView', () => {
  it('shows our uploaded images inline', () => {
    render(<MarkdownView source="![captura](/api/v1/task-images/abc)" />)

    expect(screen.getByRole('img', { name: 'captura' })).toHaveAttribute('src', '/api/v1/task-images/abc')
  })

  it('turns remote images into links so they cannot track who opens the task', () => {
    render(<MarkdownView source="![pixel](https://tracker.example/p.gif)" />)

    expect(screen.queryByRole('img')).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'pixel' })).toHaveAttribute('href', 'https://tracker.example/p.gif')
  })

  it('never renders raw HTML nor javascript: links', () => {
    render(<MarkdownView source={'<img src=x onerror=alert(1)>\n\n[click](javascript:alert(1))'} />)

    expect(document.querySelector('[onerror]')).toBeNull()
    expect(screen.getByText('click').closest('a')?.getAttribute('href') ?? '').not.toContain('javascript')
  })
})
