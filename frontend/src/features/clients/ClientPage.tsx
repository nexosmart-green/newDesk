import type { ReactNode } from 'react'
import { Link, useParams } from 'react-router'
import { Badge } from '@/components/ui/Badge'
import { PageState } from '@/components/ui/PageState'
import { useCan, useCanAny } from '@/features/auth/useAuth'
import { ProjectStatusBadge } from '@/features/projects/ProjectStatusBadge'
import { ApiError } from '@/lib/api'
import { ClientActions } from './ClientActions'
import { CLIENTS_READ_PERMISSIONS } from './types'
import { useClient } from './useClients'

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="text-sm break-words">{children || '—'}</dd>
    </div>
  )
}

/** RF-02.02 client file: data and its projects (sold vs consumed hours join in E4). */
export function ClientPage() {
  const clientId = Number(useParams().clientId)
  const canRead = useCanAny(CLIENTS_READ_PERMISSIONS)
  const canManage = useCan('clients.manage')
  const client = useClient(clientId)

  if (!canRead) return <p className="text-muted">No tenés acceso a los clientes.</p>
  if (client.error instanceof ApiError && client.error.status === 404) {
    return <h1 className="text-lg font-semibold">Cliente no encontrado</h1>
  }
  if (!client.data) return <PageState isPending={client.isPending} error={client.error} shape="detail" onRetry={() => void client.refetch()} />
  const data = client.data

  return (
    <section className="max-w-4xl space-y-6">
      <Link to="/clientes" className="text-sm text-accent">
        ← Clientes
      </Link>
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-lg font-semibold">{data.display_name}</h1>
            <Badge tone={data.status === 'active' ? 'success' : 'neutral'}>
              {data.status === 'active' ? 'Activo' : 'Inactivo'}
            </Badge>
          </div>
          {data.trade_name && <p className="text-sm text-muted">{data.legal_name}</p>}
        </div>
        {canManage && <ClientActions client={data} />}
      </header>
      <dl className="grid gap-4 rounded-lg border border-border bg-surface p-4 sm:grid-cols-2">
        <Field label="CUIT">{data.tax_id}</Field>
        <Field label="Contacto">{data.contact_name}</Field>
        <Field label="Email">{data.email}</Field>
        <Field label="Teléfono">{data.phone}</Field>
        <div className="sm:col-span-2">
          <Field label="Notas">{data.notes && <span className="whitespace-pre-line">{data.notes}</span>}</Field>
        </div>
      </dl>
      <section aria-labelledby="client-projects" className="space-y-2">
        <h2 id="client-projects" className="font-semibold">
          Proyectos
        </h2>
        {(data.projects ?? []).length === 0 ? (
          <p className="text-sm text-muted">Este cliente todavía no tiene proyectos.</p>
        ) : (
          <ul className="divide-y divide-border rounded-lg border border-border bg-surface">
            {data.projects?.map((project) => (
              <li key={project.id}>
                <Link to={`/proyectos/${project.id}`} className="flex flex-wrap items-center gap-3 p-3 hover:bg-bg">
                  <span className="font-mono text-xs uppercase text-muted">{project.code_prefix}</span>
                  <span className="flex-1 font-medium">{project.name}</span>
                  <ProjectStatusBadge status={project.status} />
                </Link>
              </li>
            ))}
          </ul>
        )}
      </section>
    </section>
  )
}
