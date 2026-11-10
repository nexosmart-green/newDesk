import { QueryClientProvider, type QueryClient } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import type { ReactElement } from 'react'
import { createMemoryRouter, RouterProvider, type RouteObject } from 'react-router'
import { Toaster } from '@/components/ui/toast/Toaster'
import { ALL_MODULES, type ModulePermission } from '@/features/auth/modules'
import type { CurrentUser } from '@/features/auth/types'
import { createQueryClient } from '@/lib/queryClient'

/** Same caches (toasts, 2FA handling) as the app, without retries. */
export function createTestQueryClient(): QueryClient {
  return createQueryClient({ retry: false })
}

/** Renders `routes` in a memory router so redirects/navigation can be asserted via `LocationProbe`. */
export function renderRoutes(routes: RouteObject[], initialPath: string, client = createTestQueryClient()) {
  const router = createMemoryRouter(routes, { initialEntries: [initialPath] })
  const result = render(
    <QueryClientProvider client={client}>
      <RouterProvider router={router} />
      <Toaster />
    </QueryClientProvider>,
  )
  return { ...result, router, client }
}

export function renderAt(element: ReactElement, path = '/', client?: QueryClient) {
  return renderRoutes([{ path, element }], path, client)
}

/** `modules` defaults to every module switch (what existing roles got); pass fewer to test a disabled one. */
export function buildUser(overrides: Partial<CurrentUser> = {}, modules: ModulePermission[] = ALL_MODULES): CurrentUser {
  return {
    id: 1,
    first_name: 'Ana',
    last_name: 'García',
    full_name: 'Ana García',
    email: 'ana@nexosmart.com',
    locale: 'es',
    timezone: 'America/Argentina/Buenos_Aires',
    role: { id: 1, name: 'Admin' },
    two_factor: { enabled: false, required: false },
    company: { id: 1, name: 'NexoSmart', currency: 'ARS', timezone: 'America/Argentina/Buenos_Aires' },
    ...overrides,
    permissions: [...modules, ...(overrides.permissions ?? [])],
  }
}
