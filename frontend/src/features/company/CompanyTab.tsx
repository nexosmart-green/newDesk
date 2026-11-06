import { ErrorAlert } from '@/components/ui/ErrorAlert'
import { useCan } from '@/features/auth/useAuth'
import { CompanyForm } from './CompanyForm'
import { CompanyLogoUpload } from './CompanyLogoUpload'
import { useCompany } from './useCompany'

export function CompanyTab() {
  const { data: company, isPending, error } = useCompany()
  const canEdit = useCan('company.manage')

  if (isPending) return <p className="text-muted">Cargando…</p>
  if (!company) return <ErrorAlert error={error} showFieldErrors />

  return (
    <div className="space-y-6">
      {!canEdit && <p className="text-sm text-muted">Solo lectura: no tenés permiso para configurar la empresa.</p>}
      <CompanyLogoUpload company={company} canEdit={canEdit} />
      <CompanyForm key={company.id} company={company} canEdit={canEdit} />
    </div>
  )
}
