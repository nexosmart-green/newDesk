import { useId, type TextareaHTMLAttributes } from 'react'
import { inputClassName } from './TextField'

interface TextAreaFieldProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label: string
  error?: string
}

export function TextAreaField({ label, error, className, rows = 3, ...textareaProps }: TextAreaFieldProps) {
  const id = useId()
  const messageId = `${id}-message`

  return (
    <div className={`space-y-1 ${className ?? ''}`}>
      <label htmlFor={id} className="block text-sm text-muted">
        {label}
      </label>
      <textarea
        id={id}
        rows={rows}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? messageId : undefined}
        className={inputClassName}
        {...textareaProps}
      />
      {error && (
        <p id={messageId} className="text-sm text-danger">
          {error}
        </p>
      )}
    </div>
  )
}
