import { useId, useState, type ChangeEvent } from 'react'
import { LoaderCircle } from 'lucide-react'
import { fieldErrorOf } from '@/lib/api'
import type { CompanyProfile } from './types'
import { useUploadCompanyLogo } from './useCompany'

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp']
const MAX_BYTES = 2 * 1024 * 1024

interface CompanyLogoUploadProps {
  company: CompanyProfile
  canEdit: boolean
}

export function CompanyLogoUpload({ company, canEdit }: CompanyLogoUploadProps) {
  const hintId = useId()
  const mutation = useUploadCompanyLogo()
  const [localError, setLocalError] = useState<string | null>(null)

  function handleFile(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    // Early feedback only; the backend validates again.
    if (!ACCEPTED_TYPES.includes(file.type)) return setLocalError('El logo tiene que ser JPG, PNG o WebP.')
    if (file.size > MAX_BYTES) return setLocalError('El logo no puede pesar más de 2 MB.')
    setLocalError(null)
    mutation.mutate(file)
  }

  const error = localError ?? fieldErrorOf(mutation.error, 'logo')

  return (
    <div className="flex flex-wrap items-center gap-4">
      <div className="flex size-20 items-center justify-center overflow-hidden rounded border border-border bg-bg">
        {company.logo_url ? (
          <img src={company.logo_url} alt={`Logo de ${company.name}`} className="size-full object-contain" />
        ) : (
          <span className="text-xs text-muted">Sin logo</span>
        )}
      </div>
      {canEdit && (
        <div className="space-y-1">
          {/* `relative` anchors the sr-only input so it doesn't escape the layout. */}
          <label className="relative inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium transition-colors hover:bg-surface-2 focus-within:outline-2 focus-within:outline-accent">
            {mutation.isPending && <LoaderCircle aria-hidden="true" className="size-4 animate-spin" />}
            {mutation.isPending ? 'Subiendo…' : 'Cambiar logo'}
            <input
              type="file"
              accept=".jpg,.jpeg,.png,.webp"
              aria-describedby={hintId}
              onChange={handleFile}
              disabled={mutation.isPending}
              className="sr-only"
            />
          </label>
          <p id={hintId} className="text-xs text-muted">
            JPG, PNG o WebP, hasta 2 MB.
          </p>
          {error && (
            <p role="alert" className="text-sm text-danger">
              {error}
            </p>
          )}
        </div>
      )}
    </div>
  )
}
