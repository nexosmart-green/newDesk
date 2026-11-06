import { Suspense, type ReactNode } from 'react'
import { Link, Navigate, Outlet, useLocation, useParams } from 'react-router'
import { useCurrentUser } from '@/features/auth/useAuth'
import { ApiError } from '@/lib/api'
import { errorMessage } from '../api/monitoringApi'
import { portalProjectPath, usePortalLogout, usePortalProject, usePortalProjects } from '../api/usePortal'
import { Icon } from '../components/Icon'
import { Empty, ErrorNote, Spinner } from '../components/Panel'
import { Badge } from '../components/Status'
import { Segmented } from '../components/Tabs'
import { brandStyle } from '../lib/brand'
import { portalStateMeta } from '../lib/status'
import { usePortalTheme } from '../lib/theme'
import { usePortalEvents } from '../realtime/usePortalEvents'
import { PortalStatusView, ProjectLogo } from './PortalStatusView'

// Un usuario de cliente solo ve /portal (D-037): el equipo que cae acá vuelve a lo suyo (RequireAuth hace lo inverso).
export function RequireClient({ children }: { children: ReactNode }) {
  const me = useCurrentUser()
  const location = useLocation()

  if (me.isPending) {
    return (
      <div className="grid min-h-dvh place-items-center">
        <Spinner label="Verificando sesión…" />
      </div>
    )
  }
  if (me.isError) {
    return (
      <div className="mx-auto grid min-h-dvh max-w-md place-items-center p-8">
        <ErrorNote onRetry={() => void me.refetch()}>No se pudo contactar al panel. Reintentá en unos segundos.</ErrorNote>
      </div>
    )
  }
  if (!me.data) return <Navigate to="/portal/login" replace state={{ from: location.pathname }} />
  if (!me.data.client) return <Navigate to="/" replace />
  return <>{children}</>
}

const themeOptions = [
  { value: 'auto', label: 'Automático', icon: 'display' },
  { value: 'light', label: 'Claro', icon: 'sun' },
  { value: 'dark', label: 'Oscuro', icon: 'moon' },
] as const

// Portal del cliente: sin sidebar ni datos internos, con la marca de cada proyecto.
export function PortalLayout() {
  const me = useCurrentUser()
  const logout = usePortalLogout()
  const [theme, setTheme] = usePortalTheme()
  // Los reportes y sus mensajes llegan en vivo.
  usePortalEvents()
  return (
    <div className="flex min-h-dvh flex-col">
      <header className="glass sticky top-0 z-20 border-b border-line">
        <div className="mx-auto flex h-14 w-full max-w-4xl items-center gap-3 px-5">
          <Link to="/portal" className="truncate text-headline font-semibold">
            {me.data?.client?.name ?? 'Estado de tus servicios'}
          </Link>
          <span className="flex-1" />
          <span className="hidden truncate text-xs text-muted sm:inline">{me.data?.email}</span>
          <button type="button" className="btn btn-ghost btn-sm" onClick={() => logout.mutate()} disabled={logout.isPending}>
            {logout.isPending ? <Spinner /> : <Icon name="logout" size={14} />}
            Salir
          </button>
        </div>
      </header>
      <main id="main" className="mx-auto w-full max-w-4xl flex-1 px-5 pt-8 pb-16 sm:pt-10">
        <Suspense fallback={<Spinner label="Cargando…" />}>
          <Outlet />
        </Suspense>
      </main>
      {/* El selector de tema de monitoreo (en el panel iba en la barra lateral, que el portal no tiene). */}
      <footer className="flex flex-col items-center gap-3 pb-8 text-xs text-faint">
        <Segmented label="Tema" options={themeOptions} value={theme} onChange={setTheme} />
        Monitoreo de nexosmart
      </footer>
    </div>
  )
}

export function PortalHome() {
  const projects = usePortalProjects()
  if (projects.isPending) return <Spinner label="Cargando tus proyectos…" />
  if (projects.isError) return <ErrorNote onRetry={() => void projects.refetch()}>{errorMessage(projects.error)}</ErrorNote>
  const [only] = projects.data
  if (projects.data.length === 1 && only) return <Navigate to={`/portal/${only.slug}`} replace />
  if (projects.data.length === 0) return <Empty icon="servers">Todavía no hay proyectos para mostrar.</Empty>

  return (
    <div className="rise space-y-5">
      <h1 className="title">Tus proyectos</h1>
      <ul className="grid gap-4 sm:grid-cols-2">
        {projects.data.map((project) => {
          const meta = portalStateMeta[project.status]
          return (
            <li key={project.slug} style={brandStyle(project.primary_color)}>
              <Link to={`/portal/${project.slug}`} className="panel flex items-center gap-4 p-5 transition-shadow hover:shadow-[var(--shadow-pop)]">
                <ProjectLogo name={project.name} logoUrl={project.logo_url} className="size-11 rounded-xl text-lg" />
                <div className="min-w-0 flex-1">
                  <div className="truncate font-semibold">{project.name}</div>
                  <div className="truncate text-sm text-muted">{project.phrase}</div>
                </div>
                <div className="flex flex-col items-end gap-1.5">
                  <Badge tone={meta.tone}>{meta.label}</Badge>
                  {project.incident && <Badge tone="warn">En resolución</Badge>}
                </div>
              </Link>
            </li>
          )
        })}
      </ul>
    </div>
  )
}

export function PortalProjectPage() {
  const { slug = '' } = useParams()
  const status = usePortalProject(slug)
  const projects = usePortalProjects()
  const several = (projects.data?.length ?? 0) > 1

  if (status.isPending) return <Spinner label="Cargando el estado…" />
  if (status.error instanceof ApiError && status.error.status === 404) {
    return (
      <div className="space-y-4 text-center">
        <Empty icon="alert">No encontramos ese proyecto.</Empty>
        <Link to="/portal" className="btn">Ver mis proyectos</Link>
      </div>
    )
  }
  if (status.isError) return <ErrorNote onRetry={() => void status.refetch()}>{errorMessage(status.error)}</ErrorNote>

  return (
    <div className="rise space-y-4">
      {several && (
        <Link to="/portal" className="inline-flex items-center gap-1 text-sm text-muted hover:text-text">
          <Icon name="chevron" size={12} className="rotate-180" />
          Tus proyectos
        </Link>
      )}
      <PortalStatusView
        status={status.data}
        responseTimesPath={portalProjectPath(slug)}
        actions={
          <>
            <Link to={`/portal/${slug}/reportar`} state={{ from: window.location.href }} className="btn btn-primary flex-1 sm:flex-none">
              <Icon name="alert" size={14} />
              Reportar un problema
            </Link>
            <Link to={`/portal/${slug}/reportes`} className="btn flex-1 sm:flex-none">Mis reportes</Link>
          </>
        }
      />
    </div>
  )
}
