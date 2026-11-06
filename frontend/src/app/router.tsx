import { createBrowserRouter } from 'react-router'
import { AppLayout } from '@/components/layout/AppLayout'
import { ForgotPasswordPage } from '@/features/auth/ForgotPasswordPage'
import { InvitationPage } from '@/features/auth/InvitationPage'
import { LoginPage } from '@/features/auth/LoginPage'
import { RequireAuth, SECURITY_PATH } from '@/features/auth/RequireAuth'
import { ResetPasswordPage } from '@/features/auth/ResetPasswordPage'
import { CatalogsTab } from '@/features/catalogs/CatalogsTab'
import { ClientPage } from '@/features/clients/ClientPage'
import { ClientsPage } from '@/features/clients/ClientsPage'
import { CompanyTab } from '@/features/company/CompanyTab'
import { PeopleDirectoryPage } from '@/features/people/PeopleDirectoryPage'
import { PersonPage } from '@/features/people/PersonPage'
import { ProfilePage } from '@/features/profile/ProfilePage'
import { ComingSoonTab } from '@/features/projects/ComingSoonTab'
import { ProjectActivityTab } from '@/features/projects/ProjectActivityTab'
import { ProjectMembersTab } from '@/features/projects/ProjectMembersTab'
import { ProjectPage } from '@/features/projects/ProjectPage'
import { ProjectsPage } from '@/features/projects/ProjectsPage'
import { ProjectSummaryTab } from '@/features/projects/ProjectSummaryTab'
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
          { path: '/perfil', element: <ProfilePage /> },
          { path: '/personas', element: <PeopleDirectoryPage /> },
          { path: '/personas/:personId', element: <PersonPage /> },
          { path: '/clientes', element: <ClientsPage /> },
          { path: '/clientes/:clientId', element: <ClientPage /> },
          { path: '/proyectos', element: <ProjectsPage /> },
          {
            path: '/proyectos/:projectId',
            element: <ProjectPage />,
            children: [
              { index: true, element: <ProjectSummaryTab /> },
              { path: 'tareas', element: <ComingSoonTab title="Tareas" /> },
              { path: 'horas', element: <ComingSoonTab title="Horas" /> },
              { path: 'miembros', element: <ProjectMembersTab /> },
              { path: 'actividad', element: <ProjectActivityTab /> },
            ],
          },
          {
            path: '/configuracion',
            element: <SettingsPage />,
            children: [
              { index: true, element: <SettingsIndexRedirect /> },
              { path: 'empresa', element: <SettingsTabGuard path="empresa"><CompanyTab /></SettingsTabGuard> },
              { path: 'roles', element: <SettingsTabGuard path="roles"><RolesTab /></SettingsTabGuard> },
              { path: 'usuarios', element: <SettingsTabGuard path="usuarios"><UsersTab /></SettingsTabGuard> },
              { path: 'catalogos', element: <SettingsTabGuard path="catalogos"><CatalogsTab /></SettingsTabGuard> },
            ],
          },
        ],
      },
    ],
  },
])
