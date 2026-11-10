import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import type { ReactElement } from 'react'
import { createMemoryRouter, RouterProvider, type RouteObject } from 'react-router'
import type { CurrentUser } from '@/features/auth/types'

export function createTestQueryClient(): QueryClient {
  return new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
}

/** Renders `routes` in a memory router so redirects/navigation can be asserted via `LocationProbe`. */
export function renderRoutes(routes: RouteObject[], initialPath: string, client = createTestQueryClient()) {
  const router = createMemoryRouter(routes, { initialEntries: [initialPath] })
  const result = render(
    <QueryClientProvider client={client}>
      <RouterProvider router={router} />
    </QueryClientProvider>,
  )
  return { ...result, router, client }
}

export function renderAt(element: ReactElement, path = '/') {
  return renderRoutes([{ path, element }], path)
}

export function buildUser(overrides: Partial<CurrentUser> = {}): CurrentUser {
  return {
    id: 1,
    first_name: 'Ana',
    last_name: 'García',
    full_name: 'Ana García',
    email: 'ana@nexosmart.com',
    locale: 'es',
    timezone: 'America/Argentina/Buenos_Aires',
    role: { id: 1, name: 'Admin' },
    permissions: [],
    two_factor: { enabled: false, required: false },
    company: { id: 1, name: 'NexoSmart', currency: 'ARS', timezone: 'America/Argentina/Buenos_Aires' },
    client: null,
    ...overrides,
  }
}
