import { fireEvent, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { renderAt } from '@/test/renderWithProviders'
import * as tasksApi from '../tasksApi'
import { applyCommand, toggleLinePrefix } from './markdownCommands'
import { MarkdownEditor } from './MarkdownEditor'

function Harness({ initial = '', projectId = 9 }: { initial?: string; projectId?: number }) {
  const [value, setValue] = useState(initial)
  return <MarkdownEditor label="Descripción" value={value} onChange={setValue} projectId={projectId} />
}

function paste(textarea: HTMLElement, file: File) {
  fireEvent.paste(textarea, { clipboardData: { files: [file], types: ['Files'] } })
}

describe('markdown commands', () => {
  it('wraps the selection, or inserts a selected placeholder', () => {
    expect(applyCommand({ value: 'hola mundo', start: 5, end: 10 }, 'bold')).toEqual({ value: 'hola **mundo**', start: 7, end: 12 })
    expect(applyCommand({ value: '', start: 0, end: 0 }, 'italic')).toEqual({ value: '_cursiva_', start: 1, end: 8 })
  })

  it('toggles a line prefix on every selected line', () => {
    const listed = toggleLinePrefix({ value: 'uno\ndos', start: 0, end: 7 }, '- ')
    expect(listed.value).toBe('- uno\n- dos')
    expect(toggleLinePrefix({ ...listed, start: 0, end: listed.value.length }, '- ').value).toBe('uno\ndos')
  })

  it('builds a link with the URL selected and fences multi-line code', () => {
    expect(applyCommand({ value: 'ver spec', start: 4, end: 8 }, 'link')).toEqual({ value: 'ver [spec](https://)', start: 11, end: 19 })
    expect(applyCommand({ value: 'a\nb', start: 0, end: 3 }, 'code').value).toBe('```\na\nb\n```\n')
  })
})

describe('MarkdownEditor', () => {
  it('formats with the toolbar and previews the result', async () => {
    renderAt(<Harness />)

    await userEvent.click(screen.getByRole('button', { name: 'Negrita (Ctrl+B)' }))
    await userEvent.keyboard('importante')
    expect(screen.getByLabelText('Descripción')).toHaveValue('**importante**')

    await userEvent.click(screen.getByRole('button', { name: 'Vista previa' }))
    expect(screen.getByText('importante').tagName).toBe('STRONG')
    expect(screen.queryByRole('toolbar')).not.toBeInTheDocument()
  })

  it('uploads a pasted image with progress and inserts it where the cursor was', async () => {
    let finish: (url: string) => void = () => undefined
    const upload = vi.spyOn(tasksApi, 'uploadTaskImage').mockImplementation((_projectId, _file, onProgress) => {
      onProgress(40)
      return new Promise((resolve) => {
        finish = resolve
      })
    })
    renderAt(<Harness initial="Antes " />)
    const textarea = screen.getByLabelText('Descripción')
    ;(textarea as HTMLTextAreaElement).setSelectionRange(6, 6)

    paste(textarea, new File(['png'], 'captura.png', { type: 'image/png' }))

    expect(upload).toHaveBeenCalledWith(9, expect.any(File), expect.any(Function))
    expect(await screen.findByText('40%')).toBeInTheDocument()
    expect(screen.getByText('Subiendo captura.png')).toBeInTheDocument()
    expect(textarea).toHaveValue('Antes ![Subiendo imagen 1…]()\n')

    finish('/api/v1/task-images/abc')
    await vi.waitFor(() => expect(textarea).toHaveValue('Antes ![captura](/api/v1/task-images/abc)\n'))
    expect(screen.queryByText('Subiendo captura.png')).not.toBeInTheDocument()
  })

  it('refuses HEIC before uploading, with the reason', async () => {
    const upload = vi.spyOn(tasksApi, 'uploadTaskImage')
    renderAt(<Harness />)

    paste(screen.getByLabelText('Descripción'), new File(['x'], 'IMG_0001.HEIC', { type: 'image/heic' }))

    expect(await screen.findByText('Convertí la imagen a JPG o PNG.')).toBeInTheDocument()
    expect(upload).not.toHaveBeenCalled()
    expect(screen.getByLabelText('Descripción')).toHaveValue('')
  })

  it('removes the marker and toasts the backend message when the upload fails', async () => {
    const { ApiError } = await import('@/lib/api')
    vi.spyOn(tasksApi, 'uploadTaskImage').mockRejectedValue(new ApiError(422, 'Datos inválidos', { image: ['La imagen supera los 5 MB.'] }))
    renderAt(<Harness />)

    paste(screen.getByLabelText('Descripción'), new File(['x'], 'foto.jpg', { type: 'image/jpeg' }))

    expect(await screen.findByText('La imagen supera los 5 MB.')).toBeInTheDocument()
    expect(screen.getByLabelText('Descripción')).toHaveValue('')
  })

  it('lets text copied from Excel or Word paste as text, ignoring the bitmap that comes with it', () => {
    const upload = vi.spyOn(tasksApi, 'uploadTaskImage')
    renderAt(<Harness />)
    const file = new File(['png'], 'image.png', { type: 'image/png' })

    const notCancelled = fireEvent.paste(screen.getByLabelText('Descripción'), {
      clipboardData: { files: [file], types: ['text/plain', 'text/html', 'Files'] },
    })

    expect(notCancelled).toBe(true)
    expect(upload).not.toHaveBeenCalled()
  })

  it('inserts the image even when the file name has $ replacement patterns', async () => {
    vi.spyOn(tasksApi, 'uploadTaskImage').mockResolvedValue('/api/v1/task-images/abc')
    renderAt(<Harness />)

    paste(screen.getByLabelText('Descripción'), new File(['png'], "pago$&$'.png", { type: 'image/png' }))

    await vi.waitFor(() => expect(screen.getByLabelText('Descripción')).toHaveValue("![pago$&$'](/api/v1/task-images/abc)\n"))
  })
})
