import { LoaderCircle } from 'lucide-react'
import type { ButtonHTMLAttributes } from 'react'

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'link'

const variants: Record<Variant, string> = {
  // Brand teal from desk.nexosmart.es, the AA-safe shade for white text.
  primary: 'min-h-11 rounded-lg bg-brand-strong px-4 py-2 text-white shadow-sm transition-colors hover:bg-brand active:bg-brand-strong',
  secondary:
    'min-h-11 rounded-lg border border-border bg-surface px-4 py-2 text-text transition-colors hover:border-muted/50 hover:bg-surface-2',
  danger: 'min-h-11 rounded-lg bg-danger px-4 py-2 text-white transition-opacity hover:opacity-90',
  ghost: 'min-h-11 rounded-lg px-3 py-2 text-muted transition-colors hover:bg-surface-2 hover:text-text',
  link: 'text-accent underline-offset-2 hover:underline',
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant
  /** While the action runs: spinner, disabled, same width (the label stays, invisible, for size and name). */
  loading?: boolean
}

export function Button({ variant = 'primary', loading = false, className, type = 'button', disabled, children, ...props }: ButtonProps) {
  return (
    <button
      type={type}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={`relative inline-flex cursor-pointer items-center justify-center gap-1.5 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50 aria-busy:cursor-progress aria-busy:opacity-80 ${variants[variant]} ${className ?? ''}`}
      {...props}
    >
      <span className={`inline-flex items-center justify-center gap-1.5 ${loading ? 'opacity-0' : ''}`}>{children}</span>
      {loading && (
        <span className="absolute inset-0 grid place-items-center" aria-hidden="true">
          <LoaderCircle className="size-4 animate-spin" />
        </span>
      )}
    </button>
  )
}
