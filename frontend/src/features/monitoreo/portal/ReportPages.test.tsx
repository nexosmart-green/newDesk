import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route, Routes } from 'react-router'
import { mockClientPortal } from '../test/clientPortalApi'
import { playinnUser } from '../test/fixtures/clientPortal'
import { reply } from '../test/mockApi'
import { renderWithProviders } from '../test/render'
import { PortalReportDetailPage, PortalReportPage, PortalReportsPage } from './ReportPages'

function renderPortal(route: string) {
  return renderWithProviders(
    <Routes>
      <Route path="/portal/:slug/reportar" element={<PortalReportPage />} />
      <Route path="/portal/:slug/reportes" element={<PortalReportsPage />} />
      <Route path="/portal/:slug/reportes/:id" element={<PortalReportDetailPage />} />
      <Route path="/portal/:slug" element={<div>página de estado</div>} />
    </Routes>,
    { route },
  )
}

const png = (name = 'pantalla.png') => new File([new Uint8Array([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a, 1, 2, 3])], name, { type: 'image/png' })

afterEach(() => vi.restoreAllMocks())

describe('reportar un problema', () => {
  it('pide qué pasa y la urgencia antes de mandar nada', async () => {
    const user = userEvent.setup()
    const { api } = mockClientPortal(playinnUser)
    renderPortal('/portal/playinn/reportar')
    await user.click(await screen.findByRole('button', { name: 'Enviar reporte' }))

    expect(screen.getByText('Contanos un poco más (al menos 10 caracteres).')).toBeInTheDocument()
    expect(screen.getByText('Elegí qué tan urgente es.')).toBeInTheDocument()
    expect(api.post).not.toHaveBeenCalled()
  })

  it('manda el reporte con la captura y el contexto, y muestra lo que vio el chequeo en vivo', async () => {
    const user = userEvent.setup()
    const { state } = mockClientPortal(playinnUser)
    renderPortal('/portal/playinn/reportar')

    await user.type(await screen.findByLabelText('¿Qué pasa?'), 'Al pagar una reserva el botón se queda cargando.')
    // El cliente elige entre las partes que tiene: la API, los workers y los backups no se ofrecen.
    const where = screen.getByLabelText('¿Dónde?')
    expect([...where.querySelectorAll('option')].map((option) => option.textContent)).toEqual(['No sé', 'Web', 'App', 'CMS'])
    await user.selectOptions(where, 'App')
    expect(where).toHaveValue('app')
    await user.click(screen.getByRole('radio', { name: /No puedo trabajar/ }))
    await user.upload(screen.getByLabelText('Archivo de la captura'), png())
    expect(await screen.findByRole('img', { name: 'Captura adjunta' })).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Enviar reporte' }))

    const message = await screen.findByText('Detectamos problemas en la App y el CMS. El equipo ya está avisado.')
    expect(message.closest('[role="status"]')).not.toBeNull()
    // Lo que vio el chequeo en vivo, también en sus partes.
    const seen = screen.getByRole('heading', { name: 'Lo que vimos recién' }).closest('section') as HTMLElement
    expect(within(seen).getAllByRole('listitem').map((item) => item.textContent)).toEqual([
      expect.stringMatching(/^WebFunciona normalmente/),
      expect.stringMatching(/^AppResponde lento/),
      expect.stringMatching(/^CMSResponde lento/),
    ])
    expect(seen).not.toHaveTextContent(/API|Workers|Backups/)
    expect(screen.getByRole('heading', { name: 'Recibimos tu reporte', level: 1 })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ver mis reportes' })).toHaveAttribute('href', '/portal/playinn/reportes')

    // Lo que mandó el navegador: los mismos campos que monitoreo, multipart con la captura en `screenshot`.
    expect(state.created).toMatchObject({ description: 'Al pagar una reserva el botón se queda cargando.', urgency: 'blocking', component_label: 'App' })
    const form = state.created?.form as FormData
    expect(form.get('part')).toBe('app')
    expect((form.get('screenshot') as File).name).toBe('pantalla.png')
    expect(form.get('screen')).toMatch(/^\d+x\d+$/)
    expect(form.get('timezone')).not.toBe('')
    expect(form.get('language')).not.toBe('')
    expect(form.get('page_url')).toMatch(/^https?:\/\//)
  })

  it('no acepta una captura que no es imagen', async () => {
    const user = userEvent.setup({ applyAccept: false })
    mockClientPortal(playinnUser)
    renderPortal('/portal/playinn/reportar')
    await user.upload(await screen.findByLabelText('Archivo de la captura'), new File(['hola'], 'notas.txt', { type: 'text/plain' }))
    expect(screen.getByRole('alert')).toHaveTextContent('La captura tiene que ser PNG, JPG o WebP.')
    expect(screen.queryByRole('img', { name: 'Captura adjunta' })).not.toBeInTheDocument()
  })

  it('muestra el aviso del back si hay demasiados reportes seguidos', async () => {
    mockClientPortal(playinnUser, {
      'POST portal/projects/:slug/reports': reply({ status: 429, message: 'Ya recibimos varios reportes tuyos en la última hora.' }),
    })
    const user = userEvent.setup()
    renderPortal('/portal/playinn/reportar')
    await user.type(await screen.findByLabelText('¿Qué pasa?'), 'Otra vez no anda el sitio.')
    await user.click(screen.getByRole('radio', { name: /Consulta/ }))
    await user.click(screen.getByRole('button', { name: 'Enviar reporte' }))
    expect(await screen.findByText('Ya recibimos varios reportes tuyos en la última hora.')).toBeInTheDocument()
  })
})

describe('mis reportes', () => {
  it('lista los reportes del proyecto con su estado', async () => {
    mockClientPortal(playinnUser)
    renderPortal('/portal/playinn/reportes')
    const list = await screen.findByRole('list', { name: 'Reportes' })
    const items = within(list).getAllByRole('listitem')
    expect(items).toHaveLength(2)
    expect(items[0]).toHaveTextContent('En revisión')
    expect(items[0]).toHaveTextContent('Consulta')
    expect(items[1]).toHaveTextContent('Resuelto')
    expect(items[1]).toHaveTextContent('No puedo trabajar')
    // El 3 tiene una respuesta del equipo sin leer; cada tarjeta abre el chat del reporte.
    expect(items[0]).toHaveTextContent('Respuesta nueva')
    expect(items[1]).not.toHaveTextContent('Respuesta nueva')
    expect(within(items[0] as HTMLElement).getByRole('link')).toHaveAttribute('href', '/portal/playinn/reportes/3')
    expect(screen.getByRole('link', { name: /Reportar un problema/ })).toHaveAttribute('href', '/portal/playinn/reportar')
  })
})

// El chat del lado del cliente (el del equipo está en reports/ReportThread.test.tsx).
describe('chat del reporte en el portal', () => {
  it('el cliente no ve las notas internas ni la opción de escribirlas', async () => {
    const { state } = mockClientPortal(playinnUser)
    state.messages[3]?.push({ id: 900, author_kind: 'team', author: 'teamviolet@nexosmart.com.ar', body: 'Nota secreta.', created_at: new Date().toISOString(), images: [], internal: true })
    renderPortal('/portal/playinn/reportes/3')
    const thread = await screen.findByRole('list', { name: 'Conversación' })
    expect(within(thread).getAllByRole('listitem')[0]).toHaveTextContent('Tu reporte')
    expect(within(thread).getByText('¿Se puede agregar un campo de DNI', { exact: false })).toBeInTheDocument()
    expect(within(thread).queryByText('Nota secreta.')).not.toBeInTheDocument()
    expect(screen.queryByRole('checkbox')).not.toBeInTheDocument()
  })

  it('no deja adjuntar una cuarta imagen ni mandar un mensaje vacío', async () => {
    const user = userEvent.setup()
    const { api } = mockClientPortal(playinnUser)
    renderPortal('/portal/playinn/reportes/3')
    await screen.findByRole('list', { name: 'Conversación' })

    await user.click(screen.getByRole('button', { name: 'Enviar' }))
    expect(screen.getByRole('alert')).toHaveTextContent('Escribí un mensaje o adjuntá una imagen.')
    expect(api.post).not.toHaveBeenCalled()

    await user.upload(screen.getByLabelText('Imágenes del mensaje'), [png('1.png'), png('2.png'), png('3.png'), png('4.png')])
    expect(screen.getByRole('alert')).toHaveTextContent('Hasta 3 imágenes por mensaje.')
    expect(within(screen.getByRole('list', { name: 'Imágenes adjuntas' })).getAllByRole('listitem')).toHaveLength(3)
    await user.click(screen.getByRole('button', { name: 'Quitar 2.png' }))
    expect(within(screen.getByRole('list', { name: 'Imágenes adjuntas' })).getAllByRole('listitem')).toHaveLength(2)
  })

  it('el cliente ve al equipo como "Equipo nexosmart" y al escribir en uno resuelto lo reabre', async () => {
    const user = userEvent.setup()
    const { state, api } = mockClientPortal(playinnUser)
    renderPortal('/portal/playinn/reportes/2')
    const thread = await screen.findByRole('list', { name: 'Conversación' })
    expect(within(thread).getAllByRole('listitem')[1]).toHaveTextContent('Equipo nexosmart')
    expect(screen.getByText('Lo dimos por resuelto. Si nos escribís, lo volvemos a abrir.')).toBeInTheDocument()
    // La captura del reporte, por la ruta espejo del portal.
    expect(within(thread).getByRole('img', { name: 'Imagen 1 de Tu reporte' })).toHaveAttribute('src', '/api/v1/portal/projects/playinn/reports/2/screenshot')

    await user.type(screen.getByLabelText('Mensaje'), 'Volvió a pasar hoy.')
    await user.upload(screen.getByLabelText('Imágenes del mensaje'), png())
    await user.click(screen.getByRole('button', { name: 'Enviar' }))
    expect(await within(thread).findByText('Volvió a pasar hoy.')).toBeInTheDocument()
    expect(await screen.findByText('En revisión')).toBeInTheDocument()
    expect(state.reports.find((report) => report.id === 2)?.status).toBe('in_review')
    // images[]: Laravel arma la lista solo con corchetes.
    const [url, sent] = api.post.mock.calls.at(-1) as [string, FormData]
    expect(url).toBe('/portal/projects/playinn/reports/2/messages')
    expect(sent.getAll('images[]').map((file) => (file as File).name)).toEqual(['pantalla.png'])
    expect(sent.has('internal')).toBe(false)
  })
})
