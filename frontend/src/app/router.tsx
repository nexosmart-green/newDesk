import { createBrowserRouter } from 'react-router'
import { AppLayout } from '@/components/layout/AppLayout'
import { ForgotPasswordPage } from '@/features/auth/ForgotPasswordPage'
import { InvitationPage } from '@/features/auth/InvitationPage'
import { LoginPage } from '@/features/auth/LoginPage'
import { RequireAuth, SECURITY_PATH } from '@/features/auth/RequireAuth'
import { ResetPasswordPage } from '@/features/auth/ResetPasswordPage'
import { CompanyTab } from '@/features/company/CompanyTab'
import { RolesTab } from '@/features/roles/RolesTab'
import { SecurityPage } from '@/features/security/SecurityPage'
import { SettingsIndexRedirect, SettingsPage, SettingsTabGuard } from '@/features/settings/SettingsPage'
import { UsersTab } from '@/features/users/UsersTab'
import { HomePage } from './HomePage'

/** Each feature module adds its routes as children of the authenticated layout. */
export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  { path: '/recuperar-contrasena', element: <ForgotPasswordPage /> },
  { path: '/restablecer-contrasena', element: <ResetPasswordPage /> },
  { path: '/invitacion/:token', element: <InvitationPage /> },
  {
    element: <RequireAuth />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { index: true, element: <HomePage /> },
          { path: SECURITY_PATH, element: <SecurityPage /> },
          {
            path: '/configuracion',
            element: <SettingsPage />,
            children: [
              { index: true, element: <SettingsIndexRedirect /> },
              { path: 'empresa', element: <SettingsTabGuard path="empresa"><CompanyTab /></SettingsTabGuard> },
              { path: 'roles', element: <SettingsTabGuard path="roles"><RolesTab /></SettingsTabGuard> },
              { path: 'usuarios', element: <SettingsTabGuard path="usuarios"><UsersTab /></SettingsTabGuard> },
            ],
          },
        ],
      },
    ],
  },
])
