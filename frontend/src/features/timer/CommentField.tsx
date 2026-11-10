import { TextAreaField } from '@/components/ui/TextAreaField'
import { MIN_COMMENT_LENGTH } from './types'

interface CommentFieldProps {
  label?: string
  value: string
  onChange: (value: string) => void
  error?: string
}

/** RF-05.16 "¿Qué hiciste?": shows how many characters are still missing. */
export function CommentField({ label = '¿Qué hiciste?', value, onChange, error }: CommentFieldProps) {
  const length = value.trim().length
  const missing = Math.max(0, MIN_COMMENT_LENGTH - length)

  return (
    <div className="space-y-1">
      <TextAreaField
        label={label}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        error={error}
        maxLength={5000}
        required
      />
      <p className={`text-xs ${missing > 0 ? 'text-muted' : 'text-success'}`} aria-live="polite">
        {missing > 0
          ? `${length}/${MIN_COMMENT_LENGTH} caracteres · faltan ${missing}`
          : `${length} caracteres`}
      </p>
    </div>
  )
}
