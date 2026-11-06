import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { PageState } from '@/components/ui/PageState'
import { Pagination } from '@/components/ui/Pagination'
import { SelectField } from '@/components/ui/SelectField'
import { TextField } from '@/components/ui/TextField'
import { useCan, useCanAny } from '@/features/auth/useAuth'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import { ClientFormDialog } from './ClientFormDialog'
import { clientStatusLabels, CLIENTS_READ_PERMISSIONS, type ClientFilters, type ClientStatus } from './types'
import { useClients } from './useClients'

const statuses = Object.keys(clientStatusLabels) as ClientStatus[]

export function ClientsPage() {
  const canRead = useCanAny(CLIENTS_READ_PERMISSIONS)
  const canManage = useCan('clients.manage')
  const navigate = useNavigate()
  const [filters, setFilters] = useState<ClientFilters>({ status: 'active', search: '', page: 1 })
  const [creating, setCreating] = useState(false)
  const debouncedSearch = useDebouncedValue(filters.search)
  const clients = useClients({ ...filters, search: debouncedSearch }, canRead)

  if (!canRead) return <p className="text-muted">No tenés acceso a los clientes.</p>

  const change = (changes: Partial<ClientFilters>) => setFilters((current) => ({ ...current, ...changes, page: changes.page ?? 1 }))

  return (
    <section className="mx-auto max-w-5xl space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h1 className="text-lg font-semibold">Clientes</h1>
        {canManage && <Button onClick={() => setCreating(true)}>Nuevo cliente</Button>}
      </div>
      <div className="grid gap-3 sm:grid-cols-[1fr_12rem]">
        <TextField
          label="Buscar"
          type="search"
          placeholder="Razón social, nombre o CUIT"
          value={filters.search}
          onChange={(event) => change({ search: event.target.value })}
        />
        <SelectField
          label="Estado"
          value={filters.status}
          onChange={(event) => change({ status: statuses.find((status) => status === event.target.value) ?? '' })}
        >
          <option value="">Todos</option>
          {statuses.map((status) => (
            <option key={status} value={status}>
              {clientStatusLabels[status]}
            </option>
          ))}
        </SelectField>
      </div>
      {!clients.data ? (
        <PageState isPending={clients.isPending} error={clients.error} />
      ) : clients.data.data.length === 0 ? (
        <p className="text-sm text-muted">No hay clientes que coincidan con la búsqueda.</p>
      ) : (
        <ul className="divide-y divide-border rounded-lg border border-border bg-surface" aria-label="Clientes">
          {clients.data.data.map((client) => (
            <li key={client.id}>
              <Link to={`/clientes/${client.id}`} className="flex flex-wrap items-center gap-x-3 gap-y-1 p-3 hover:bg-bg">
                <span className="flex-1 font-medium">{client.display_name}</span>
                {client.status === 'inactive' && <Badge>Inactivo</Badge>}
                <span className="text-sm text-muted">
                  {client.projects_count ?? 0} {client.projects_count === 1 ? 'proyecto' : 'proyectos'}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}
      {clients.data && <Pagination meta={clients.data.meta} onPageChange={(page) => change({ page })} />}
      {creating && (
        <ClientFormDialog client={null} onClose={() => setCreating(false)} onSaved={(client) => navigate(`/clientes/${client.id}`)} />
      )}
    </section>
  )
}
