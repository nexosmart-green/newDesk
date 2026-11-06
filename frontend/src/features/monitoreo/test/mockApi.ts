import type { AxiosResponse } from 'axios'
import { api } from '@/lib/api'

// Responde los GET de la API de monitoreo (`/monitoring/<ruta>`, sin query) con el sobre { data }.
// Lo que no está en `routes` falla, como un back que no tiene esa ruta.
export function mockMonitoringGet(routes: Record<string, unknown>): void {
  vi.spyOn(api, 'get').mockImplementation((url: string) => {
    const path = url.replace(/^\/monitoring\//, '').split('?')[0] ?? ''
    if (!(path in routes)) return Promise.reject(new Error(`GET sin mock: ${url}`))
    return Promise.resolve({ data: { data: routes[path], message: null, errors: null } } as AxiosResponse)
  })
}
