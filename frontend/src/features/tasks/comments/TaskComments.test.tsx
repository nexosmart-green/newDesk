import { fireEvent, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { page } from '@/test/fixtures'
import { renderAt } from '@/test/renderWithProviders'
import { TaskComments } from '../TaskComments'
import * as tasksApi from '../tasksApi'
import type { TaskComment } from '../types'

const members = [
  { id: 2, full_name: 'Juan Pérez' },
  { id: 3, full_name: 'Lucía Gómez' },
]

const comment: TaskComment = {
  id: 1,
  body: 'Listo @Juan Pérez\nmirá **esto**:\n- uno\n- dos\n\n![captura](/api/v1/task-images/abc)\n\n`@Juan Pérez` en código',
  author: { id: 1, full_name: 'Ana García' },
  mentions: [{ id: 2, full_name: 'Juan Pérez' }],
  created_at: '2026-11-10T12:00:00+00:00',
}

function renderComments(comments: TaskComment[] = [comment]) {
  vi.spyOn(tasksApi, 'fetchTaskComments').mockResolvedValue(page(comments))
  return renderAt(<TaskComments code="bhn-60" projectId={9} members={members} />)
}

describe('TaskComments', () => {
  it('renders markdown like a chat: single line breaks, lists, images and highlighted mentions', async () => {
    renderComments()

    expect(await screen.findByText('esto')).toHaveProperty('tagName', 'STRONG')
    const mention = screen.getAllByText('@Juan Pérez').find((element) => element.tagName === 'SPAN')
    expect(mention).toHaveClass('text-accent')
    expect(document.querySelector('.markdown br')).not.toBeNull()
    expect(screen.getByText('uno').closest('ul')).not.toBeNull()
    expect(screen.getByRole('img', { name: 'captura' })).toHaveAttribute('src', '/api/v1/task-images/abc')
    expect(screen.getByText('@Juan Pérez', { selector: 'code' }).querySelector('span')).toBeNull()
  })

  it('invites to write the first comment', async () => {
    renderComments([])

    expect(await screen.findByText(/Todavía no hay comentarios/)).toBeInTheDocument()
  })

  it('formats with Ctrl+B, keeps the mention autocomplete and publishes with Ctrl+Enter', async () => {
    const post = vi.spyOn(tasksApi, 'postTaskComment').mockResolvedValue(comment)
    renderComments([])
    const box = await screen.findByLabelText('Nuevo comentario')

    await userEvent.click(box)
    await userEvent.keyboard('{Control>}b{/Control}')
    await userEvent.keyboard('ojo')
    expect(box).toHaveValue('**ojo**')

    await userEvent.keyboard('{End} @Lu')
    await userEvent.keyboard('{Enter}')
    expect(box).toHaveValue('**ojo** @Lucía Gómez ')

    await userEvent.keyboard('{Control>}{Enter}{/Control}')
    expect(post).toHaveBeenCalledWith('bhn-60', { body: '**ojo** @Lucía Gómez', mentioned_user_ids: [3] })
    await vi.waitFor(() => expect(box).toHaveValue(''))
  })

  it('uploads a pasted image into the comment and blocks publishing until it lands', async () => {
    let finish: (url: string) => void = () => undefined
    const upload = vi.spyOn(tasksApi, 'uploadTaskImage').mockImplementation((_projectId, _file, onProgress) => {
      onProgress(50)
      return new Promise((resolve) => {
        finish = resolve
      })
    })
    renderComments([])
    const box = await screen.findByLabelText('Nuevo comentario')

    fireEvent.paste(box, { clipboardData: { files: [new File(['png'], 'error.png', { type: 'image/png' })], types: ['Files'] } })

    expect(upload).toHaveBeenCalledWith(9, expect.any(File), expect.any(Function))
    expect(await screen.findByText('50%')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Comentar' })).toBeDisabled()
    expect(screen.getByText('Esperá a que terminen de subir las imágenes.')).toBeInTheDocument()

    finish('/api/v1/task-images/xyz')
    await vi.waitFor(() => expect(box).toHaveValue('![error](/api/v1/task-images/xyz)\n'))
    expect(screen.getByRole('button', { name: 'Comentar' })).toBeEnabled()
  })
})
