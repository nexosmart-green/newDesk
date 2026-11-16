import { render, screen } from '@testing-library/react'
import { NotificationItem } from './NotificationItem'
import type { AppNotification } from './types'

const base: AppNotification = {
  id: 'n1',
  event: 'extension_requested',
  title: 'Juan pide más horas para Bahía Web',
  body: '',
  path: '/proyectos/4/horas',
  read_at: null,
  created_at: null,
}

describe('NotificationItem', () => {
  it('unread: says "Nueva" to screen readers, weight 500 and a pink dot; an empty body is left out', () => {
    const { container } = render(
      <ul>
        <NotificationItem notification={base} onOpen={() => {}} />
      </ul>,
    )
    const button = screen.getByRole('button', { name: /^Nueva:\s*Juan pide más horas/ })

    expect(button.querySelector('.bg-live')).not.toBeNull()
    expect(screen.getByText('Juan pide más horas para Bahía Web', { exact: false })).toHaveClass('font-medium')
    expect(container).not.toHaveTextContent('Sin mensaje')
    expect(button.querySelectorAll('span.block')).toHaveLength(1)
  })

  it('read: no dot, no "Nueva"', () => {
    render(
      <ul>
        <NotificationItem notification={{ ...base, read_at: '2026-11-10T10:00:00Z', body: '«Se agotaron»' }} onOpen={() => {}} />
      </ul>,
    )

    expect(screen.getByRole('button')).not.toHaveTextContent('Nueva')
    expect(screen.getByRole('button').querySelector('.bg-live')).toBeNull()
    expect(screen.getByText('«Se agotaron»')).toBeInTheDocument()
  })
})
