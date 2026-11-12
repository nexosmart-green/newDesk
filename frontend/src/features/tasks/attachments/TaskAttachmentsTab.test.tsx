import { fireEvent, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import * as authApi from '@/features/auth/authApi'
import { ApiError } from '@/lib/api'
import { buildUser, renderAt } from '@/test/renderWithProviders'
import { buildAttachment } from '@/test/taskExtrasFixtures'
import type { TaskAttachment } from '../types'
import { attachmentProblem } from './attachmentRules'
import * as attachmentsApi from './attachmentsApi'
import { TaskAttachmentsTab } from './TaskAttachmentsTab'

const photo = buildAttachment({ id: 1, uuid: 'p1', name: 'pantalla.png' })
const second = buildAttachment({ id: 2, uuid: 'p2', name: 'error.jpg', mime: 'image/jpeg' })
const pdf = buildAttachment({
  id: 3,
  uuid: 'd1',
  name: 'presupuesto.pdf',
  mime: 'application/pdf',
  is_image: false,
  size: 3_565_158,
  can_delete: false,
  uploaded_by: { id: 2, full_name: 'Juan Pérez' },
})

let server: TaskAttachment[] = []

function renderTab(attachments: TaskAttachment[] = [photo, pdf, second]) {
  server = attachments
  vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser())
  vi.spyOn(attachmentsApi, 'fetchTaskAttachments').mockImplementation(async () => server)
  return renderAt(<TaskAttachmentsTab code="bhn-60" />)
}

function pick(...files: File[]) {
  fireEvent.change(screen.getByTestId('attachment-input'), { target: { files } })
}

function file(name: string, type: string, size = 10): File {
  const created = new File(['x'], name, { type })
  Object.defineProperty(created, 'size', { value: size })
  return created
}

describe('attachmentProblem', () => {
  it('uses the backend messages for HEIC, size and type', () => {
    expect(attachmentProblem(file('IMG_1.HEIC', 'image/heic'))).toBe('Convertí la imagen a JPG o PNG.')
    expect(attachmentProblem(file('video.mp4', 'video/mp4'))).toBe('Ese tipo de archivo no se puede adjuntar.')
    expect(attachmentProblem(file('grande.pdf', 'application/pdf', 21 * 1024 * 1024))).toBe('El archivo puede pesar hasta 20 MB.')
    expect(attachmentProblem(file('planilla.XLSX', ''))).toBeNull()
  })
})

