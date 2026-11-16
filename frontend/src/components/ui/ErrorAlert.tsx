import { CircleAlert, CircleCheck } from 'lucide-react'
import { ApiError } from '@/lib/api'
import { errorTextOf } from '@/lib/errorText'
import { claimAllFieldErrors } from '@/lib/fieldErrors'

interface ErrorAlertProps {
  error: unknown
  /** When true, a validation error with field errors also shows (its first field message). */
  showFieldErrors?: boolean
}

/**
 * Inline error for places that explain it next to the action (auth forms, timer prompts, actions
 * without inputs). Most mutation errors go to a toast instead (see `lib/queryClient.ts`).
 */
export function ErrorAlert({ error, showFieldErrors = false }: ErrorAlertProps) {
  if (!error) return null
  const apiError = error instanceof ApiError ? error : null
  if (apiError?.hasFieldErrors() && !showFieldErrors) return null

  // Shown here, so the hidden-field toast (lib/queryClient.ts) doesn't repeat it.
  if (apiError && showFieldErrors) claimAllFieldErrors(apiError)
  const firstFieldMessage = Object.values(apiError?.fieldErrors ?? {})[0]?.[0]
  const message = (showFieldErrors ? firstFieldMessage : undefined) ?? errorTextOf(error).message

  return (
    <p role="alert" className="flex items-start gap-2 rounded-lg border border-danger/40 bg-danger/10 px-3 py-2.5 text-sm text-danger">
      <CircleAlert aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
      <span>{message}</span>
    </p>
  )
}

export function SuccessNotice({ message }: { message: string | null | undefined }) {
  if (!message) return null
  return (
    <p role="status" className="flex items-start gap-2 rounded-lg border border-success/40 bg-success/10 px-3 py-2.5 text-sm text-success">
      <CircleCheck aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
      <span>{message}</span>
    </p>
  )
}

/**
 * For actions with no inputs to hang a field error on (confirm dialogs, rows): shows the first
 * validation message. Business-rule errors are not repeated here — the toast already says them.
 */
export function FieldErrorsAlert({ error }: { error: unknown }) {
  if (!(error instanceof ApiError) || !error.hasFieldErrors()) return null
  return <ErrorAlert error={error} showFieldErrors />
}
