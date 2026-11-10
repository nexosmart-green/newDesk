import { createBrowserRouter } from 'react-router'
import { AppLayout } from '@/components/layout/AppLayout'
import { LoginPage } from '@/features/auth/LoginPage'
import { RequireAuth, SECURITY_PATH } from '@/features/auth/RequireAuth'
import { HomePage } from './HomePage'

/**
 * Each feature module adds its routes as children of the authenticated layout. Everything except
 * the login and the Panel (the page people open every morning) is lazy so the main chunk stays small.
 */
export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  {
    path: '/recuperar-contrasena',
    lazy: async () => ({ Component: (await import('@/features/auth/ForgotPasswordPage')).ForgotPasswordPage }),
  },
  {
    path: '/restablecer-contrasena',
    lazy: async () => ({ Component: (await import('@/features/auth/ResetPasswordPage')).ResetPasswordPage }),
  },
  {
    path: '/invitacion/:token',
    lazy: async () => ({ Component: (await import('@/features/auth/InvitationPage')).InvitationPage }),
  },
  {
    element: <RequireAuth />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { index: true, element: <HomePage /> },
          {
            path: SECURITY_PATH,
            lazy: async () => ({ Component: (await import('@/features/security/SecurityPage')).SecurityPage }),
          },
          { path: '/perfil', lazy: async () => ({ Component: (await import('@/features/profile/ProfilePage')).ProfilePage }) },
          {
            path: '/mi-semana',
            lazy: async () => ({ Component: (await import('@/features/timesheet/MyWeekPage')).MyWeekPage }),
          },
          {
            path: '/mis-tareas',
            lazy: async () => ({ Component: (await import('@/features/tasks/MyTasksPage')).MyTasksPage }),
          },
          { path: '/tareas', lazy: async () => ({ Component: (await import('@/features/tasks/AllTasksPage')).AllTasksPage }) },
          { path: '/tareas/:code', lazy: async () => ({ Component: (await import('@/features/tasks/TaskPage')).TaskPage }) },
          {
            path: '/personas',
            lazy: async () => ({ Component: (await import('@/features/people/PeopleDirectoryPage')).PeopleDirectoryPage }),
          },
          {
            path: '/personas/:personId',
            lazy: async () => ({ Component: (await import('@/features/people/PersonPage')).PersonPage }),
          },
          { path: '/clientes', lazy: async () => ({ Component: (await import('@/features/clients/ClientsPage')).ClientsPage }) },
          {
            path: '/clientes/:clientId',
            lazy: async () => ({ Component: (await import('@/features/clients/ClientPage')).ClientPage }),
          },
          {
            path: '/proyectos',
            lazy: async () => ({ Component: (await import('@/features/projects/ProjectsPage')).ProjectsPage }),
          },
          {
            path: '/proyectos/:projectId',
            lazy: async () => ({ Component: (await import('@/features/projects/ProjectPage')).ProjectPage }),
            children: [
              {
                index: true,
                lazy: async () => ({ Component: (await import('@/features/projects/ProjectSummaryTab')).ProjectSummaryTab }),
              },
              {
                path: 'tareas',
                lazy: async () => ({ Component: (await import('@/features/tasks/ProjectTasksTab')).ProjectTasksTab }),
              },
              {
                path: 'horas',
                lazy: async () => ({ Component: (await import('@/features/hours/ProjectHoursTab')).ProjectHoursTab }),
              },
              {
                path: 'miembros',
                lazy: async () => ({ Component: (await import('@/features/projects/ProjectMembersTab')).ProjectMembersTab }),
              },
              {
                path: 'actividad',
                lazy: async () => ({ Component: (await import('@/features/projects/ProjectActivityTab')).ProjectActivityTab }),
              },
            ],
          },
          {
            path: '/calendario',
            lazy: async () => ({ Component: (await import('@/features/calendar/MyCalendarPage')).MyCalendarPage }),
          },
          { path: '/turnos', lazy: async () => ({ Component: (await import('@/features/shifts/ShiftsPage')).ShiftsPage }) },
          {
            path: '/reportes/horas',
            lazy: async () => ({ Component: (await import('@/features/reports/HoursReportPage')).HoursReportPage }),
          },
          {
            path: '/reportes/horas/proyectos/:projectId',
            lazy: async () => ({ Component: (await import('@/features/reports/ProjectHoursReportPage')).ProjectHoursReportPage }),
          },
          {
            path: '/configuracion',
            lazy: async () => ({ Component: (await import('./settingsRoutes')).SettingsLayout }),
            children: [
              { index: true, lazy: async () => ({ Component: (await import('./settingsRoutes')).SettingsIndex }) },
              { path: 'empresa', lazy: async () => ({ Component: (await import('./settingsRoutes')).CompanySettings }) },
              { path: 'roles', lazy: async () => ({ Component: (await import('./settingsRoutes')).RolesSettings }) },
              { path: 'usuarios', lazy: async () => ({ Component: (await import('./settingsRoutes')).UsersSettings }) },
              { path: 'catalogos', lazy: async () => ({ Component: (await import('./settingsRoutes')).CatalogsSettings }) },
              { path: 'feriados', lazy: async () => ({ Component: (await import('./settingsRoutes')).HolidaysSettings }) },
              { path: 'tokens-ia', lazy: async () => ({ Component: (await import('./settingsRoutes')).AiTokensSettings }) },
            ],
          },
        ],
      },
    ],
  },
])
