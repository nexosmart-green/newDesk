import { createBrowserRouter } from 'react-router'
import { AppLayout } from '@/components/layout/AppLayout'
import { LoginPage } from '@/features/auth/LoginPage'
import { RequireAuth } from '@/features/auth/RequireAuth'
import { monitoringRoutes } from '@/features/monitoreo/routes'
import { HomePage } from './HomePage'

/** Each feature module adds its routes as children of the authenticated layout. */
export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  {
    element: <RequireAuth />,
    children: [{ element: <AppLayout />, children: [{ index: true, element: <HomePage /> }, monitoringRoutes] }],
  },
])
