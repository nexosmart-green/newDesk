import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { useCan } from '@/features/auth/useAuth'
import { errorMessage, fieldError } from '../api/monitoringApi'
import { useClient } from '../api/usePortalAdmin'
import { useClientMutations } from '../api/useProjectMutations'
import type { ProjectRow, User } from '../api/types'
import { DataTable } from '../components/DataTable'
import { Icon } from '../components/Icon'
import { ErrorNote, Panel, Spinner } from '../components/Panel'
import { Badge } from '../components/Status'
import { formatRelative } from '../lib/format'
import { portalStateMeta } from '../lib/status'
import { ConfirmButton } from '../settings/ConfirmButton'
import { NewProjectForm } from './NewProjectForm'

export function BackLink({ to, children }: { to: string; children: string }) {
  return (
    <Link to={to} className="inline-flex items-center gap-1 text-sm text-muted hover:text-text">
      <Icon name="chevron" size={12} className="rotate-180" />
      {children}
    </Link>
  )
}

export function ClientPage() {
  const id = Number(useParams().id)
  const detail = useClient(id)
  const isAdmin = useCan('monitoring.manage')
  const navigate = useNavigate()
  const mutations = useClientMutations()
  const [renaming, setRenaming] = useState<string | null>(null)
  const [email, setEmail] = useState('')

  if (detail.isPending) return <Spinner label="Cargando el cliente…" />
  if (detail.isError) return <ErrorNote onRetry={() => void detail.refetch()}>{errorMessage(detail.error)}</ErrorNote>
  const { client, users, projects } = detail.data

  return (
    <div className="rise space-y-5">
      <BackLink to="/monitoreo/clientes">Clientes</BackLink>
      <div className="flex flex-wrap items-center gap-3">
        {renaming === null ? (
          <h1 className="title">{client.name}</h1>
        ) : (
          <form
            className="flex flex-wrap items-center gap-2"
            onSubmit={(event) => {
              event.preventDefault()
              mutations.update.mutate({ id, patch: { name: renaming.trim() } }, { onSuccess: () => setRenaming(null) })
            }}
          >
            <label>
              <span className="sr-only">Nombre del cliente</span>
              <input className="input w-64" value={renaming} onChange={(e) => setRenaming(e.target.value)} autoFocus />
            </label>
            <button type="submit" className="btn btn-primary btn-sm" disabled={!renaming.trim() || mutations.update.isPending}>Guardar</button>
            <button type="button" className="btn btn-sm" onClick={() => setRenaming(null)}>Cancelar</button>
          </form>
        )}
        <Badge tone={client.active ? 'ok' : 'idle'}>{client.active ? 'Activo' : 'Inactivo'}</Badge>
        {isAdmin && renaming === null && (
          <div className="ml-auto flex flex-wrap gap-2">
            <button type="button" className="btn btn-sm" onClick={() => setRenaming(client.name)}>Renombrar</button>
            <button type="button" className="btn btn-sm" onClick={() => mutations.update.mutate({ id, patch: { active: !client.active } })}>
              {client.active ? 'Desactivar' : 'Activar'}
            </button>
            <ConfirmButton label="Eliminar cliente" confirmLabel="¿Borrar el cliente y sus usuarios?" onConfirm={() => mutations.remove.mutate(id, { onSuccess: () => navigate('/monitoreo/clientes') })} />
          </div>
        )}
      </div>
      {fieldError(mutations.update.error, 'name') && <ErrorNote>{fieldError(mutations.update.error, 'name')}</ErrorNote>}
      {/* Un cliente con proyectos no se borra (422): el back dice que se inactive. */}
      {mutations.remove.isError && <ErrorNote>{errorMessage(mutations.remove.error)}</ErrorNote>}
      {!client.active && <p className="text-sm text-muted">Inactivo: sus usuarios no pueden entrar al portal.</p>}

      <Panel title={`Proyectos · ${projects.length}`} bodyClassName="">
        {isAdmin && <NewProjectForm clientId={id} />}
        <DataTable<ProjectRow>
          noun="proyectos"
          rows={projects}
          rowKey={(row) => String(row.id)}
          rowClassName={(row) => (row.active ? '' : 'opacity-60')}
          empty="Este cliente todavía no tiene proyectos."
          columns={[
            { header: 'Proyecto', cell: (row) => <Link to={`/monitoreo/clientes/${id}/proyectos/${row.id}`} className="link font-medium">{row.name}</Link> },
            { header: 'Dirección', cell: (row) => <span className="font-mono text-xs text-muted">/portal/{row.slug}</span> },
            { header: 'Estado', cell: (row) => <Badge tone={portalStateMeta[row.status].tone}>{portalStateMeta[row.status].label}</Badge> },
            { header: 'Componentes', align: 'right', cell: (row) => <span className="num">{row.components}</span> },
            { header: 'Visible', cell: (row) => <span className="text-xs text-muted">{row.active ? 'Sí' : 'Oculto'}</span> },
          ]}
        />
      </Panel>

      <Panel title={`Usuarios · ${users.length}`} bodyClassName="">
        {isAdmin && (
          <form
            className="flex flex-wrap items-end gap-3 px-5 pb-4"
            onSubmit={(event) => {
              event.preventDefault()
              mutations.addUser.mutate({ clientId: id, email: email.trim().toLowerCase() }, { onSuccess: () => setEmail('') })
            }}
          >
            <label className="min-w-[240px] flex-1">
              <span className="label mb-1.5 block">Mail del usuario</span>
              <input className="input" type="email" value={email} onChange={(e) => setEmail(e.target.value)} placeholder="dueño@cliente.com" />
            </label>
            <button type="submit" className="btn btn-primary h-10" disabled={!email.includes('@') || mutations.addUser.isPending}>
              {mutations.addUser.isPending && <Spinner />}
              Agregar usuario
            </button>
          </form>
        )}
        {fieldError(mutations.addUser.error, 'email') && <div className="px-5 pb-4"><ErrorNote>{fieldError(mutations.addUser.error, 'email')}</ErrorNote></div>}
        <DataTable<User>
          noun="usuarios"
          rows={users}
          rowKey={(row) => String(row.id)}
          rowClassName={(row) => (row.active ? '' : 'opacity-50')}
          empty="Sin usuarios: nadie de este cliente puede entrar todavía."
          columns={[
            { header: 'Mail', cell: (row) => row.email },
            { header: 'Estado', cell: (row) => <Badge tone={row.active ? 'ok' : 'idle'}>{row.active ? 'Activo' : 'Inactivo'}</Badge> },
            { header: 'Último ingreso', cell: (row) => <span className="num text-xs text-muted">{formatRelative(row.last_login_at)}</span> },
            ...(isAdmin
              ? [{
                  header: 'Acciones',
                  cell: (row: User) => (
                    <div className="flex flex-wrap gap-1.5">
                      <button type="button" className="btn btn-sm" onClick={() => mutations.updateUser.mutate({ clientId: id, userId: row.id, active: !row.active })}>
                        {row.active ? 'Desactivar' : 'Activar'}
                      </button>
                      <ConfirmButton label="Eliminar" confirmLabel="Confirmar" onConfirm={() => mutations.removeUser.mutate({ clientId: id, userId: row.id })} />
                    </div>
                  ),
                }]
              : []),
          ]}
        />
        <p className="border-t border-line px-5 py-3 text-xs text-muted">Entran con su mail y un código, sin autenticador. Ven solo los proyectos de este cliente.</p>
      </Panel>
    </div>
  )
}
