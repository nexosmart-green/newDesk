import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import type { ReactElement } from 'react'
import { MemoryRouter } from 'react-router'
import { currentUserKey } from '@/features/auth/useAuth'
import { FloatingReportsProvider } from '../reports/FloatingReports'
import { teamAdmin } from './fixtures/portal'

// admin: un usuario con monitoring.manage, como el admin de monitoreo (las escrituras).
export function renderWithProviders(ui: ReactElement, { route = '/', admin = false }: { route?: string; admin?: boolean } = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  if (admin) client.setQueryData(currentUserKey, teamAdmin)
  return {
    client,
    ...render(
      <QueryClientProvider client={client}>
        <MemoryRouter initialEntries={[route]}>
          {/* Como el layout del módulo: las ventanas flotantes de reportes (D-064). */}
          <FloatingReportsProvider>{ui}</FloatingReportsProvider>
        </MemoryRouter>
      </QueryClientProvider>,
    ),
  }
}
