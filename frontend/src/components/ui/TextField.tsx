import { useId, type InputHTMLAttributes } from 'react'

interface TextFieldProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string
  error?: string
  hint?: string
}

export const inputClassName =
  'min-h-11 w-full rounded-lg border border-border bg-bg px-3 py-2 transition-colors hover:border-muted/60 focus-visible:border-accent focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-accent/40 disabled:opacity-60 aria-[invalid=true]:border-danger'

export function TextField({ label, error, hint, className, ...inputProps }: TextFieldProps) {
  const id = useId()
  const messageId = `${id}-message`
  const message = error ?? hint

  return (
    <div className={`space-y-1 ${className ?? ''}`}>
      <label htmlFor={id} className="block text-sm text-muted">
        {label}
      </label>
      <input
        id={id}
        aria-invalid={error ? true : undefined}
        aria-describedby={message ? messageId : undefined}
        className={inputClassName}
        {...inputProps}
      />
      {message && (
        <p id={messageId} className={`text-sm ${error ? 'text-danger' : 'text-xs text-muted'}`}>
          {message}
        </p>
      )}
    </div>
  )
}
