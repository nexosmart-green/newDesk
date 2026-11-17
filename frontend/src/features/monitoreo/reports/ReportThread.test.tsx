import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { mockReports } from '../test/reportsApi'
import { renderWithProviders } from '../test/render'
import { ReportPage } from './ReportPage'

// Del lado del equipo; los casos del cliente están en portal/ReportPages.test.tsx.
const png = (name = 'pantalla.png') => new File([new Uint8Array([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 1, 2, 3])], name, { type: 'image/png' })

function render(route: string) {
  return renderWithProviders(
    <Routes>
      <Route path="/monitoreo/reportes/:id" element={<ReportPage />} />
    </Routes>,
    { route },
  )
}

afterEach(() => vi.restoreAllMocks())

describe('chat del reporte', () => {
  it('el equipo ve el reporte como primer mensaje y responde con texto e imagen', async () => {
    const user = userEvent.setup()
    const { state, api } = mockReports()
    render('/monitoreo/reportes/3')
    const thread = await screen.findByRole('list', { name: 'Conversación' })
    const bubbles = within(thread).getAllByRole('listitem')
    expect(bubbles[0]).toHaveTextContent('cliente@playinn.com')
    expect(bubbles[0]).toHaveTextContent('¿Se puede agregar un campo de DNI')
    expect(bubbles[1]).toHaveTextContent('¿Lo querés obligatorio u opcional?')

    await user.type(screen.getByLabelText('Mensaje'), 'Listo, quedó obligatorio.')
    await user.upload(screen.getByLabelText('Imágenes del mensaje'), png())
    expect(await screen.findByRole('img', { name: 'Adjunta: pantalla.png' })).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Enviar' }))

    expect(await within(thread).findByText('Listo, quedó obligatorio.')).toBeInTheDocument()
    expect(within(thread).getByRole('img', { name: /^Imagen 1 de / })).toHaveAttribute('src', expect.stringMatching(/^\/api\/v1\/monitoring\/portal-admin\/reports\/3\/attachments\/\d+$/))
    // images[]: Laravel arma la lista solo con corchetes.
    const sent = api.post.mock.calls.at(-1)?.[1] as FormData
    expect(sent.get('body')).toBe('Listo, quedó obligatorio.')
    expect(sent.getAll('images[]').map((file) => (file as File).name)).toEqual(['pantalla.png'])
    expect(sent.has('images')).toBe(false)
    // Se limpia el formulario.
    expect(screen.getByLabelText('Mensaje')).toHaveValue('')
    expect(screen.queryByRole('img', { name: 'Adjunta: pantalla.png' })).not.toBeInTheDocument()
    expect(state.messages[3]?.at(-1)).toMatchObject({ author_kind: 'team', body: 'Listo, quedó obligatorio.' })
  })

  it('el equipo deja una nota interna, marcada como tal', async () => {
    const user = userEvent.setup()
    const { api } = mockReports()
    render('/monitoreo/reportes/3')
    const thread = await screen.findByRole('list', { name: 'Conversación' })

    const note = screen.getByRole('checkbox', { name: 'Nota interna (el cliente no la ve)' })
    await user.click(note)
    await user.type(screen.getByLabelText('Mensaje'), 'Ojo: es el mismo bug del mes pasado.')
    await user.click(screen.getByRole('button', { name: 'Enviar' }))

    const bubble = (await within(thread).findByText('Ojo: es el mismo bug del mes pasado.')).closest('li') as HTMLElement
    expect(bubble).toHaveTextContent('Nota interna')
    expect(note).not.toBeChecked()
    expect((api.post.mock.calls.at(-1)?.[1] as FormData | undefined)?.get('internal')).toBe('1')
  })

  it('el equipo puede ver solo las notas internas', async () => {
    mockReports()
    render('/monitoreo/reportes/2') // tiene una respuesta al cliente y ninguna nota
    await screen.findByRole('list', { name: 'Conversación' })
    expect(screen.getByRole('checkbox', { name: 'Solo notas internas (0)' })).toBeDisabled()
  })

  it('con notas, el filtro deja solo esas y al destildarlo vuelve todo', async () => {
    const { state } = mockReports()
    state.messages[3]?.push({ id: 951, author_kind: 'team', author: 'teamviolet@nexosmart.com.ar', body: 'Diagnóstico: falta la columna dni.', created_at: new Date().toISOString(), images: [], internal: true })
    const user = userEvent.setup()
    render('/monitoreo/reportes/3')
    const thread = await screen.findByRole('list', { name: 'Conversación' })
    const all = within(thread).getAllByRole('listitem').length
    expect(all).toBeGreaterThan(2) // el reporte, la respuesta al cliente y la nota

    const filter = screen.getByRole('checkbox', { name: 'Solo notas internas (1)' })
    await user.click(filter)
    expect(within(thread).getAllByRole('listitem')).toHaveLength(1)
    expect(within(thread).getByRole('listitem')).toHaveTextContent('Diagnóstico: falta la columna dni.')
    expect(within(thread).queryByText('¿Se puede agregar un campo de DNI', { exact: false })).not.toBeInTheDocument()

    await user.click(filter)
    expect(within(thread).getAllByRole('listitem')).toHaveLength(all)
  })

  it('no deja adjuntar una cuarta imagen ni mandar un mensaje vacío', async () => {
    const user = userEvent.setup()
    const { api } = mockReports()
    render('/monitoreo/reportes/3')
    await screen.findByRole('list', { name: 'Conversación' })
    // La página del equipo tiene otros avisos (el /api/errors que no respondió): se mira el del formulario.
    const composer = screen.getByRole('form', { name: 'Escribir un mensaje' })

    await user.click(screen.getByRole('button', { name: 'Enviar' }))
    expect(within(composer).getByRole('alert')).toHaveTextContent('Escribí un mensaje o adjuntá una imagen.')
    expect(api.post).not.toHaveBeenCalled()

    await user.upload(screen.getByLabelText('Imágenes del mensaje'), [png('1.png'), png('2.png'), png('3.png'), png('4.png')])
    expect(within(composer).getByRole('alert')).toHaveTextContent('Hasta 3 imágenes por mensaje.')
    expect(within(screen.getByRole('list', { name: 'Imágenes adjuntas' })).getAllByRole('listitem')).toHaveLength(3)
    await user.click(screen.getByRole('button', { name: 'Quitar 2.png' }))
    expect(within(screen.getByRole('list', { name: 'Imágenes adjuntas' })).getAllByRole('listitem')).toHaveLength(2)
  })
})