describe('TaskAttachmentsTab', () => {
  it('shows a skeleton, then a grid with size, who and when, and delete only when allowed', async () => {
    renderTab()

    expect(screen.getByRole('status')).toHaveTextContent('Cargando archivos…')
    const grid = await screen.findByRole('list', { name: 'Archivos adjuntos' })
    expect(within(grid).getAllByRole('listitem')).toHaveLength(3)
    expect(within(grid).getByText('3,4 MB')).toBeInTheDocument()
    expect(within(grid).getByText(/Juan Pérez/)).toBeInTheDocument()
    expect(within(grid).getByRole('link', { name: 'Abrir presupuesto.pdf' })).toHaveAttribute('target', '_blank')
    expect(within(grid).getByRole('link', { name: 'Descargar presupuesto.pdf' })).toHaveAttribute('href', '/api/v1/task-attachments/d1')
    expect(within(grid).getByRole('button', { name: 'Borrar pantalla.png' })).toBeInTheDocument()
    expect(within(grid).queryByRole('button', { name: 'Borrar presupuesto.pdf' })).not.toBeInTheDocument()
  })

  it('makes the drop zone the call to action when there are no files', async () => {
    renderTab([])

    expect(await screen.findByText('Todavía no hay archivos')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Elegir archivos' })).toBeInTheDocument()
  })

  it('offers Reintentar when the list fails to load', async () => {
    vi.spyOn(authApi, 'fetchCurrentUser').mockResolvedValue(buildUser())
    const fetch = vi.spyOn(attachmentsApi, 'fetchTaskAttachments').mockRejectedValueOnce(new ApiError(500, 'boom')).mockResolvedValue([photo])
    renderAt(<TaskAttachmentsTab code="bhn-60" />)

    await userEvent.click(await screen.findByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('list', { name: 'Archivos adjuntos' })).toBeInTheDocument()
    expect(fetch).toHaveBeenCalledTimes(2)
  })

  it('uploads several files in parallel with a bar each, then shows them', async () => {
    const finishers = new Map<string, () => void>()
    const upload = vi.spyOn(attachmentsApi, 'uploadTaskAttachment').mockImplementation((_code, picked, onProgress) => {
      onProgress(picked.name === 'a.pdf' ? 25 : 60)
      return new Promise((resolve) => {
        finishers.set(picked.name, () => {
          const saved = buildAttachment({ id: 10 + finishers.size, uuid: picked.name, name: picked.name, is_image: false, mime: 'application/pdf' })
          server = [saved, ...server]
          resolve(saved)
        })
      })
    })
    renderTab([])
    await screen.findByText('Todavía no hay archivos')

    pick(file('a.pdf', 'application/pdf'), file('b.docx', ''))

    expect(upload).toHaveBeenCalledTimes(2)
    expect(await screen.findByText('25%')).toBeInTheDocument()
    expect(screen.getByText('60%')).toBeInTheDocument()
    expect(screen.getByRole('progressbar', { name: 'Progreso de a.pdf' })).toBeInTheDocument()

    finishers.get('a.pdf')?.()
    expect(await screen.findByText('«a.pdf» adjuntado.')).toBeInTheDocument()
    expect(await screen.findByRole('link', { name: 'Abrir a.pdf' })).toBeInTheDocument()
    expect(screen.getByText('Subiendo b.docx')).toBeInTheDocument()
  })

  it('refuses wrong files before uploading, listing each reason', async () => {
    const upload = vi.spyOn(attachmentsApi, 'uploadTaskAttachment')
    renderTab([])
    await screen.findByText('Todavía no hay archivos')

    pick(file('IMG_2.heic', 'image/heic'), file('peli.mov', 'video/quicktime'))

    expect(upload).not.toHaveBeenCalled()
    const list = screen.getByRole('list', { name: 'Archivos subiendo' })
    expect(within(list).getByText('Convertí la imagen a JPG o PNG.')).toBeInTheDocument()
    expect(within(list).getByText('Ese tipo de archivo no se puede adjuntar.')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Descartar peli.mov' }))
    expect(screen.queryByText('Ese tipo de archivo no se puede adjuntar.')).not.toBeInTheDocument()
  })

  it('toasts the backend reason and lets a failed network upload be retried', async () => {
    const upload = vi
      .spyOn(attachmentsApi, 'uploadTaskAttachment')
      .mockRejectedValueOnce(new ApiError(0, 'No se pudo conectar con el servidor'))
      .mockImplementation(async () => {
        const saved = buildAttachment({ uuid: 'n1', name: 'notas.txt', is_image: false, mime: 'text/plain' })
        server = [saved]
        return saved
      })
    renderTab([])
    await screen.findByText('Todavía no hay archivos')

    pick(file('notas.txt', 'text/plain'))

    expect(await screen.findAllByText('Revisá tu conexión a internet y probá de nuevo.')).not.toHaveLength(0)
    await userEvent.click(screen.getByRole('button', { name: 'Reintentar notas.txt' }))

    expect(await screen.findByText('«notas.txt» adjuntado.')).toBeInTheDocument()
    expect(upload).toHaveBeenCalledTimes(2)
  })

  it('opens images in a viewer that moves with the arrows and closes with Escape', async () => {
    renderTab()

    await userEvent.click(await screen.findByRole('button', { name: 'Ver pantalla.png' }))

    const viewer = screen.getByRole('dialog', { name: 'pantalla.png' })
    expect(within(viewer).getByText('1 de 2')).toBeInTheDocument()
    expect(within(viewer).getByRole('button', { name: 'Cerrar visor' })).toHaveFocus()
    await userEvent.keyboard('{ArrowRight}')
    expect(screen.getByRole('dialog', { name: 'error.jpg' })).toBeInTheDocument()
    expect(screen.getByRole('img', { name: 'error.jpg' })).toHaveAttribute('src', '/api/v1/task-attachments/p2')
    await userEvent.keyboard('{ArrowRight}')
    expect(screen.getByRole('dialog', { name: 'pantalla.png' })).toBeInTheDocument()

    await userEvent.keyboard('{Escape}')
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Ver pantalla.png' })).toHaveFocus()
  })

  it('deletes after confirming', async () => {
    const remove = vi.spyOn(attachmentsApi, 'deleteTaskAttachment').mockImplementation(async () => {
      server = [pdf, second]
    })
    renderTab()

    await userEvent.click(await screen.findByRole('button', { name: 'Borrar pantalla.png' }))
    await userEvent.click(within(screen.getByRole('dialog', { name: 'Borrar archivo' })).getByRole('button', { name: 'Borrar' }))

    expect(remove).toHaveBeenCalledWith('p1')
    expect(await screen.findByText('«pantalla.png» borrado.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Ver pantalla.png' })).not.toBeInTheDocument()
  })
})
