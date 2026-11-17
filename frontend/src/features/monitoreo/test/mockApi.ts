import { AxiosError, type AxiosResponse } from 'axios'
import { api } from '@/lib/api'
import type { PageMeta } from '../api/types'

// Un back de mentira para la API de monitoreo (`/monitoring/<ruta>`, `/portal/<ruta>`), sobre la instancia axios de newDesk.
// Las claves son 'GET portal-admin/projects/:id' (sin método = GET); el valor es el dato o una función que lo arma
// (o un reply() con otro estado). Lo que no está en `routes` falla, como un back que no tiene esa ruta.

type Method = 'GET' | 'POST' | 'PUT' | 'DELETE'
export interface RequestContext { body: unknown; query: URLSearchParams; params: Record<string, string> }
export type Routes = Record<string, ((ctx: RequestContext) => unknown) | object | string | number | boolean | null>

const REPLY = Symbol('reply')
interface Reply { [REPLY]: true; status: number; data: unknown; message: string | null; errors: Record<string, string[]> | null; meta?: PageMeta }

export function reply({ status = 200, data = null, message = null, errors = null, meta }: Partial<Omit<Reply, typeof REPLY>>): Reply {
  return { [REPLY]: true, status, data, message, errors, meta }
}

const isReply = (value: unknown): value is Reply => typeof value === 'object' && value !== null && REPLY in value

function match(routes: Routes, method: Method, path: string): { value: unknown; params: Record<string, string> } | null {
  const segments = path.split('/')
  for (const [key, value] of Object.entries(routes)) {
    const [keyMethod, pattern = ''] = key.includes(' ') ? key.split(' ') : ['GET', key]
    const parts = pattern.split('/')
    if (keyMethod !== method || parts.length !== segments.length) continue
    const params: Record<string, string> = {}
    if (parts.every((part, index) => (part.startsWith(':') ? ((params[part.slice(1)] = segments[index] ?? ''), true) : part === segments[index]))) {
      return { value, params }
    }
  }
  return null
}

export function mockMonitoringApi(routes: Routes) {
  const handle = (method: Method) => (url: string, body?: unknown): Promise<AxiosResponse> => {
    // '/monitoring/servers' → 'servers'; las del portal y las de newDesk solo pierden la barra ('portal/projects', 'me').
    const [path = '', search = ''] = url.replace(/^\/(monitoring\/)?/, '').split('?')
    const found = match(routes, method, path)
    if (!found) return Promise.reject(new Error(`${method} sin mock: ${url}`))
    const result = typeof found.value === 'function' ? found.value({ body, query: new URLSearchParams(search), params: found.params }) : found.value
    const out = isReply(result) ? result : reply({ data: result })
    const envelope = { data: out.data, message: out.message, errors: out.errors, ...(out.meta && { meta: out.meta }) }
    if (out.status >= 400) {
      return Promise.reject(new AxiosError(out.message ?? 'error', 'ERR_BAD_RESPONSE', undefined, undefined, { status: out.status, data: envelope } as AxiosResponse))
    }
    return Promise.resolve({ status: out.status, data: envelope } as AxiosResponse)
  }
  return {
    get: vi.spyOn(api, 'get').mockImplementation(handle('GET')),
    post: vi.spyOn(api, 'post').mockImplementation(handle('POST')),
    put: vi.spyOn(api, 'put').mockImplementation(handle('PUT')),
    delete: vi.spyOn(api, 'delete').mockImplementation(handle('DELETE')),
  }
}

// Solo GET, con el sobre { data } (los tests de la fase 3).
export function mockMonitoringGet(routes: Record<string, unknown>): void {
  mockMonitoringApi(Object.fromEntries(Object.entries(routes).map(([path, data]) => [`GET ${path}`, () => data])))
}
