import { useId, type SelectHTMLAttributes } from 'react'
import { inputClassName } from './TextField'

interface SelectFieldProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label: string
  error?: string
}

export function SelectField({ label, error, className, children, ...selectProps }: SelectFieldProps) {
  const id = useId()
  const messageId = `${id}-message`

  return (
    <div className={`space-y-1 ${className ?? ''}`}>
      <label htmlFor={id} className="block text-sm text-muted">
        {label}
      </label>
      <select
        id={id}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? messageId : undefined}
        className={inputClassName}
        {...selectProps}
      >
        {children}
      </select>
      {error && (
        <p id={messageId} className="text-sm text-danger">
          {error}
        </p>
      )}
    </div>
  )
}
