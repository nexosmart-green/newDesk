import type { RouteObject } from 'react-router'

// Todo el módulo va en chunks aparte (uPlot, Echo e Inter solo se bajan al entrar a Monitoreo).
export const monitoringRoutes: RouteObject = {
  path: 'monitoreo',
  lazy: () => import('./MonitoringLayout').then((m) => ({ Component: m.MonitoringLayout })),
  hydrateFallbackElement: <p className="text-muted">Cargando…</p>,
  children: [
    { index: true, lazy: () => import('./dashboard/DashboardPage').then((m) => ({ Component: m.DashboardPage })) },
    { path: 'servers/:id/:tab?', lazy: () => import('./server/ServerPage').then((m) => ({ Component: m.ServerPage })) },
    { path: 'alerts', lazy: () => import('./alerts/AlertsPage').then((m) => ({ Component: m.AlertsPage })) },
    { path: 'settings/:tab?', lazy: () => import('./settings/SettingsPage').then((m) => ({ Component: m.SettingsPage })) },
    { path: 'proyectos', lazy: () => import('./clients/ProjectsPage').then((m) => ({ Component: m.ProjectsPage })) },
    { path: 'clientes', lazy: () => import('./clients/ClientsPage').then((m) => ({ Component: m.ClientsPage })) },
    { path: 'clientes/:id', lazy: () => import('./clients/ClientPage').then((m) => ({ Component: m.ClientPage })) },
    { path: 'clientes/:id/proyectos/:pid', lazy: () => import('./clients/ProjectAdminPage').then((m) => ({ Component: m.ProjectAdminPage })) },
    { path: 'reportes', lazy: () => import('./reports/ReportsPage').then((m) => ({ Component: m.ReportsPage })) },
    { path: 'reportes/:id', lazy: () => import('./reports/ReportPage').then((m) => ({ Component: m.ReportPage })) },
  ],
}

// El portal del cliente (RF-13.07/08): fuera del layout de newDesk, en sus propios chunks. El login es público; el resto,
// solo para usuarios de un cliente (RequireClient). Un proyecto con slug "login" quedaría tapado por el login.
export const portalRoutes: RouteObject = {
  path: 'portal',
  lazy: () => import('./portal/PortalRoot').then((m) => ({ Component: m.PortalRoot })),
  hydrateFallbackElement: <p>Cargando…</p>,
  children: [
    { path: 'login', lazy: () => import('./portal/login/PortalLoginPage').then((m) => ({ Component: m.PortalLoginPage })) },
    {
      lazy: () =>
        import('./portal/PortalPages').then(({ RequireClient, PortalLayout }) => ({
          element: (
            <RequireClient>
              <PortalLayout />
            </RequireClient>
          ),
        })),
      children: [
        { index: true, lazy: () => import('./portal/PortalPages').then((m) => ({ Component: m.PortalHome })) },
        { path: ':slug', lazy: () => import('./portal/PortalPages').then((m) => ({ Component: m.PortalProjectPage })) },
        { path: ':slug/reportar', lazy: () => import('./portal/ReportPages').then((m) => ({ Component: m.PortalReportPage })) },
        { path: ':slug/reportes', lazy: () => import('./portal/ReportPages').then((m) => ({ Component: m.PortalReportsPage })) },
        { path: ':slug/reportes/:id', lazy: () => import('./portal/ReportPages').then((m) => ({ Component: m.PortalReportDetailPage })) },
      ],
    },
  ],
}
