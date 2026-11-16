import { render, screen } from '@testing-library/react'
import { buildTask } from '@/test/fixtures'
import { TaskAttachmentsBadge, TaskChecklistBadge } from './TaskBits'

describe('task row badges', () => {
  it('shows checklist progress, green and said in words when complete', () => {
    const { rerender } = render(<TaskChecklistBadge task={buildTask({ checklist_progress: { done: 3, total: 5 } })} />)
    expect(screen.getByText('3/5').parentElement).toHaveClass('text-muted')

    rerender(<TaskChecklistBadge task={buildTask({ checklist_progress: { done: 5, total: 5 } })} />)
    expect(screen.getByText('5/5').parentElement).toHaveClass('text-success')
    expect(screen.getByText('(completas)')).toBeInTheDocument()
  })

  it('shows nothing without steps or files', () => {
    const { container } = render(
      <>
        <TaskChecklistBadge task={buildTask({ checklist_progress: { done: 0, total: 0 } })} />
        <TaskAttachmentsBadge task={buildTask({ attachments_count: 0 })} />
      </>,
    )
    expect(container).toBeEmptyDOMElement()
  })

  it('shows the clip with how many files', () => {
    render(<TaskAttachmentsBadge task={buildTask({ attachments_count: 3 })} />)
    expect(screen.getByTitle('3 archivos adjuntos')).toHaveTextContent('3archivos adjuntos')
  })
})
