import { ApiError } from '@/lib/api'

interface ErrorAlertProps {
  error: unknown
  /** When true, a validation error with field errors also shows (its first field message). */
  showFieldErrors?: boolean
}

/** Business-rule and generic errors as an alert. Field errors are rendered inline by each field. */
export function ErrorAlert({ error, showFieldErrors = false }: ErrorAlertProps) {
  if (!error) return null
  const apiError = error instanceof ApiError ? error : null
  if (apiError?.hasFieldErrors() && !showFieldErrors) return null

  const firstFieldMessage = Object.values(apiError?.fieldErrors ?? {})[0]?.[0]
  const message = (showFieldErrors ? firstFieldMessage : undefined) ?? apiError?.message ?? 'Error inesperado'

  return (
    <p role="alert" className="rounded border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
      {message}
    </p>
  )
}

export function SuccessNotice({ message }: { message: string | null | undefined }) {
  if (!message) return null
  return (
    <p role="status" className="rounded border border-success/40 bg-success/10 px-3 py-2 text-sm text-success">
      {message}
    </p>
  )
}
