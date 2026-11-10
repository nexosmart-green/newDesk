import { Link, useParams } from 'react-router'
import { useCan } from '@/features/auth/useAuth'
import { errorMessage } from '../api/monitoringApi'
import { useAdminProject, useProjectPreview } from '../api/usePortalAdmin'
import { ErrorNote, Panel, Spinner } from '../components/Panel'
import { Badge } from '../components/Status'
import { portalStateMeta } from '../lib/status'
import { PortalStatusView } from '../portal/PortalStatusView'
import { BackLink } from './ClientPage'
import { ComponentsPanel } from './ComponentsPanel'
import { LogoPanel } from './LogoPanel'
import { FoldersPanel } from './FoldersPanel'
import { ProjectForm } from './ProjectForm'
import { IncidentsAdminPanel } from './ProjectHistory'
import { VulnerabilitiesPanel } from './ProjectVulnerabilities'
import { SuggestionsPanel } from './SuggestionsPanel'

export function ProjectAdminPage() {
  const params = useParams()
  const projectId = Number(params.pid)
  const project = useAdminProject(projectId)
  const isAdmin = useCan('monitoring.manage')

  if (project.isPending) return <Spinner label="Cargando el proyecto…" />
  if (project.isError) return <ErrorNote onRetry={() => void project.refetch()}>{errorMessage(project.error)}</ErrorNote>
  const data = project.data
  const meta = portalStateMeta[data.status]

  return (
    <div className="rise space-y-5">
      <BackLink to="/monitoreo/proyectos">Proyectos</BackLink>
      <div className="flex flex-wrap items-center gap-3">
        <h1 className="title">{data.name}</h1>
        <Badge tone={meta.tone}>{meta.label}</Badge>
        {!data.active && <span className="tag">oculto para el cliente</span>}
        <span className="text-sm text-muted">
          Cliente: <Link to={`/monitoreo/clientes/${data.client_id}`} className="link">{data.client_name}</Link>
        </span>
      </div>

      <div className="grid gap-5 xl:grid-cols-[1fr_20rem]">
        <ProjectForm key={data.id} project={data} readOnly={!isAdmin} />
        <LogoPanel project={data} readOnly={!isAdmin} />
      </div>
      {/* Primero las carpetas del cliente; de ellas salen las sugerencias de qué chequear (D-044). */}
      <FoldersPanel project={data} readOnly={!isAdmin} />
      <VulnerabilitiesPanel project={data} />
      {isAdmin && <SuggestionsPanel project={data} />}
      <ComponentsPanel project={data} readOnly={!isAdmin} />
      <IncidentsAdminPanel projectId={projectId} readOnly={!isAdmin} />
      <PreviewPanel projectId={projectId} slug={data.slug} />
    </div>
  )
}

// Lo mismo que ve el cliente en /portal/<slug>, con los datos de ahora.
function PreviewPanel({ projectId, slug }: { projectId: number; slug: string }) {
  const preview = useProjectPreview(projectId)
  return (
    <Panel title="Vista previa" actions={<span className="text-xs text-muted">Así lo ve el cliente en /portal/{slug}</span>}>
      {preview.isPending && <Spinner label="Armando la vista previa…" />}
      {preview.isError && <ErrorNote onRetry={() => void preview.refetch()}>{errorMessage(preview.error)}</ErrorNote>}
      {preview.data && (
        <div className="rounded-2xl bg-bg p-4 sm:p-6">
          <PortalStatusView status={preview.data} headingLevel={2} responseTimesPath={`portal-admin/projects/${projectId}`} />
        </div>
      )}
    </Panel>
  )
}
