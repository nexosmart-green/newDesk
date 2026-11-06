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
