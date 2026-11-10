import { PageState } from '@/components/ui/PageState'
import { useCan } from '@/features/auth/useAuth'
import { CompanyForm } from './CompanyForm'
import { CompanyLogoUpload } from './CompanyLogoUpload'
import { useCompany } from './useCompany'

export function CompanyTab() {
  const { data: company, isPending, error, refetch } = useCompany()
  const canEdit = useCan('company.manage')

  if (!company) return <PageState isPending={isPending} error={error} shape="detail" onRetry={() => void refetch()} />

  return (
    <div className="space-y-6">
      {!canEdit && <p className="text-sm text-muted">Solo lectura: no tenés permiso para configurar la empresa.</p>}
      <CompanyLogoUpload company={company} canEdit={canEdit} />
      <CompanyForm key={company.id} company={company} canEdit={canEdit} />
    </div>
  )
}
