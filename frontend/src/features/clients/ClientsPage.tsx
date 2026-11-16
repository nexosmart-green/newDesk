import { useState } from 'react'
import { Building2 } from 'lucide-react'
import { Link, useNavigate } from 'react-router'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { PageState } from '@/components/ui/PageState'
import { Pagination } from '@/components/ui/Pagination'
import { StaleContent } from '@/components/ui/StaleContent'
import { EmptyState } from '@/components/ui/EmptyState'
import { FilterBar } from '@/components/ui/filters/FilterBar'
import { FilterSelect } from '@/components/ui/filters/FilterSelect'
import { SearchInput } from '@/components/ui/filters/SearchInput'
import { PageHeader } from '@/components/ui/PageHeader'
import { useCan, useCanAny } from '@/features/auth/useAuth'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import { ClientFormDialog } from './ClientFormDialog'
import { clientStatusLabels, CLIENTS_READ_PERMISSIONS, type ClientFilters, type ClientStatus } from './types'
import { useClients } from './useClients'

const statuses = Object.keys(clientStatusLabels) as ClientStatus[]
const statusOptions = [{ value: '', label: 'Todos' }, ...statuses.map((status) => ({ value: status, label: clientStatusLabels[status] }))]

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
    <section className="max-w-5xl space-y-4">
      <PageHeader title="Clientes" actions={canManage && <Button onClick={() => setCreating(true)}>Nuevo cliente</Button>} />
      <FilterBar
        active={filters.status !== 'active' ? 1 : 0}
        search={<SearchInput label="Buscar clientes por razón social, nombre o CUIT" placeholder="Razón social, nombre o CUIT" value={filters.search} onChange={(search) => change({ search })} />}
      >
        <FilterSelect
          label="Estado"
          value={filters.status}
          options={statusOptions}
          onChange={(value) => change({ status: statuses.find((status) => status === value) ?? '' })}
        />
      </FilterBar>
      {!clients.data ? (
        <PageState isPending={clients.isPending} error={clients.error} onRetry={() => void clients.refetch()} />
      ) : (
        <StaleContent stale={clients.isPlaceholderData} className="space-y-4">
          {clients.data.data.length === 0 ? (
            <EmptyState icon={Building2}>No hay clientes que coincidan con la búsqueda.</EmptyState>
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
          <Pagination meta={clients.data.meta} onPageChange={(page) => change({ page })} />
        </StaleContent>
      )}
      {creating && (
        <ClientFormDialog client={null} onClose={() => setCreating(false)} onSaved={(client) => navigate(`/clientes/${client.id}`)} />
      )}
    </section>
  )
}
