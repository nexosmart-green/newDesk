import { AxiosError, AxiosHeaders, type AxiosResponse } from 'axios'
import { ApiError, toApiError } from './api'
import { errorTextOf } from './errorText'

function httpError(status: number, data: unknown): AxiosError {
  const config = { headers: new AxiosHeaders() }
  const response: AxiosResponse = { status, statusText: '', data, headers: {}, config }
  return new AxiosError('Request failed', undefined, config, undefined, response)
}

const textOf = (error: unknown) => errorTextOf(toApiError(error))

describe('errorTextOf', () => {
  afterEach(() => vi.restoreAllMocks())

  it('says the file is too big on a 413, even when the proxy answers with an HTML page', () => {
    expect(textOf(httpError(413, '<html><body>413 Request Entity Too Large</body></html>')).message).toBe(
      'El archivo es demasiado grande para subirlo.',
    )
  })

  it('asks to reload when the body is HTML or empty, never showing a bare "Error"', () => {
    const expected = 'No se pudo completar la acción. Recargá la página y probá de nuevo.'
    expect(textOf(httpError(400, '<!doctype html><title>Bad Request</title>')).message).toBe(expected)
    expect(textOf(httpError(422, '')).message).toBe(expected)
  })

  it('keeps the backend message when there is one', () => {
    expect(textOf(httpError(422, { data: null, message: 'La tarea está cerrada.', errors: null })).message).toBe('La tarea está cerrada.')
  })

  it('tells a timeout apart from a network failure', () => {
    const timeout = new AxiosError('timeout of 30000ms exceeded', AxiosError.ECONNABORTED)
    expect(textOf(timeout).message).toBe('El servidor tardó demasiado en responder. Probá de nuevo.')
  })

  it('says there is no internet when the browser is offline', () => {
    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false)
    const text = textOf(new AxiosError('Network Error', AxiosError.ERR_NETWORK))
    expect(text.message).toBe('Sin conexión a internet. Revisá la red y probá de nuevo.')
  })

  it('explains a lost session and an expired page', () => {
    expect(errorTextOf(new ApiError(401, '')).message).toBe('Tu sesión se cerró. Ingresá de nuevo para seguir.')
    expect(errorTextOf(new ApiError(419, 'CSRF token mismatch.')).message).toBe(
      'Pasó mucho tiempo sin actividad. Recargá la página para seguir.',
    )
  })

  it('titles 404 and 409 by what happened', () => {
    expect(errorTextOf(new ApiError(404, 'La tarea no existe.')).title).toBe('Ya no existe')
    expect(errorTextOf(new ApiError(409, 'Otra persona la editó.')).title).toBe('Cambió mientras lo editabas')
  })
})
